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

## 2026-09-28 — Round 9: activity feed timeline + production image (P0 #2)

**Context:** The two P2/P0 leftovers that touch no shared frontend surface ran
in parallel: #32 (activity feed on the project page — read-path only) and #2
(production Dockerfile, P0, the last prerequisite for the cloud deploy slice
#21–#23 and release QA #25). PRs #74 and #75.

**Changes:**

- **#32 — Activity feed timeline** (`feature/32-activity-feed`, PR #74):
  `ActivityFeedService` composes the feed (project entries ∪ its tasks' entries
  via SQL, eager `actor`/`subject`, newest-first, 10/page on its own
  `activity_page` param so it paginates independently of the task board);
  `through()` maps each `ActivityLog` to `{id, event, actor{id,name}, label,
  target{type,id,title,url}, icon, at, day}` — the service owns all phrasing
  (incl. the status-change destination from `meta.to`), so no event→copy map
  lives in the frontend. `Components/Project/ActivityTimeline.vue` groups by a
  **server-computed** `day` key (no browser-timezone drift, no "Today" bug when
  a project is quiet), links the live target title, has its own pager and an
  `EmptyState`. Writes untouched (Observers / `LogActivityAction`). Prop name
  kept as `activity` so #15's tests still pass. 11 Pest (shape, task
  composition, cross-project scoping, departed actor, deleted task,
  pagination, authorization guest/outsider/member) · 7 Vitest (342 Pest total).
  **Known behaviour:** entries of a *deleted* task drop out of the feed (the
  feed resolves task subjects through the live `tasks` table, the only reliable
  project link without changing the writers). Documented, covered by a test.
- **#2 — Production Dockerfile** (`feature/2-prod-dockerfile`, PR #75, P0):
  `Dockerfile.prod` with 4 stages — `frontend` (node → Vite build),
  `php-extensions` (pdo_mysql/intl/zip/bcmath/opcache/redis; toolchain
  dropped), `vendor` (composer `--no-dev --optimize-autoloader`), `runtime`
  (`php:8.4-fpm-alpine` + nginx). **Hermetic build**: proven from a genuinely
  clean checkout (`git archive HEAD` → no `vendor`, no `node_modules`, no
  `.env`, no `public/build`) — image **46.3 MiB content** (208 MB on disk), 10
  RUN layers. Runtime: nginx serves `public/` and talks to php-fpm over the unix
  socket `/run/php-fpm.sock` (no port between them, only `:80` exposed); the
  entrypoint builds the framework caches on boot and supervises both processes
  (neither exists at build time), so the image carries no secrets and no state —
  everything is env-injected. Config in `docker/prod/` (`nginx.conf`,
  `php-fpm.conf`, `php.ini`, `entrypoint.sh`), deliberately diverging from the
  dev stack. `.dockerignore` added (keeps source, drops vendor/node_modules/
  tests/docs/certs/.env/artefacts). Documented in ARCHITECTURE.md § Production
  image. New **6th CI check** (`docker build` + smoke test: `/up` → 200,
  `/login` → 200, `Zend OPcache` loaded), which is the regression gate for this
  file. Agent-side defects it fixed: `extension_loaded("opcache")` is always
  false (it registers as `Zend OPcache`) — the CI assert would have failed on
  a good image; missing EOF newline in `ci.yml`.
- **Scaffolder YAML fix (the blocker):** the new CI job shipped a plain
  `run:` step whose value contained `virtual size: {{.Size}} bytes` — the `: `
  (colon+space) makes YAML parse it as a mapping, so **the whole workflow file
  failed to parse**; GitHub then reported `completed/failure` runs with *no log
  at all* and no check-runs on the PR. Fixed by making that step a block
  scalar (`run: |`). **Lesson (hard-won):** always YAML-parse a workflow after
  editing it (`ruby -e 'require "yaml"; YAML.load_file(".github/workflows/ci.yml")'`,
  or `actionlint`); a parse error in one job silently kills the whole CI for
  the branch, and `gh pr checks` reporting "no checks" is the tell. The run
  was not a flake and a re-push did not help; the parse was the only cause.

**Decisions:**

- #32 vs #2 was the chosen parallel pair because they are provably disjoint
  (PHP/Vue read-path vs Docker+CI+docs). #33/#34 (both frontend) cannot run
  together — they touch the same pages/components — and #33 collides with #32
  on `Pages/Projects/Show.vue`, hence one at a time.
- The activity feed is its own paginator (`activity_page`) instead of sharing
  the task board's `page` param: independent scrolling, and no prop-shape
  coupling between the board (paginator from #31/#20) and the timeline.
- Day labels are absolute (`Mon 28 Sept 2026`), never relative ("Today"): the
  grouping key is computed server-side, so a relative label would be wrong in
  every timezone the viewer is not in. Deliberate trade for correctness.
- The Dockerfile runs as **root** in the container (nginx `:80` + php-fpm
  master need it) — standard for this layout, noted as a hardening candidate
  for the Cloud Run phase. Cloud Run's `PORT` is not wired yet; out of scope.
- No unit tests ship with the Dockerfile (a Dockerfile has no Pest/Vitest
  target); the `docker build` + smoke CI job is the gate instead.

**Verification:** PRs #74/#75 5–6/6 green; `develop` post-merge CI green
(`Production Image (Dockerfile.prod)` included). Local: Pint 200 files, PHPStan
L5 baseline untouched, Pest 342 (2046 assertions), Vitest 54 files / 406
(96.7% stmt, `ActivityTimeline` 100%), ESLint 0, docker build 46.3 MiB +
`/up / /login` 200.

**Status (end of session):** `develop` `bef4540` — shipped this round #32
and #2; session total: #11/#16/#27/#7/#12/#6/#17/#13/#15/#8/#18/#28/#31/#5/#3/
#4/#30/#20/#26/#9/#14/#29/#19/#32/#2 (+ #10 closed). `main` still v0.2.0 — the
next release carries all of it. Remaining: #25 (release v1.0.0 QA, P3),
#33/#34 (frontend polish, P2 — sequential), #21/#22/#23 (Cloud Run, managed
MySQL/Redis, custom domain — P2, unblocked by #2), #24 (deploy docs, after
#21–#23). Next session: deploy slice #21–#23, then #33/#34, then #25.

---

## 2026-09-28 — Round 8: auth pages UI + KPI dashboard (+ Pint hotfix)

**Context:** One more agent round landed on `develop` (PRs #70–#72). Auth flow
got a real UI, the signed-in home became a metrics dashboard, and a pre-existing
Pint failure was fixed so every subsequent PR's lint gate is green again.

**Changes:**

- **#29 — Auth pages UI** (`feature/29-auth-pages`, agent's PR #71): all five
  `Pages/Auth/*` rebuilt on #18 tokens + #17 components (`FormInput`, `Button`,
  `Icon`, `AuthLayout`); flows/routes untouched. New `AuthStatusMessage`
  (maps the server flash *keys* — e.g. `password-reset-link-sent` — to copy;
  previously the raw key was rendered to the visitor) + `PasswordField`
  (show/hide toggle `aria-pressed` under the field to keep `FormInput`
  untouched; reveal on confirm fields too; inputs disabled while processing).
  `AuthLayout.spec.js` added; `AuthPages.spec.js` 6 → 20 tests (reactive mock
  makes pending state testable). `tests/e2e/auth.spec.js`: 5 guest journeys
  (render/validation/reveal — submits stay in Pest because CI e2e runs on an
  unmigrated DB). 33 Vitest · 322 Pest. **Fix after CI:** `getByLabel` without
  `{ exact: true }` matched both password fields (substring) → strict mode
  violation; one-line exact-match fix.
- **#19 — KPI dashboard** (`feature/19-dashboard`, PR #72): `DashboardMetricsService`
  (5-query read model: active projects, per-status grouped counts, overdue,
  recent 5 projects with `withCount`, 8 open tasks via joined raw rows to avoid
  N+1) + invokable `DashboardController` (authorizes `viewAny`, renders
  `Pages/Dashboard/Index.vue` with `kpis`/`statusBreakdown`/`recentProjects`/
  `openTasks`) + `GET /dashboard` in the existing `auth` group. Components
  `Dashboard/{KpiCards,StatusBreakdownChart,CompletionDonut,RecentProjects,
  OpenTaskList,QuickActions}` — charts from props with NO charting library
  (bar width vs busiest status, `stroke-dasharray` ring, both `aria-hidden`
  — numbers carry meaning); empty states for new users; `Project::tasks()`
  relation added (was missing); Dashboard link added to `AppLayout` nav.
  `due today = on time` (tested). 9 Pest · 33 Vitest (dashboard components
  100% stmt).
- **#70 — Pint hotfix** (bugfix, merged first — `12cfbff`): `develop` WAS RED on
  the `lint-php` job since #26 (`ad1e764`): unused imports `ActivityLog`,`Task`
  + missing EOF newline in `tests/Architecture/ArchitectureTest.php`. Both
  round-8 agents hit it; fixed via a dedicated branch so every PR's lint gate
  (which lints the whole tree) is green again. Dashboard's own `style(tests)`
  commit duplicated the fix → trivial merge conflict during series-squash,
  resolved taking `develop`'s version.
- **Integrations:** #71 then #72 squashed in series (after #71, #72 conflicted
  only on the ArchitectureTest file); post-merge push runs the full CI on
  `develop`.

**Decisions:**

- Dev workflow confirmed: every agent's PR lints the whole tree with Pint, so a
  single style issue on `develop` blocks ALL PR lint gates → fix such issues on
  `develop` immediately (dedicated bugfix PR) rather than letting agents carry
  the fix and collide.
- Dashboard quick actions navigate (`/projects`, `/tasks/mine`) instead of
  POSTing — there is no standalone create route, and the issue forbade role
  logic; a one-line change if dedicated create pages land later.
- E2E convenience spec asserts only render/validation; persistence stays in
  Pest (CI e2e web server uses unmigrated in-memory SQLite).

**Verification:** `develop` `b18e165` — PRs #71/#72 5/5 green (Pint · PHPStan
· Pest ≥80% · Vitest+ESLint · Playwright); post-merge develop run green.
Local: Pest 331 · 1968 assertions (12 processes), Vitest 366 (50 files, 96.3%
stmt, dashboard components + auth pages 100%), Pint 195 files PASS, PHPStan
level 5 baseline untouched.

**Status (end of session):** `develop` `b18e165` — the session shipped
#11/#16/#27/#7/#12/#6/#17/#13/#15/#8/#18/#28/#31/#5/#3/#4/#30/#20/#26/#9/#14
/#29/#19 (+ #10 closed); `main` still v0.2.0 — the next release carries all of
it. Remaining backlog: #2 (prod Dockerfile, P0), #25 (release v1.0.0 QA, P3),
#32/#33/#34 (P2s), #21/#22/#23/#24 (deploy, P2). Next session: #2 or deploy
slice, then #32/#33/#34 polish pass before #25.

---

## 2026-09-28 — Parallel rounds 6-7 + parallel testing (#30/#20/#9/#14/#26)

**Context:** Two more agent rounds and a scaffolder round landed on `develop`
(PRs #63–#68). Every merged feature now carries personality: team management,
an optimistic task board, profile settings, and file attachments.

**Changes:**

- **#30 — Team settings page** (`feature/30-team-settings`, merged via agent's
  own PR #63; scrapped duplicate #64): `Pages/Teams/Settings.vue` (members with
  inline role select, invitations with resend/cancel, status badges),
  `ChangeTeamMemberRoleAction` (audits `team.member_role_changed`) +
  `PATCH /teams/{team}/members/{membership}`, `ResendInvitationAction` (token
  rotation, keeps one-pending-per-address) + `POST …/resend` (audits
  `team.invitation_resent`), `TeamPolicy` additive (`updateMemberRole`,
  `resendInvitation`; owner seat unreachable from policy AND request),
  `TeamRedirector` (referer exact-match, no open redirect),
  `GET /teams/{team}/settings`, per-row `can*` flags server-side.
  **Lesson:** instruct agents again that a PR may already exist for their
  branch before creating one (the agent opened #63, scaffolder's #64 was a
  duplicate).
- **#20 — Project/task pages with optimistic UI** (`feature/20-project-pages`,
  PR #65): `ProjectOverviewService` (progress, people, per-task can flags),
  task board (columns per `TaskStatus`, one `view=board|list` param, both share
  `TaskQueryService` filters + paginator), native HTML5 drag&drop with select
  fallback, optimistic status changes via Inertia `optimistic` callback,
  `TaskFormModal` create/edit, `TaskStatuses` sharing `{value,label,allows}`
  from `TaskStatus::canTransitionTo` (done can reopen). 56 Vitest · 29 Pest.
- **#26 — Parallel testing + architecture tests** (`feature/26-parallel-testing`,
  PR #66, scaffolder): `make test` → `--ci --no-coverage --parallel` (Pest 4
  native, 12 processes; full suite 266 tests in ~4 s, was ~60 s serial);
  `tests/Architecture/ArchitectureTest.php` — 6 layering rules (enums native,
  strict types, Actions/Services/Models out of HTTP layer, Observers/Policies
  never touch controllers); README documents `--filter` for changed files.
  CI backend keeps serial + `--coverage --min=80` (clover aggregation with
  parallel would complicate the Codacy upload).
- **#9 — User profile and account settings** (`feature/9-profile-settings`,
  PR #67): `Pages/Profile/{Edit,Password}.vue`, avatar on a private local disk
  served via `GET /profile/avatar` (no GD resize: extension absent — validate
  mime/size), email change = Laravel re-verification pattern (applied
  immediately, `email_verified_at` nulled, queued `VerifyEmail`; routes in an
  `auth.unverified` group so a mistyped address stays correctable),
  `persistTheme()` shared by `ThemeToggle` + profile `ThemeChoice`,
  `UpdateUserProfile/PasswordAction`, avatar added to shared `auth.user`
  (additive in `AuthContext`). 27 Pest · 37 Vitest. Migrations coordinated
  between the parallel agents (`2026_09_28_0000xx` prefix split).
- **#14 — File attachments on tasks** (`feature/14-task-attachments`, PR #68):
  `TaskFile` + private `attachments` disk (`serve => false`, never symlinked),
  `StoreTaskFileRequest` with content-based mime allow-list + 5MB cap,
  `AttachmentRules` single source of truth, `Store/DeleteTaskFileAction`,
  `TaskFilePolicy` (members view/download/create; uploader or owner/admin
  delete; `scopeBindings` → cross-task file 404), streamed downloads that never
  leak storage paths, `TaskAttachmentList/Form` in `Tasks/Show.vue`. 2 Pest
  files · 29 Vitest.

**Verification (develop `ad1e764`):** CI green on every PR — Pint, PHPStan
(level 5), Pest (295 · 1695 assertions), Vitest+ESLint (41 files · 319, 95%
stmt), Playwright; local suites green with `--order-by=random` and parallel.

**Decisions:**

- Two agent rounds per session is the sustainable throughput (3 aborted
  earlier); rounds are now domain pairing: teams+pages, then profile+
  attachments. Each round: seed fetch → 2 agents → PRs in parallel → single CI
  watch → squash in series (routes/web.php conflicts resolved with one-line
  use-join commits when they occur).
- Role/status UI derives options from enums (single source of truth) instead
  of bespoke frontend lists; policy answers per-row `can*` flags so pages hold
  no authorization rules (consistent with #28).
- Optimistic updates rely on Inertia's own `optimistic` callback (baseline
  restored on refusal) rather than a hand-rolled rollback.

**Status (end of session):** `develop` `ad1e764` — #30/#20/#26/#9/#14 in (this
session: #13/#15/#8/#18/#28/#31/#5/#3/#4/#30/#20/#26/#9/#14, #10 closed).
CI gates: Pint, PHPStan (level 5), Pest (≥80%, parallel local), Vitest+ESLint,
Playwright. `main` still v0.2.0 — the next release carries all of these. Next:
#29 auth pages UI, #19 dashboard KPIs, #2 production Dockerfile, #25 release
v1.0.0 QA.

---

## 2026-09-26 — Parallel rounds 3-4: comments, audit trail, invitations, dark mode

**Context:** Two more parallel rounds were integrated into `develop` (PRs
#52–#55). A "seed" template optimisation removed per-agent installs.

**Changes:**

- **Speed optimisation (applied):** a seed clone at
  `/var/folders/.../opencode/taskflow-seed` (develop + vendor + node_modules +
  .env + assets) is copied with `cp -c` (APFS reflink) by each new agent, so
  composer/npm installs disappear from agent runs entirely. Each round now
  works: seed fetch → agent pairs → PRs in parallel → single CI watch →
  squash in series.
- **#13 — Comments & mentions** (`feature/13-comments`, PR #52):
  `Comment` model + migration + factory, `CommentPolicy` (author can
  update/delete own; project members view/create), `AddCommentAction` +
  `App\Support\MentionParser` (`@handle` = slug of display name, project-scoped
  candidates), `YouWereMentioned` (ShouldQueue, database+mail), `CommentController`
  + `/tasks/{task}/comments` routes, `comments` prop on `TaskController@show`,
  `CommentList`/`CommentForm` in `Pages/Tasks/Show.vue`. Added the standard
  `notifications` table (was missing). 25 new Pest · 11 new Vitest.
- **#15 — ActivityLog audit trail** (`feature/15-audit-trail`, PR #53):
  `ActivityLog` (actor nullable, event enum, morph subject, meta json) +
  migration, `TaskObserver`/`ProjectObserver` registered in AppServiceProvider,
  `LogActivityAction` (single writer), `ActivityLogService` (paginated),
  `activity` prop on `ProjectController@show`, activity section in
  `Pages/Projects/Show.vue`; `Relation::morphMap(['project','task'])` in
  `bootstrap/app.php`. 12 new Pest · 5 new Vitest. Migrations coordinated
  between parallel agents (`000000/000001` comments/notifications, `000002`
  activity_logs) — agents were told each other's timestamp prefixes.
- **#8 — Team invitations flow** (`feature/8-team-invitations`, PR #54):
  `InviteTeamMemberAction`/`AcceptInvitationAction`/`RevokeInvitationAction`/
  `RemoveTeamMemberAction`, `YouWereInvited` queued mail with accept link,
  one-time token self-consuming on accept (valid/expired/revoked/email-mismatch
  handled; NO auto-accounts — accept requires signed-in verified user with
  matching email), audit events `team.member_*` via ActivityLog, `role` added
  to `team_invitations` (invitable set member/admin, owner seat protected),
  `TeamPolicy` +`view`/`removeMember`, `scopeBindings` for cross-team 404,
  `Pages/Teams/Show.vue` + `Invitation.vue`. 43 new Pest · 18 new Vitest.
- **#18 — Design tokens + dark mode** (`feature/18-dark-mode`, PR #55):
  `@theme inline` token system in `app.css` (brand/surfaces/lines/content/
  status/shape-spacing, oklch, all theme-flipping) + class-strategy dark
  variant (`:where(.dark,.dark *)` verified in compiled CSS); user-persisted
  theme (`Theme` enum, `users.theme` migration `000010`, `PATCH /theme`,
  `UpdateUserThemeAction`), `theme` shared prop + server-side `dark` class in
  `app.blade.php` (no FOUC), `ThemeToggle` in AppLayout, shared components
  converted to tokens (no stray hex). 129 Pest · 136 Vitest (94% stmt) ·
  `npm run build` emits dark rules.
- **Integration fix:** #54 and #55 both touched `routes/web.php` (auth group)
  → squash of #55 conflicted; resolved by merging `develop` into the branch and
  joining the `use` statements (one-line commit `3a970ee`), CI re-ran green.

**Decisions:**

- Seed copy (`cp -c`) over Docker cache volumes: simpler, no per-agent install
  at all; trade-off is the seed must be re-fetched to `origin/develop` before
  each round (cheap).
- Two agents per round (three aborted mid-run earlier due to tool limits);
  runner count: domain+domain, then auth+frontend.
- Non-registered emails are NOT auto-created on invitation accept (security +
  KISS); acceptance only matches a signed-in verified account's email.
- Theme persisted server-side (users.theme) so the boot value is the truth and
  first paint has no flash; localStorage was rejected as the single source.

**Verification run (develop `74ebc37`):** CI green on all four PRs — Pint,
Pest (163), Vitest+ESLint (136, 94% stmt), Playwright — plus local agent runs
with `--order-by=random` stability checks on #15.

**Status:** `develop` green at `74ebc37` with #13/#15/#8/#18. Next: #9 profile
and settings, then #14/#15-adjacent domain work, #20 pages, #26 parallel
testing, close/review #10 (ProjectMember largely covered by #11).

**Follow-up (round 5 + scaffolder, same session; PRs #57–#61):**

- **#28 — Shared auth props** (`feature/28-shared-auth-props`, PR #57):
  `App\Support\AuthContext` (memoized per user) shares `auth.user`
  (id/name/email) + `auth.team` (earliest membership) + a `can` allow-list
  resolved via `Gate::forUser`; guests get zero queries. `useCan` composable
  (list/map/ref) + AppLayout team nav gated on `teams.view`. 12 Pest · 14
  Vitest.
- **#31 — Task filtering/search/pagination** (`feature/31-task-filters`,
  PR #58): `TaskQuery` (chainable, immutable) + `TaskQueryService`
  (forProject/forAssignee, 15/page, `withQueryString` → shareable URLs),
  filters status/priority/assignee/due/search (+combos, 51 Pest),
  `TaskFilterRequest` replacing `TaskIndexRequest`, `MyTaskController`
  `GET /tasks/mine` (before `{task}` binding), `TaskFilterForm`/`TaskList`/
  `TaskPagination` + `Pages/Tasks/{Mine,Index}.vue`. Note: board prop shape
  changed to paginator (`tasks.data.*`).
- **#5 — README** (PR #59): CI/release/license badges, feature overview.
- **#3 — PHPStan tooling** (PR #60): dev deps larastan ^3.12 + ide-helper
  ^3.7, `phpstan.neon` level 5 + baseline (59 findings), new `phpstan` CI job
  (5th check).
- **#4 — Coverage gate** (PR #61): backend job now runs
  `--coverage --min=80 --coverage-clover` (CI fails below 80%; clover.xml
  finally feeds the existing Codacy upload). Local `make test` stays
  `--no-coverage`.
- **#10 — closed** as covered by #11 (ProjectMember/ProjectRole/ProjectPolicy
  + matrices in ProjectAuthorizationTest/TaskPolicyTest/CommentPolicyTest; #28
  derives frontend permissions from policies). The "invite via MemberService"
  AC never existed as a service — project membership is created with the
  project; member invitations to existing projects would be a new issue.
- **Seed flow now:** `cp -c` seed → agents (zero composer/npm installs) →
  PRs in parallel → single CI watch → squash in series; conflicts so far only
  in `routes/web.php` and the workflow file, both resolved with one-commit
  joins.

**Status (end of session):** `develop` `699a04a` — #13/#15/#8/#18/#28/#31/
#5/#3/#4 in, #10 closed; CI gates: Pint, PHPStan (level 5+baseline), Pest
(≥80% coverage), Vitest+ESLint, Playwright. `main` still v0.2.0; next release
will carry this batch. Next: #9 profile/settings, #14 attachments, #20 pages,
#29 auth pages UI, #30 team settings page, #26 parallel testing.

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
- **Release v0.2.0** (PR #46 → `main` `ae9a80b`, tag + release notes; #47 sync
  back to `develop`).
- **#12 — Task entity** (`feature/12-task-entity`, PR #48): Task model + factory
  + migration (`position` ordering), `TaskStatus`/`TaskPriority` enums, filter
  scopes, Create/Update/Assign/ChangeTaskStatus actions, `TaskOrderService`,
  `TaskPolicy` matrix, `TaskController` + FormRequests + `/projects/{project}/
  tasks` routes, 18 feature tests (Pest 58 total).
- **#6 — Authentication** (`feature/6-authentication`, PR #49): native Laravel
  auth controllers + `routes/auth.php`, Inertia pages under `Pages/Auth`,
  `MustVerifyEmail` + verified gating on `/projects`, signed verification URLs,
  throttled resend, password reset via queued `ShouldQueue` notifications;
  unblocked the guest-gating tests deferred in #11.
- **#17 — Form components** (`feature/17-form-components`, PR #50):
  FormInput/Textarea/Select, Button, Badge, StatusPill, Modal (focus trap),
  EmptyState, Skeleton — all with v-model + error display; 96 Vitest specs
  (94% stmt).

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

**Status:** `develop` green with #11/#16/#27/#7/#12/#6/#17 (PRs #39–#50);
`main` at v0.2.0. Next: invitations #8, profile #9, #10 (ProjectMember — largely
covered by #11, verify/close), then #13/#14/#15 domain issues.

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