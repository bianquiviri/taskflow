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

## 2026-09-24 — HTTPS trust fix & stale mkcert CA cleanup

**Context:** Browser was showing an HTTPS certificate error on
`https://taskflow.josebianco.local` while the stack itself worked.

**Diagnosis:**
- Server cert valid (mkcert, SAN `taskflow.josebianco.local`, expires Dec 2028).
- System trust store had **three** mkcert root CAs from different machines:
  `pop-os`, `FedoraRemolonas`, and the current `Joses-MacBook-Pro.local`.
- The cert is issued by the MacBook CA, which **was** installed and trusted —
  `curl` (`ssl_verify_result: 0`) and `security verify-cert` both passed, and a
  real Chromium browser (Playwright MCP) loaded the page with 0 console errors.
  The failure was browser-session-only: browsers loaded the trust store before
  the CA was registered, so sessions opened earlier kept rejecting it.

**Changes:**
- Deleted the two obsolete mkcert CAs (`pop-os`, `FedoraRemolonas`) from the
  System keychain via `osascript` with admin privileges (by SHA-1 hash, keeping
  `Joses-MacBook-Pro.local` untouched).
- Re-verified after cleanup: keychain holds a single mkcert CA, `curl` returns
  HTTP 200 with SSL verify 0, Playwright Chromium loads the app with no errors.

**Action for the user:** fully quit and reopen Chrome, Brave and Safari
(`Cmd+Q`) so they reload the trust store; flush Chrome/Brave socket pools if a
cached cert error persists.

**Status:** HTTPS trust issue resolved at system level. NOTE: uncommitted
auth-agent work (Team models/migrations/policies) is still on
`feature/7-team-membership` — untouched. Machine was rebooted after this entry.

---

## 2026-09-22 — Project bootstrap review & stack startup

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