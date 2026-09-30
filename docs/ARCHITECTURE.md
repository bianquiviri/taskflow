# TaskFlow — System Architecture

TaskFlow is a multi-tenant-style SaaS for project & task management. It follows
a conventional **Laravel layered architecture** optimised for testability,
reuse, and parallel development.

## High-Level Overview

```
Browser
  │  HTTPS (Traefik terminate TLS)
  ▼
nginx (app container) ──► php-fpm (Laravel 13)
                                  │
              ┌───────────────────┼────────────────┐
              ▼                   ▼                ▼
           MySQL 8.4          Redis 7           Mailpit (dev)
        (persistence)     (session/cache/queue)   (mail capture)
```

- Traefik terminates TLS (mkcert certs locally, Let's Encrypt later).
- The `app` container runs nginx + php-fpm (supervisord).
- Node container runs Vite dev server for the frontend.

## Layered Architecture

### 1. Presentation — Controllers & Inertia Pages

- Controllers receive a validated **FormRequest**, call one **Action** or
  **Service**, and return an Inertia response or a redirect.
- Controllers never contain business rules.
- Inertia pages under `resources/js/Pages/**` receive typed props and render
  Vue components. Shared state (auth user, flash) flows via
  `HandleInertiaRequests`.

### 2. Application — Actions, Services, Jobs

- `app/Actions/**`: single-purpose domain operations. Each Action is an
  invokable class with one responsibility:
  `CreateProjectAction`, `AssignTaskAction`, `InviteTeamMemberAction`.
- `app/Services/**`: cross-cutting orchestration that references multiple
  entities or third parties: `ReportService`, `NotificationService`,
  `ProjectMetricsService`.
- `app/Jobs/**`: queued work (digests, exports). Always dispatched via
  `->onQueue()` so HTTP requests return fast.

**Rule of thumb:** if a controller method grows past ~10 lines, extract an
Action. If two Actions share logic, extract it into a Service.

### 3. Domain — Models, Enums, DTOs, Policies

- Eloquent models expose `scopes`, `casts`, and relationships only. No fat
  methods beyond thin, intention-revealing helpers.
- `app/Enums/**`: native PHP enums for fixed value sets
  (`TaskStatus`, `TaskPriority`, `ProjectRole`). Enums implement
  interfaces (e.g. `HasLabel`) and are used in migrations/casts.
- `app/DTOs/**`: immutable data-transfer objects for complex hand-offs between
  layers (`CreateTaskData`, `ReportFiltersData`).
- `app/Policies/**`: authorization per model, aligned with the role model
  (`ProjectPolicy`, `TaskPolicy`). Controllers rely on `authorize()` /
  `Gate`.

### 4. Cross-Cutting — Observers, FormRequests, Middleware, Notifications

- `app/Observers/**`: lifecycle side-effects, e.g. `TaskObserver` appends an
  `ActivityLog` entry on create/update.
- `app/Http/Requests/**`: one FormRequest per mutation; validation lives here,
  never in controllers.
- `app/Notifications/**`: transactional email/database notifications. Email
  notifications must `implement ShouldQueue` (see below for queued delivery).
- `app/Support/**`: small framework-agnostic helpers.

### Queued mail & the queue worker

Mail is delivered asynchronously. Notifications implementing `ShouldQueue` are
pushed as `SendQueuedNotifications` jobs onto the **Redis `default` queue**
(`QUEUE_CONNECTION=redis`). A single attached worker consumes that queue with the
`queue:work redis --tries=3` command (`make queue`). During local development the
worker forwards messages to **Mailpit** (`smtp://mailpit:1025`), whose capture
inbox is browsable at `http://localhost:8025`. There is no long-running detached
queue service in Compose; the attached worker keeps the local footprint minimal.

## Domain Model (v1)

Defined for the first release. Extend per GitHub issues.

```
User ──1:N── TeamMember ──N:1── Team
  │                              │ 1
  │                              ▼
  └────1:N── Project ──1:N── Task ──1:N── Comment
                  │                 │
                  │                 └── User (assignee)
                  └── ProjectMember (role: owner | admin | member)
```

### Entities

| Entity          | Purpose                                            |
| --------------- | -------------------------------------------------- |
| `User`          | Account owner; has many teams & owned projects     |
| `Team`          | Workspace that gathers projects                    |
| `TeamMember`    | User membership in a team with a role              |
| `Project`       | Container of tasks with deadlines & members        |
| `ProjectMember` | User role inside a project                         |
| `Task`          | Work item: title, description, status, priority    |
| `Comment`       | Discussion thread on a task                        |
| `ActivityLog`   | Immutable audit trail of domain events             |

### Enums

- `TaskStatus`: todo | in_progress | in_review | done | cancelled
- `TaskPriority`: low | medium | high | urgent
- `ProjectRole`: owner | admin | member
- `TeamRole`: owner | admin | member

## Database

- MySQL 8.4 with `utf8mb4` and `InnoDB`.
- All FKs indexed; soft deletes where historicity matters (users archive).
- `refreshDatabase`/`RefreshDatabase` in Feature tests; CI uses SQLite
  in-memory for speed.
- Migrations are additive only after the first release; never edit a shipped
  migration.

## Testing Strategy

| Layer    | Tool        | Scope                                            |
| -------- | ----------- | ------------------------------------------------ |
| Backend  | Pest        | Feature tests per use-case; unit tests for Services/Actions/DTOs |
| Frontend | Vitest      | Component specs with props (no HTTP)             |
| E2E      | Playwright  | Critical journeys (register → create project → task) |

- Coverage baseline: **≥ 80%** for backend and frontend (`vitest --coverage`
  enforced in CI; Pest coverage reported, tracked over time).

## Deployment (roadmap, activated later)

- Container image on Google **Cloud Run** (scale-to-zero).
- Managed MySQL (Aiven free tier or Cloud SQL) + Redis.
- GitHub Actions builds the image and deploys to a `taskflow.<domain>`
  subdomain; SSL managed by the platform.
- This repo is developed **locally only** until the cloud integration phase.

### Production image (Dockerfile.prod)

`Dockerfile.prod` builds the deployable image. It is a **multi-stage** build
so that nothing but nginx, php-fpm and the application ends up in it:

| Stage           | Base                  | Produces                                       |
| --------------- | --------------------- | ---------------------------------------------- |
| `frontend`      | `node:22-alpine`      | `public/build` (Vite: hashed CSS + JS bundle)  |
| `php-extensions`| `php:8.4-fpm-alpine`  | `pdo_mysql`, `intl`, `zip`, `bcmath`, `opcache`, `redis` (toolchain then dropped) |
| `vendor`        | `php:8.4-fpm-alpine`  | `vendor/` via `composer install --no-dev`      |
| `runtime`       | `php:8.4-fpm-alpine`  | the final image: nginx + php-fpm + the app      |

```sh
docker build -f Dockerfile.prod -t taskflow:latest .
```

- **Hermetic build**: it runs from a clean checkout — `vendor/` and
  `node_modules/` are never needed on the host, everything is compiled from
  the committed `composer.lock` / `package-lock.json`. `.dockerignore` keeps
  secrets (`.env`, TLS keys), dev-only trees (`tests/`, `docs/`, tooling
  configs) and host-built artefacts (`public/build/`, `bootstrap/cache/*.php`)
  out of the context; the hashed assets are rebuilt in-image instead.
- **Runtime**: nginx serves `public/` and talks to php-fpm over the unix
  socket `/run/php-fpm.sock` (no port is exposed between the two); only
  `index.php` is executable. Configuration lives in `docker/prod/`
  (`nginx.conf`, `php-fpm.conf`, `php.ini`, `entrypoint.sh`) and deliberately
  diverges from the dev stack: opcache no longer polls timestamps, `.env` never
  exists in the image, and both services log to stdout/stderr.
- **No secrets, no state**: all configuration is injected as environment
  variables at start time. Because of that the entrypoint builds the framework
  caches (config/routes/views) on every boot rather than at build time, then
  supervises nginx + php-fpm itself — keeping a Python runtime for a process
  supervisor out of the image. Either process exiting ends the container so the
  platform restarts a clean pair; `SIGTERM` drains php-fpm gracefully.
- **Operations**: `HEALTHCHECK` polls `/up` through nginx and php-fpm. Run
  migrations as a one-off — `docker run --rm taskflow:latest php artisan
  migrate --force` — and the queue worker as
  `docker run --rm taskflow:latest php artisan queue:work --tries=3`.
- **Port**: the container listens on plain HTTP `:80`; TLS is terminated by the
  platform's edge proxy. Wiring Cloud Run's `PORT` (8080) is part of the cloud
  integration phase, not of this image.

CI builds the image on every push and pull request (`docker` job in
`.github/workflows/ci.yml`).

## Parallel Development

GitHub issues are grouped by milestone; agents work on disjoint domains:

| Agent          | Owns                                             |
| -------------- | ------------------------------------------------ |
| `scaffolder`   | Repo, CI, Docker, docs, releases                 |
| `auth-agent`   | Auth, teams, invitations, profile, policies      |
| `domain-agent` | Projects, tasks, comments, files, activity log   |
| `reporting-agent` | Dashboard, metrics, audit exports, reports    |
| `frontend-agent` | UI shell, components, responsive, dark mode    |
| `infra-agent`  | Production image, Cloud Run, TLS, DNS            |

Conflicts are avoided by owning specific directories and issue scopes.