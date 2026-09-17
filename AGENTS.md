# TaskFlow — Project Rules & Conventions

TaskFlow is a professional SaaS for project and task management built with the
latest Laravel stack. Every contribution must follow the rules below. These
rules are mandatory for human developers AND AI agents.

## Stack

| Layer      | Technology                                        |
| ---------- | ------------------------------------------------- |
| Backend    | PHP 8.4, Laravel 13 (latest)                      |
| Database   | MySQL 8.4 (Docker), Test: SQLite in-memory        |
| Frontend   | Inertia.js v3, Vue 3, Tailwind CSS 4, Vite 8      |
| Testing    | Pest (backend), Vitest (frontend), Playwright (e2e) |
| Infra      | Docker Compose, Traefik (HTTPS), Redis, Mailpit   |

## GitFlow — Branching Model

```
main ───────────────────────────── (production; merge ONLY from develop)
   └── develop ─────────────────── (integration; never commit directly)
         └── feature/<id>-<slug> ── (new work) → PR → develop
         └── bugfix/<id>-<slug> ─── (fixes)    → PR → develop
         └── release/vX.Y.Z ─────── (release prep) → main
         └── hotfix/<id>-<slug> ─── (emergency) → main AND develop
```

- **`main` is protected.** No direct pushes. Only PRs from `develop` (or a
  `hotfix/` branch) with all CI checks green. Merging to `main` produces a
  `vX.Y.Z` tag + release notes.
- **`develop` is the integration branch.** All `feature/*` and `bugfix/*`
  branches must be created from it and merged back into it via PR.
- Branch names MUST be prefixed: `feature/`, `bugfix/`, `release/`, `hotfix/`.
- Keep feature branches short-lived; rebase onto `develop` before opening a PR.
- Never commit directly to `main` or `develop`.

## TDD — Test Driven Development (MANDATORY)

1. RED: write a failing test describing the behaviour first.
2. GREEN: implement the minimum code to make it pass.
3. REFACTOR: clean the implementation while tests stay green.

- Every new feature, fix, or refactor MUST ship with tests.
- Backend: Pest feature tests. Frontend: Vitest. E2E: Playwright.
- Run the full suite before every push:
  ```sh
  make test        # Pest
  make test-fe     # Vitest
  make e2e         # Playwright
  make lint        # Pint + ESLint
  ```

## KISS & YAGNI

- Prefer the simplest solution that satisfies the requirement.
- Do NOT add speculative abstractions, unused methods, or dead code.
- A method does one thing. A class has one responsibility.
- If a query/code is used in more than one place, extract it (DRY).
- Prefer Laravel's built-in features over custom code whenever possible.

## PHP Standards (latest)

- PSR-12 style, enforced automatically with Pint (`make lint` / `make fix`).
- Use modern PHP 8.4 features:
  - `readonly` classes and properties where appropriate.
  - Native `enum`s for fixed value sets.
  - Typed properties, promoted constructor parameters, named arguments.
  - `declare(strict_types=1)` at the top of every PHP file (Pint enforces it).
  - Arrow functions and match expressions instead of verbose alternatives.
- Use Laravel 13 idioms: route model binding, form requests, casts, enums in
  migrations, `Str`, `Carbon`, collections. Avoid `query()->where(...)` chains
  in favour of Eloquent scopes and relations where clearer.

## Code Organisation

Add business logic to the appropriate layer (`docs/ARCHITECTURE.md`):

```
app/
├── Actions/        # single-purpose domain operations (CreateTask, AssignTask)
├── Services/       # reusable business orchestration (reports, notifications)
├── DTOs/           # immutable typed data transfers
├── Enums/          # TaskStatus, Priority, TeamRole...
├── Policies/       # authorization rules per model
├── Observers/      # model lifecycle side-effects (audit trail)
├── Jobs/           # queued work (digests, exports)
├── FormRequests/   # validation, one per mutation
```

- Controllers stay thin: delegate to Services/Actions, return Inertia
  responses or redirects.
- Do NOT put business logic in models, controllers, or Blade templates.
- Code and identifiers are written in **English**.

## Frontend Conventions

- Pages live in `resources/js/Pages/`; reusable UI in `resources/js/Components/`.
- One Vue component per concern; use `<script setup>`.
- Use Tailwind utility classes; no hand-written CSS unless unavoidable.
- Components must not fetch data directly; receive props from Inertia pages.
- Every component with logic ships a Vitest spec.

## Commits

- Conventional Commits, written in **English**:
  `feat(scope): desc`, `fix(scope): desc`, `test(scope): desc`,
  `docs: desc`, `chore(scope): desc`, `refactor(scope): desc`, `ci: desc`.
- Reference the GitHub issue in the body: `Closes #12`.
- One logical change per commit. Keep history clean (no WIP noise).

## Pull Requests

- Every PR links an issue and targets `develop`.
- PR title uses conventional commits; description lists acceptance criteria.
- CI must be green (lint + backend + frontend + coverage + e2e).
- Reviewer(s) approve before merge; the author then squashes the branch.

## Environment

- Local URL: `https://taskflow.josebianco.local` (HTTPS via Traefik + mkcert).
- `.env` is gitignored; use `.env.example` as the template.
- Never commit secrets, keys, certs, or DB dumps.
- Use `make up` to start the stack and `make certs` to (re)generate TLS certs.