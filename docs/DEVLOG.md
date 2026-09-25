# TaskFlow — Development Log (DEVLOG)

A running, human-readable record of what changed during each working session and
why. It is loaded automatically by OpenCode on every session start (via
`opencode.json` → `instructions`), so any agent or developer can pick up the
project without re-explaining it.

## Rules

- **Never close a working session without appending an entry here.** This is
  mandatory (see AGENTS.md).
- One entry per session, newest first. Written in English.
- Keep it concise: what changed, decisions taken, verification run, and what is
  next (if unfinished).
- Refreshing this log is a `docs:` commit.

## Resuming conversations with OpenCode

All OpenCode sessions are persisted locally and can be resumed:

```sh
opencode -c                       # continue the last session
opencode session list             # list stored sessions
opencode --session <session-id>   # resume a specific session
opencode export <session-id>      # export a conversation to JSON
```

Ad-hoc OpenCode sessions do not replace this log; use this file as the source of
truth for the project's evolution.

---

## 2026-09-25 — Release v0.1.0 + parallel agents (#11/#16/#27)

**Context:** The Dockerized stack was released to `main`; three P0 issues were
implemented by parallel agents on disjoint domains and integrated into
`develop`; an integration lint issue was fixed.

**Changes:**

- **GitFlow:** `release/v0.1.0` was fast-forwarded to `develop` and merged to
  `main` via PR #36 → tag `v0.1.0` + GitHub release notes created. `develop`
  was then merged back to `main` parity (`99de8ee`).
- **Parallel agents:** three agents ran concurrently, each in an isolated clone
  (no shared working tree; TDD; own `feature/*` branch; no DEVLOG edits — kept
  for the scaffolder to avoid merge conflicts). Integrated via PRs #39/#40/#41
  (squash).
- **#11 — Project entity** (`feature/11-project-model`): `projects` +
  `project_members` migrations, `Project` model (slug + numeric disambiguation,
  `archived_at` cast, `forUser`/`active`/`archived` scopes), `ProjectMember`,
  `ProjectRole` enum, factory, `Create/Update/ArchiveProjectAction`,
  `ProjectController` (index/show/store/update/archive), `ProjectPolicy`
  (view=member, update/archive=owner/admin), FormRequests, `/projects` routes,
  minimal Inertia pages, 17 feature tests. Guest-gating test deferred until
  login routes exist (auth-agent scope, `route('login')` not yet defined).
- **#16 — Application shell** (`feature/16-app-shell`): `AppLayout.vue`
  (responsive sidebar + topbar + mobile drawer), 14 inline-SVG `Icon`s +
  resolver, `Avatar` (image/initials), `FlashMessages` decoding the `flash`
  shared prop (wired in `HandleInertiaRequests`), Inertia progress bar
  (framework-native, no new dependency), `eslint.config.js` timer globals; 44
  Vitest specs, 100% statement coverage.
- **#27 — Redis queue worker** (`feature/27-redis-queue-worker`): `make queue`
  (attached worker `queue:work redis --tries=3`; no floating compose service —
  KISS), compose `app` env mirrors `QUEUE_CONNECTION`/`MAIL_*`, notifications
  queued via Laravel 13-native `ShouldQueue` (no `config/notifications.php`
  exists in the framework), `QueuedNotificationsTest`, README + ARCHITECTURE
  "queued mail" sections.
- **Fix (#42):** ESLint `vue/max-attributes-per-line` warnings in
  `Pages/Projects/Index.vue` introduced by #39 (the domain agent had not run
  the frontend linter).
- **#7 — Team & TeamMember models** (`feature/7-team-membership`, merged via
  PR #44): commit WIP auth-agent → `teams`/`team_members`/`team_invitations`
  migrations, `Team`/`TeamMember`/`TeamInvitation` models (casts, relations),
  `TeamRole` enum (label/color/canManageMembers), factories, `TeamObserver`
  registration, `TeamInvitationService` (hashed tokens, 7-day expiry,
  revoke), `TeamPolicy` (manageMembers = owner/admin), `User` relations
  (ownedTeams/memberships/teams), 4 test files (23 tests · 53 assertions).
  Integration fixes: `forOwner()/forTeam()/forUser()` don't take model
  instances in Laravel 13 → FK attributes; Carbon 3 `diffInHours()` is signed
  → absolute flag; Pint EOF newlines.

**Decisions:**

- Queue worker is an attached `make queue` target — simplest thing that works
  for a local dev stack; a detached worker service can come with deployment.
- Agents worked in isolated clones; scaffolding docs (DEVLOG/AGENTS) stay with
  the scaffolder to serialize writes to shared files.
- Guarding a *bare-clone* verification run: backend tests need `.env` with
  `APP_KEY` and a `npm run build` (Vite manifest) — exactly what CI provides —
  otherwise `MissingAppKeyException` / `ViteManifestNotFoundException` appear.

**Verification run (develop `41c3f2a` + fix):**

- Pint clean · Pest 22 passed (76 assertions) · ESLint clean (max-warnings=0)
  · Vitest 44 passed (100% stmt coverage) · `docker compose config` OK.

**Status:** `develop` green with #11/#16/#27/#7 (PRs #39–#44). Next: auth
milestones (email verification #6, invitations #8, profile #9), then #10/#12
domain issues.

---

## 2026-09-24 — Full-stack runs inside Docker (no host tooling required)

**Context:** The stack previously depended on host tools: `mkcert` for TLS,
`npx playwright` for e2e, and manual `composer install` / `npm install`. Goal:
a fresh machine with only Docker should be able to lift the whole project.

**Changes (branch `feature/dockerize-full-stack`):**

- **TLS certs dockerized** (`docker/certs/Dockerfile` + `entrypoint.sh`): the
  `certs` compose service runs mkcert inside a container and persists the root
  CA under `docker/traefik/ca/` (gitignored). Existing CA is reused → browsers
  that already trust it keep working without re-trust. The old host-based CA
  was migrated into `docker/traefik/ca/` (verified: `cert.pem: OK`).
- **E2E dockerized** (`docker/e2e/entrypoint.sh` + compose `e2e` service on the
  `e2e` profile, image `mcr.microsoft.com/playwright:v1.63.0-noble`): `make e2e`
  now runs Playwright inside a container against the real HTTPS app. The
  entrypoint rewrites `/etc/hosts` (app domain → Docker gateway) and builds
  production assets while hiding `public/hot`, then restores it via EXIT trap —
  Playwright's Chromium hardcodes `localhost` to `127.0.0.1` and cannot reach
  the Vite dev server, and the system CA store is ignored by Chromium (local
  e2e uses `ignoreHTTPSErrors: !isCI`; real TLS is checked by `make doctor`).
- **Self-bootstrapping stack** (`docker-compose.yml`): the `app` container now
  creates `.env` from `.env.example`, runs `composer install`, and generates
  `APP_KEY` when missing; `env_file` is optional with sane defaults; app has a
  `/up` healthcheck; `node` reconciles npm deps on boot.
- **New Make targets:** `doctor` (prereq check), `hosts` (one-time `/etc/hosts`
  entry), `trust-ca` (one-time keychain trust), `deps`.
- README quick start updated: only Docker needed.

**Verification:** `make lint` (Pint 33 files + ESLint PASS), `make test`
(Pest 5 passed), `make test-fe` (Vitest 2 passed), `make e2e` (Playwright
1 passed, `public/hot` restored after run), `make doctor` all OK, HTTPS
`/up` 200.

**Remaining host steps (unavoidable — the browser lives on the host):** one-time
`make hosts` and, only when a new root CA is created, `make trust-ca`.

**Session notes:** a long debugging chain showed the value of full diagnostics
upfront: layers were resolved one at a time (cert → CA trust → Chromium doesn't
use system CAs → Vite assets unreachable → `localhost` hardcoded in
curl/Chromium → `/etc/hosts` is mounted, `sed -i` fails → IPv6-first
resolution). The final design sidesteps all of it instead of fighting each
layer.

---

**Context:** First working session. Taskflow was scaffolded (M1) but the stack
was not running.

**Changes:**
- Added this DEVLOG and wired it into OpenCode instructions
  (`opencode.json` → `instructions`).
- Added a mandatory rule in AGENTS.md to log every session and to use
  `docs/DEVLOG.md` + archived OpenCode exports as project history.

**Work done:**
- Reviewed repository state: clean git tree on `develop`, 4 commits (scaffold).
- Prerequisites verified: Docker daemon up, `mkcert` installed, TLS certs in
  `docker/traefik/`, hosts entry for `taskflow.josebianco.local`, `.env` with
  `APP_KEY` set. Empty MySQL volume (fresh DB, migrations required).
- Started the stack with `make up` and migrated the fresh database.

**Problems found & fixed to get the stack running:**

1. **Traefik could not use the Docker provider** (404 on every request).
   - Symptom: `ERR Failed to retrieve information of the docker client and
     server host — Error response from daemon:<empty>` even though the socket
     works (`curl --unix-socket ... /_ping` → OK) both via Compose and a plain
     `docker run`. No internet access to pull a newer Traefik image.
   - Root cause: the `docker` API client bundled in Traefik v3.3.7 fails to
     negotiate with Docker Desktop daemon 29.5.3.
   - Fix: replaced dynamic discovery with **static file-provider routing**:
     added `docker/traefik/dynamic/routes.yml` (routers + `app:80` service),
     removed `--providers.docker.*` args, the Traefik container labels and the
     `docker.sock` mount. HTTPS/HTTP now return `200`.

2. **Vite dev server unreachable from the browser** (console
   `ERR_CONNECTION_REFUSED @ http://0.0.0.0:5173`).
   - Cause: `public/hot` (written by laravel-vite-plugin) contained
     `http://0.0.0.0:5173` because the node container ran
     `npm run dev -- --host 0.0.0.0`, and port `5173` was not published.
   - Fix: set `server.host: '0.0.0.0'` + `server.hmr.host: 'localhost'` in
     `vite.config.js` (the hot file resolves to `http://localhost:5173`) and
     published `5173:5173` in the `node` service.
   - Note: app nginx listens on `app:80`; Vite serves dev assets from the
     host's `localhost:5173`.

3. **`make test` failed: no coverage driver** in the local app image (Xdebug /
   PCOV not installed; no internet to install them).
   - Fix: local Makefile `test` now runs `php artisan test --ci --no-coverage`.
     CI (GitHub Actions) still runs with coverage via the `xdebug` setup.

4. **`make e2e` failed: "Process from config.webServer exited early"**.
   - Cause: the local (non-CI) Playwright `webServer` ran the one-shot
     `docker compose up -d --wait`, which exits after the stack is healthy, and
     the HTTPS health-check URL can fail against the self-signed cert.
   - Fix: keep the process alive (`&& exec tail -f /dev/null`) and health-check
     via `http://taskflow.josebianco.local/up` (baseURL stays HTTPS).

- Verified the app over HTTPS: `200`, Laravel + Vue welcome page renders, Vite
  assets load, no console errors. Full suites green: lint, Pest (5), Vitest (2),
  Playwright (1).

**Decisions:**
- Log strategy: `docs/DEVLOG.md` as the single source of truth for session
  history, complemented by OpenCode's local session DB (`opencode -c`,
  `opencode export <id>` for archiving).
- Traefik routes are defined statically (file provider) instead of via the
  Docker provider, which is currently broken on this Docker 29.5.3 + Traefik
  v3.3.7 combo. Revisit once a compatible Traefik image can be pulled.
- Tests run without coverage locally (`--no-coverage`); coverage lives in CI.

**Status:** Stack running, app reachable over HTTPS, frontend dev assets OK,
all test suites green (lint / Pest / Vitest / Playwright). No commit yet; the
session's changes are uncommitted (review before commit).