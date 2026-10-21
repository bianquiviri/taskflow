# TaskFlow

[![CI](https://github.com/bianquiviri/taskflow/actions/workflows/ci.yml/badge.svg)](https://github.com/bianquiviri/taskflow/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/bianquiviri/taskflow?sort=semver)](https://github.com/bianquiviri/taskflow/releases)
[![License: CC BY-NC-ND 4.0](https://img.shields.io/badge/License-CC%20BY--NC--ND%204.0-lightgrey.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4-8892BF?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Vue](https://img.shields.io/badge/Vue-3-42b883?logo=vuedotjs&logoColor=white)](https://vuejs.org/)

Professional project and task management SaaS, built with the latest Laravel
stack to demonstrate senior software engineering practices: **TDD, clean
layered architecture, GitFlow, and CI/CD from day one.**

## Features

- **Auth & Teams** — register/login, email verification, password reset,
  teams with owner/admin/member roles, invitations by email (one-time token),
  member management.
- **Projects & Tasks** — projects with members and roles, task boards with
  status/priority, assignment, filtering, search and pagination.
- **Collaboration** — threaded comments with `@mention` notifications, and an
  immutable activity-log audit trail for projects and tasks.
- **UX** — Inertia + Vue 3 shell with dark mode, design-system tokens, form
  components, empty states and optimistic UI.
- **Async** — queued transactional mail (Redis → Mailpit locally).

## Stack

- **Backend**: Laravel 13, PHP 8.4
- **Frontend**: Inertia.js v3, Vue 3, Tailwind CSS 4, Vite 8
- **Database**: MySQL 8.4 (Redis for session/cache/queue)
- **Local HTTPS**: Traefik + mkcert → `https://taskflow.josebianco.local`
- **Testing**: Pest, Vitest, Playwright
- **Process**: GitFlow with protected `main` and gated CI

## Quick Start (Docker)

**Prerequisite:** Docker (Desktop or Engine) with the Docker daemon running.
No PHP, Node, Composer, npm, or mkcert installation on your machine is needed —
everything (including TLS certificate generation and Playwright) runs inside
containers.

```sh
make doctor   # verifies the only host requirements (optional but recommended)
make hosts    # adds the local domain to /etc/hosts (one-time, asks for admin)
make certs    # generates the TLS certificate in Docker (one-time)
make up       # build & start the full stack (installs deps + app key automatically)
make migrate  # run database migrations
```

Open **https://taskflow.josebianco.local** and your work is live over HTTPS.

> The only two steps that touch your machine are unavoidable because your
> browser lives there: the `/etc/hosts` entry (`make hosts`) and — only when a
> new root CA is created — trusting it in the keychain (`make trust-ca`).
> Both are run **once**; `make certs` keeps reusing the same CA afterwards, so
> you will not be asked again.

### Running only changed tests

Pest runs parallel by default (`--parallel`, ~5 s for the full suite). To run
just the tests touching your change:

```sh
docker compose exec app php artisan test --filter=TaskCrudTest --parallel --no-coverage
```

`--filter` accepts a substring, a comma list (`TaskCrudTest,TeamPolicyTest`)
or a class method (`TaskCrudTest::it_updates_a_task`). Architecture
layering rules live in `tests/Architecture/ArchitectureTest.php` and run with
the suite.

### Useful commands

```sh
make doctor       # check Docker, .env, hosts, certs, app health
make test         # backend tests (Pest)
make test-fe      # frontend tests (Vitest)
make e2e          # end-to-end (Playwright, inside Docker)
make lint         # Pint + ESLint
make fix          # auto-fix Pint violations
make shell        # bash inside the app container
make mysql        # MySQL client
make logs         # tail all service logs
make queue        # run an extra foreground Redis queue worker (Ctrl+C to stop)
make deps         # (re)install composer + npm dependencies
```

### Queued email & Mailpit

Transactional email (auth notifications, etc.) is delivered asynchronously:
notifications that `implement ShouldQueue` are pushed onto the **Redis** `default`
queue and consumed by the queue worker, which then sends them through the SMTP
mailer to **Mailpit** (the local capture inbox).

The worker runs **automatically inside the `app` container** (supervised by
`docker/app/supervisord.conf`), so `make up` alone delivers mail — no extra step:

```sh
make up       # start the stack; the queue worker starts with it
make queue    # optional: a second, foreground worker for debugging
```

Open **http://localhost:8025** to inspect every outgoing email that the app would
send in production. If the Mailpit inbox stays empty, check the app logs
(`make logs`) for the `queue-worker` program and confirm `.env` keeps
`QUEUE_CONNECTION=redis` (the default in `.env.example`).

### MCP servers (OpenCode)

`opencode.json` wires four MCP servers: `github` (remote), `playwright`,
`mysql` and `docker`. Check their state with:

```sh
opencode mcp list
```

Three of them need nothing beyond the stack already running. The `github`
server needs a credential — it talks to `api.githubcopilot.com/mcp/` with a
Personal Access Token, read from a file **outside the repository** so it is
never committed:

```sh
# one-time setup (reuses the token already stored in the macOS keychain by `gh`)
umask 077
printf '%s' "$(gh auth token)" > ~/.config/opencode/github-token

opencode mcp list   # → ✓ github connected
```

`opencode.json` reads it through the `{file:...}` substitution
(`"Authorization": "Bearer {file:~/.config/opencode/github-token}"`). A token
with the `repo` scope is enough. To use a different token, replace the file
contents and restart OpenCode — no secret ever enters the working tree.

> The credential is **not** read from an environment variable on purpose:
> OpenCode runs its MCP connections in a background service that does not
> inherit the shell's environment, so `{env:GITHUB_TOKEN}` would silently
> expand to an empty string and the server would answer
> `400 Authorization header is badly formatted`.

## GitFlow

- `main` — production. Protected: merges only via PR from `develop` with CI green.
- `develop` — integration branch. New work always starts from here:
  `git flow feature start <issue>-<slug>`.
- Branches: `feature/*`, `bugfix/*`, `release/*`, `hotfix/*`.

## Project Rules

Read **[AGENTS.md](AGENTS.md)** for mandatory rules (TDD, KISS, PSR-12,
conventions) and **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** for the
system design every agent and developer follows.

## Roadmap

Planned milestones (see GitHub issues):

1. **M1 — Infrastructure**: scaffold, Docker, CI, test suite.
2. **M2 — Auth & Teams**: multi-role auth, teams, invitations.
3. **M3 — Domain**: projects, tasks, comments, activity log.
4. **M4 — UX/UI**: dashboard, components, dark mode.
5. **M5 — Deploy & Docs**: Cloud Run, DNS, documentation.

## License

Copyright (c) 2026 Jose Antonio Bianco. All rights reserved under the
**Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International**
license ([CC BY-NC-ND 4.0](https://creativecommons.org/licenses/by-nc-nd/4.0/)):
you may view and share this work, unmodified and for non-commercial purposes
only, crediting the author. Commercial use and modified distributions are not
permitted. See [LICENSE](LICENSE) for the full legal code.
