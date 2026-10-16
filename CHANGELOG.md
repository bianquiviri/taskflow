# Changelog

All notable changes to TaskFlow are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

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

[Unreleased]: https://github.com/bianquiviri/taskflow/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/bianquiviri/taskflow/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/bianquiviri/taskflow/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/bianquiviri/taskflow/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/bianquiviri/taskflow/compare/v0.2.0...v1.0.0
