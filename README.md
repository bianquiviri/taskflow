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

**Prerequisites:** Docker, mkcert, and one `/etc/hosts` entry:

```
127.0.0.1  taskflow.josebianco.local
```

```sh
make certs   # generate TLS certificate (once per machine)
make up      # build & start the full stack
```

Open **https://taskflow.josebianco.local** and your work is live over HTTPS.

### Useful commands

```sh
make test        # backend tests (Pest)
make test-fe     # frontend tests (Vitest)
make e2e         # end-to-end (Playwright)
make lint        # Pint + ESLint
make fix         # auto-fix Pint violations
make shell       # bash inside the app container
make mysql       # MySQL client
make logs        # tail all service logs
```

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