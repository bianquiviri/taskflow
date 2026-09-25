# TaskFlow

Professional project and task management SaaS, built with the latest Laravel
stack to demonstrate senior software engineering practices: **TDD, clean
layered architecture, GitFlow, and CI/CD from day one.**

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
make queue        # run the Redis queue worker (attached; Ctrl+C to stop)
make deps         # (re)install composer + npm dependencies
```

### Queued email & Mailpit

Transactional email (auth notifications, etc.) is delivered asynchronously:
notifications that `implement ShouldQueue` are pushed onto the **Redis** `default`
queue and consumed by the queue worker, which then sends them through the SMTP
mailer to **Mailpit** (the local capture inbox).

```sh
make up       # start the stack
make queue    # start the queue worker in a terminal
```

While `make queue` is running, open **http://localhost:8025** to inspect every
outgoing email that the app would send in production. If the worker sees no
jobs, verify the worker terminal is attached and that `.env` keeps
`QUEUE_CONNECTION=redis` (the default in `.env.example`).

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

MIT