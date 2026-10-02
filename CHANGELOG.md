# Changelog

All notable changes to TaskFlow are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

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

[Unreleased]: https://github.com/bianquiviri/taskflow/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/bianquiviri/taskflow/compare/v0.2.0...v1.0.0