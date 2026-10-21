# Changelog

All notable changes to TaskFlow are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

## [1.2.0] - 2026-10-21

Feature release: opaque resource ids in URLs, one shared sign-in form, a
navigable and scalable project view, and the two MCP servers that had never
connected.

### Added

- **Encrypted resource ids in URLs** (#101) — every route that addresses a
  project, task, team, membership, invitation or attachment now carries an
  opaque token instead of a sequential primary key. `app/Support/IdCipher.php`
  (AES-256-CBC with a synthetic IV derived from the plaintext and sealed under
  `APP_KEY`) is **deterministic**, so `route()` stays stable, and
  **self-checking**: an edited token resolves to nothing instead of pointing at
  another record. `HasEncryptedRouteKey` decrypts it in
  `resolveRouteBindingQuery()` (scoped child bindings included) and serialises
  it as `key` beside the numeric `id`. **A numeric id in a URL is now a 404**,
  so old bookmarks break by design.
- **Shared sign-in form** (#97) — `Components/Auth/LoginForm.vue` is now the one
  sign-in form, consumed by both `Pages/Auth/Login.vue` and the landing page's
  inline card. `PasswordField` moved to `Components/Auth/` (three forms use it
  now); the landing's form gains the password show/hide toggle and a required
  email field, and its checkbox focus-ring offset was corrected to
  `ring-offset-raised` (the landing card sits on `bg-raised`).
- **Demo seeder** (#102) — `database/seeders/DemoSeeder.php` seeds every domain
  table (users, teams, memberships, invitations, projects, project members,
  tasks in every status, comments, attachments on the private disk, the
  activity trail they generate and the mention notifications behind them). It
  goes through the real Actions with `Auth::login()` per actor, so the
  observers stamp real actors and the data obeys the domain rules.

### Fixed

- **The projects list was not navigable** (#99) — each row was a plain `<li>`
  with no link, so from `/projects` there was no way to reach a project's board,
  tasks, people or activity trail.
- **Every view stopped scaling on a large screen** (#99) — each authenticated
  page capped itself with its own `max-w-*` while the shell imposed no cap, so
  the wider the screen the worse the ratio (the projects list filled 57% of a
  1920 px viewport and 41% of a 2560 px one). The shell now owns the padding
  and the pages dropped their own `px-6 py-10` and their caps; only the
  single-column forms keep a narrow reading column.
- **The docker MCP server never started** (#109) — `opencode.json` launched
  `npx -y mcp-docker`, a third-party package that declares no `bin` field and
  imports modules it does not depend on. It now runs the official Docker MCP
  Toolkit gateway that ships with the local Docker CLI
  (`docker mcp gateway run`).
- **The GitHub MCP server never connected** (#111) — the credential was read
  from `{env:GITHUB_TOKEN}`, a variable the OpenCode background service does
  not inherit, so the header went out as `Authorization: Bearer ` (empty
  credential) and GitHub answered `400 Authorization header is badly
  formatted`. The credential is now read from a **file**
  (`{file:~/.config/opencode/github-token}`), which survives a service restart
  or a reboot, and the `mcp` block was migrated to the current `mcp.servers`
  shape — which also fixed the `mysql` server, whose env block was keyed `env`
  instead of `environment`, so the MySQL credentials never reached the child
  process.

### Changed

- **Issues and pull requests are assigned to the maintainer** (#105, #107) —
  `AGENTS.md` records the rule for both, and the whole history was backfilled
  in one pass: 37 issues and 57 pull requests, zero left unassigned.
- **The local lint set now equals the CI lint set** (#101) — `make lint` also
  runs `make analyse` (PHPStan/Larastan), so the 12 static-analysis errors that
  only CI caught cannot slip through locally again.

## [1.1.1] - 2026-10-16

Maintenance release: the authenticated application shell is finally wired up.

### Fixed

- **Signed-in pages had no app chrome** (#93) — `AppLayout` was dead code: no
  page mounted it, so every authenticated view (dashboard, projects, tasks,
  teams, profile) rendered without the sidebar, the header/avatar, the sign-out
  control and the footer. The layout is now applied to every page except the
  guest frames (`Welcome`, `Auth/*`), so the shell is global by default.
- **No way to sign out** (#93) — the sidebar footer gained a *Sign out* control
  (posts to `/logout`) for signed-in users, and the shared landing footer is
  rendered after the main content.
- **Footer floated mid-viewport on short pages** (#93) — the shell root became a
  full-height flex column (`flex min-h-dvh flex-col`, `flex-1` on the main
  region) so the footer sits at the bottom.
- **Shell footer was oversized** (#93) — the shared `LandingFooter` kept the
  landing's `py-16` padding inside the app shell (~176px tall) and rendered a
  stray inner divider. Padding is now `py-16` with the guest calls to action and
  `py-5` without them, and the divider only renders with the CTAs (shell footer
  is 61px). The landing is unchanged.

### Changed

- **Shared footer brand lockup removed** (#93) — `LandingFooter` no longer
  repeats the logo + "TaskFlow" wordmark, which duplicated the sidebar logo on
  every authenticated page. The guest calls to action keep their own row.

## [1.1.0] - 2026-10-15

A public landing page on `/` and a project relicense.

### Added

- **Public landing page** (#90) — the home view now explains what TaskFlow is
  and lets a visitor sign in without leaving the page: `Components/Landing/*`
  (nav, hero, inline sign-in form, features, steps, footer), a `Button` link
  mode (`href` → Inertia `Link`, `#anchor` → plain `<a>`), and a redirect that
  sends authenticated visitors on to `/dashboard`.
- **Author credit and license link** in the landing footer, plus the TaskFlow
  site icons (`favicon.svg`, 32×32 `favicon.ico`, `apple-touch-icon.png`),
  declared in `app.blade.php`.

### Changed

- **License** — the project is now released under **CC BY-NC-ND 4.0** (was
  MIT). `LICENSE`, `composer.json`, `package.json` and the README were updated.

## [1.0.1] - 2026-10-14

Maintenance release: post-1.0.0 bug fixes.

### Fixed

- **Transactional mail never reached Mailpit** (#87) — the local stack ran no
  queue worker, so every `ShouldQueue` notification (email verification,
  password reset, team invitations, mentions) piled up in Redis and was never
  delivered. A supervised `queue:work redis` programme now runs inside the `app`
  container, so `make up` alone delivers mail. Covered by new delivery tests
  (`tests/Feature/MailDeliveryTest.php`).
- **The local test suite ran against the dev MySQL database** — the container
  environment shadowed the `phpunit.xml` testing values, so `RefreshDatabase`
  wiped the local database. The `<env>` entries are now authoritative and the
  suite is hermetic (sqlite / sync / array), on host and in Docker.
- **Broken navigation links** (#79, #86) — `/tasks` and `/settings` now redirect
  to their real destinations instead of returning 404.
- **Accessibility** (#80, #85) — the filtered task count is announced through a
  polite live region.

## [1.0.0] - 2026-09-28

The first feature-complete release: the full project/task management product
with teams, projects, task boards, collaboration, audit trail, accessibility and
a production container image. Delivered through 25 issues implemented in 10
parallel agent rounds on `develop`, all green on 6 CI checks.

### Added

**Domain**

- **Projects** (#11) — entity with slug + lifecycle (archive), members and
  per-project roles, authorization policy, CRUD and an Inertia UI.
- **Tasks** (#12, #20, #31) — task entity with status/priority enums, ordering,
  assignment; project board and list views sharing filters, search, pagination
  (`TaskQueryService`), native HTML5 drag & drop with a keyboard/select
  fallback, and Inertia optimistic status changes with rollback.
- **Teams & membership** (#7) — `Team`/`TeamMember` models with owner/admin/member
  roles, team settings page (#30) with inline role changes, invitation
  resend/revoke and an audit trail for every membership event.
- **Team invitations** (#8) — invite by email, queued acceptance mail with a
  one-time token, accept/revoke flows, no auto-created accounts (security first).
- **Comments & mentions** (#13) — threaded comments per task with an
  `@handle` mention parser and a queued `YouWereMentioned` notification.
- **File attachments** (#14) — attachments per task on a private disk, served
  through an authorized endpoint, content-based MIME allow-list, 5 MB cap,
  policy (members read, uploader/owner/admin delete), cross-task 404s.
- **ActivityLog audit trail** (#15, #32) — observers record immutable domain
  events; the project page renders a day-grouped activity timeline fed by a
  reporting service (actor, action label, live target link, independent
  pagination).
- **User profile & settings** (#9) — profile edit, avatar on a private disk,
  password change, and email change with Laravel's re-verification flow.

**Platform**

- **Authentication** (#6, #29) — register/login/logout, verified email,
  password reset via queued mail, throttled resend, polished token-driven Inertia
  pages with accessible form states.
- **Application shell** (#16) — responsive sidebar + topbar, mobile drawer,
  toasts, Inertia progress bar.
- **KPI dashboard** (#19) — signed-in landing page with KPI cards, per-status
  breakdown and completion charts (rendered from props, no chart library),
  recent projects, open/overdue tasks and quick actions.
- **Design system** (#17, #18) — reusable form/feedback/modal/skeleton
  components and an OKLCH design-token system with dark mode persisted per user.
- **Empty states & onboarding** (#33) — reusable empty states, per-page skeleton
  loaders and a first-run checklist (team → invite → project → first task), plus
  the missing project-create modal.

**Infrastructure & quality**

- **Production image** (#2, P0) — multi-stage `Dockerfile.prod` (frontend,
  PHP extensions, vendor, runtime) building hermetically from a clean checkout;
  nginx + php-fpm over a unix socket, no state and no secrets in the image.
- **Queue worker** (#27) — Redis-backed worker for queued mail, documented in
  the architecture.
- **Testing** — Pest backend with an enforced **80% coverage gate**, 12-process
  parallel test runs (`~4 s` full suite), architecture layering tests (#26),
  460+ Vitest specs, Playwright e2e for critical journeys.

### Changed

- Task board/list props are a paginator (`tasks.data.*`) after #31.
- Coverage is reported to Codacy via clover (#4).
- README documents features, badges and quick start (#5).

### Fixed

- **Accessibility pass** (#34) — skip link, focusable main, focus management in
  the mobile drawer and modals, focus rings, `aria-describedby` on all form
  fields, live regions that exist before content is inserted, and WCAG
  contrast corrections to design tokens (measured per surface in light and dark).
- Pint/PHPStan regressions introduced by later rounds were fixed on `develop`
  as they appeared, keeping every PR's lint gate green.

### Known issues

- Two navigation links (`/tasks`, `/settings`) return 404 — tracked in #79.
- A second accessibility pass for the pages changed by #33 is tracked in #80.

### Deferred to v1.1.0

- Cloud deployment slice: Cloud Run service, managed MySQL/Redis, custom domain
  and deployment docs (#21–#24). The IaC and runbooks are preserved on the
  `feature/21-cloud-run` and `feature/22-managed-data` branches, unmerged and
  unverified against a real GCP project.

[Unreleased]: https://github.com/bianquiviri/taskflow/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/bianquiviri/taskflow/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/bianquiviri/taskflow/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/bianquiviri/taskflow/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/bianquiviri/taskflow/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/bianquiviri/taskflow/compare/v0.2.0...v1.0.0
