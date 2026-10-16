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

Ad-hoc OpenCode sessions do not replace this log; use this file as the source
of truth for the project's evolution.

---

## 2026-10-16 — Round 15: the Projects list was not navigable (#99)

**Context:** The product owner asked for a review and fix of the **Projects
view**. The review turned up two problems, one of them a regression from #93.

**Problem 1 — you could not open a project (the real bug).** `Pages/Projects/
Index.vue` rendered each project as a plain `<li>` with the name and the
description and **no link**: the component did not even import `Link`. From
`/projects` there was literally no way to reach `/projects/{project}` — the
board, the tasks, the people and the activity trail of a project were
unreachable from the listing (the only entrances were the dashboard's "recent
projects" and the task pages).

**Problem 2 — a duplicated page container, systemic since #93.** `AppLayout`'s
`<main>` already provides `px-4 py-gutter sm:px-6 lg:px-8`, but every
authenticated page still carried its own `px-6 py-10` from before #93 (when the
shell was dead code and the page container was the *only* padding). Measured in
the browser at 390 px: text sits **40 px** from the edge on the un-fixed pages
(shell 16 px + page 24 px) against **16 px** where the page padding is dropped.
At wide viewports the effect is invisible — the page `max-w-*` binds and the
centring is symmetric, so the extra padding cancels out (measured: identical
`x` with and without it).

**Changes (branch `bugfix/99-projects-view-shell`, issue [#99]):**

- `Pages/Projects/Index.vue`: each row is now an Inertia `<Link>` to
  `/projects/{project.id}` (the id-based key the rest of the app already uses —
  no `getRouteKeyName` is defined, so binding is by primary key), wrapped in a
  card with a hover state; the container drops `px-6 py-10` and keeps
  `mx-auto w-full max-w-4xl`.
- `Pages/Projects/Show.vue`: the container drops `px-6 py-10` (keeps
  `max-w-5xl`).
- `Pages/Projects/Index.spec.js`: +1 (*opens the project from its row*, asserting
  both hrefs).

**Verification:** Pint 201 files · ESLint 0 · Pest **350** (2080 assertions) ·
Vitest **539** (67 files) · Playwright **14** — all green. Verified by hand in
the browser with a throwaway `qa.review@example.com` account (deleted
afterwards): clicking a project lands on `/projects/2`, the row measures
`max-w-4xl` (896 px) and the mobile margin is 16 px.

**Open decision (raised with the product owner):** the same duplicated padding
survives on **9 other pages** — `Tasks/{Index,Show,Mine}.vue` (domain-agent),
`Dashboard/Index.vue` (reporting-agent), `Profile/{Edit,Password}.vue` and
`Teams/{Show,Settings,Invitation}.vue` (auth-agent). Fixing only Projects makes
it *tighter* than its neighbours on small screens, so the options are: fix all
nine in this same PR (one `px-6 py-10` deletion per file, no spec asserts those
classes) or ship Projects alone and file a follow-up. Awaiting the decision.

**Status (end of session):** `main` = tag `v1.1.1`; `develop` `71c9710`; PR #98
(shared sign-in form, #97) still open. Branch `bugfix/99-projects-view-shell`
pushed with PR open against `develop`.

---

## 2026-10-15 — Round 13: v1.1.0 released + the app shell wired to pages (#93)

**Context:** Two pieces of work. First the release train for the landing page
(Round 12): `feature/90-landing-page` → PR **#91** → `develop`, then
`release/v1.1.0` (CHANGELOG `[1.1.0] - 2026-10-15`) → PR **#92** → `main`. Then
the product owner reviewed the signed-in product and reported that the dashboard
had **no way to sign out** and **no footer**, asking for the landing's footer on
the authenticated pages.

**Release (v1.1.0):**

- PR #92 hit a conflict on `CHANGELOG.md`: the `v1.0.1` release commits had
  **never been synced back to `develop`** (>the sync-back was skipped in Round
  11), so `main` and `develop` each carried one half of the history. Resolved by
  merging `main` into the release branch and joining the `[1.1.0]` + `[1.0.1]`
  blocks, rewriting the link refs.
- Merged to `main` (`5a6e607`) with `--merge --admin` (the required-review rule
  has `enforce_admins: false`), annotated tag `v1.1.0` + GitHub **Release**
  published as *Latest* (superseding `v1.0.0`), and this time `main` was
  **synced back into `develop`** (`50561c1`) so the recurring CHANGELOG conflict
  cannot happen again. Issues #90, #23, #24 and PRs #91/#92 assigned to the
  author.

**The bug (issue [#93]):** `resources/js/Layouts/AppLayout.vue` was **dead
code** — no page mounted it, anywhere in the repo's history. Every authenticated
page (dashboard, projects, tasks, teams, profile) therefore rendered **without
the sidebar, nav, header/avatar, sign-out control and footer**: the shell
existed, was tested, and was never wired up. Screenshots taken before/after
confirmed it in the browser (the `before` capture shows a bare page with no
chrome at all).

**Changes (branch `bugfix/93-dashboard-review` → PR #94):**

- `resources/js/app.js`: `resolve` is now async and applies
  `page.default.layout ??= AppLayout` to **every** page except the guest frames
  (`Welcome`, `Auth/*`), so the shell is global by default instead of opt-in.
- `Layouts/AppLayout.vue`: the sidebar footer gains a **Sign out** control
  (`<Link href="/logout" method="post" as="button">` + `Icon name="logout"`,
  `data-test="sign-out"`) rendered only for a signed-in user, and the shared
  `<LandingFooter :app-name="appName" :show-ctas="false" />` is rendered after
  `</main>`.
- `Components/Landing/LandingFooter.vue`: new **`showCtas`** prop (default
  `true`). With `false` the guest CTAs (*Sign in* / *Create an account*) are
  hidden and the logo + credit/license line remain — the landing itself is
  unchanged.
- Tests: `LandingFooter.spec.js` +2 (CTAs hidden/present), `AppLayout.spec.js`
  +3 (sign-out posts to `/logout`, footer present with `data-ctas="false"` and
  ordered **after** `main`). **534 Vitest · 350 Pest · 14 Playwright · Pint 201
  files · ESLint 0** — all green, CI 6/6 on PR #94.
- Verified by hand in the browser: `/dashboard` and `/projects` now render the
  skip link, the sidebar (`Main` nav, avatar, Sign out), the topbar and the
  `contentinfo` footer with the author/license line; clicking **Sign out** posts
  and lands on `/` (guest); `/login` still renders `AuthLayout` only, with no
  app chrome.

**Decisions:**

- Of the three layout options offered for the authenticated footer, **option 1**
  was implemented: reuse `LandingFooter` adapted via `showCtas=false`, applied
  **shell-wide** (every authenticated page gets it), rather than duplicating a
  second footer component or inlining it per page.
- The running **version is deliberately not shown in-app**: `version` is not a
  shared Inertia prop (`HandleInertiaRequests` exposes `auth`/`can`/`theme`
  only), and adding it there is `auth-agent` territory outside this issue.
- The shell is nominally `frontend-agent` territory; it was modified here on an
  **explicit product-owner request** and the override is recorded in PR #94, per
  the parallel-development rules.
- Test-side: the "footer comes after `main`" assertion uses element order from
  `findAll('main, [data-test="app-footer"]')` instead of
  `compareDocumentPosition` + the `Node` global, which ESLint's config does not
  declare (`no-undef`). Caught by `make lint`.
- Housekeeping: the throwaway dev account `qa.review@example.com` used for the
  browser check was deleted from the dev database, and the two screenshot
  artefacts were removed from the working tree before committing.

**Follow-ups (same session, pushed to PR #94):**

- **Footer pinned to the viewport bottom:** the shell root was only
  `min-h-dvh`, so on short pages (e.g. an empty dashboard) the footer floated
  mid-viewport instead of sitting at the bottom. Root became
  `flex min-h-dvh flex-col` with `flex-1` on the middle wrapper and `<main>`;
  `AppLayout.spec.js` +1 asserts the stacking.
- **Brand lockup removed from the shared footer** (product-owner request on the
  dashboard review): `LandingFooter` no longer renders the logo-badge + "TaskFlow"
  link to `/` (it duplicated the sidebar logo on every authenticated page). The
  CTAs now live in their own `v-if="showCtas"` row (`sm:justify-end`, `mb-10`),
  so the app-shell footer collapses to just the bottom line (tagline, author,
  license, version) with no empty row. Spec +1 (*does not repeat the brand
  lockup*), one test renamed. The tagline "TaskFlow — project & task management
  for teams." was deliberately **kept** — the request covered the button/wordmark,
  not the descriptive line.
- **App-shell footer height reduced** (product-owner request on the dashboard
  review): the shared footer kept the landing's `py-16` even in the shell, so
  the two-line bar measured ~176px tall; the inner `border-t pt-6` divider also
  rendered as a stray second rule once the guest CTAs were gone. Padding is now
  `showCtas ? 'py-16' : 'py-5'` and the divider is applied only with the CTAs,
  dropping the shell footer to **61px**. The landing is unchanged (verified in
  the browser: `/` still 64px padding + CTAs, `/dashboard` 20px, no divider);
  `LandingFooter.spec.js` +2 (compact padding, no inner divider).

**Known flake (pre-existing, untouched):** `ActivityFeedTest` — *describes an
entry with its actor, its action, its target and when it happened* compares two
`now()` calls a few ms apart.

**Release v1.1.1 (same session):** PR #94 was reviewed and merged into
`develop` (`3f85ba4`), then released. Because the whole `#93` work sat only on
`develop` (the v1.1.0 release branch had left `main` behind it), the delta
`main..develop` was released as a **patch**: branch `release/v1.1.1` from
`develop`, `CHANGELOG.md` gained the `[1.1.1] - 2026-10-16` section (Fixed: no
app chrome, no sign-out, floating footer, oversized footer; Changed: brand
lockup removed) and the link refs were rewired, PR **#95** → `main` merged
(`d1fdb3b`, `--merge --admin`; the review rule has `enforce_admins: false`).
Annotated tag `v1.1.1` pushed and the GitHub **Release** published as *Latest*
(notes single-sourced from the CHANGELOG section). `main` was then **synced back
into `develop`** as a clean fast-forward (`3f85ba4..d1fdb3b`, CHANGELOG-only) —
unlike the round-11 sync-back, no conflict this time because `develop` had not
touched the CHANGELOG since the release branch point.

**Status (end of session):** `main` = `develop` = `d1fdb3b`, tag `v1.1.1` with a
published GitHub Release (Latest). CI green on PR #94 and #95 (6/6). Open
issues: **#23**, **#24** (deferred cloud slice). Next: the remaining backlog —
`frontend-agent` follow-ups (shared sign-in form for the landing) and the cloud
phase (#23/#24).

---

## 2026-10-14 — Round 12: public landing page on `/` with inline sign-in (#90)

**Context:** The product owner asked for the home view (`GET /`) to become a real
landing page that explains what TaskFlow is and lets a visitor sign in without
leaving the page. The old `Welcome.vue` was a framework placeholder (app name +
Laravel version) with no way into the product.

**Changes (working tree on `feature/90-landing-page`, uncommitted):**

- **Issue [#90]** opened: *feat(landing): public landing page that explains
  TaskFlow and offers inline sign-in*.
- `Pages/Welcome.vue` rewritten as a landing composed of new
  `Components/Landing/*`: `LandingNav` (logo, `Sign in` anchor → `#signin`,
  `Get started` → `/register`), `LandingHero` (badge, the page's single `h1`,
  product pitch, CTAs), `LandingSignIn` (inline email/password/remember form
  posting to `POST /login`, forgot-password link, register link),
  `LandingFeatures` ("What is TaskFlow?" — six capabilities: projects,
  tasks & boards, teams & invitations, comments & mentions, activity trail,
  dashboard & metrics),   `LandingSteps` ("How it works" — three numbered steps),
  `LandingFooter` (logo, repeat CTAs, author credit, license, running version).
- **`Button` learned a link mode** (`href` → Inertia `Link`; a same-page
  `#anchor` → a plain `<a>`) so the landing's CTAs reuse the one source of truth
  for the button look instead of hand-rolled button classes. Backwards
  compatible (no `href` → `<button>` as before).
- **`WelcomeController`** now sends authenticated visitors to
  `route('dashboard')`, so the landing is guest-only.
- Tests: `Pages/Welcome.spec.js` rewritten (5) + six new landing specs (27
  tests total) · `tests/Feature/WelcomeTest.php` gains 2 tests (authenticated
  redirect; a failed sign-in attempt from `/` comes back to `/` with
  `email` errors) · `tests/e2e/welcome.spec.js` rewritten for the landing
  (title, `h1`, sections, inline form, nav anchor) + a footer credit/license
  check.
- **Author credit + project license** (follow-up request): `LandingFooter`
  gains "Developed by [Jose Antonio Bianco](https://github.com/bianquiviri)"
  (external link, `rel="noopener noreferrer"`, `target="_blank"`) and a link to
  the license (CC BY-NC-ND 4.0); the running version is now rendered only when
  present. The project license changed from **MIT** (Laravel skeleton default)
  to **CC BY-NC-ND 4.0**: `LICENSE` replaced (project header + the verbatim
  19 KB Creative Commons legalcode, downloaded from
  `creativecommons.org/licenses/by-nc-nd/4.0/legalcode.txt`), `composer.json`
  and `package.json` license fields set to `CC-BY-NC-ND-4.0`, README badge +
  § *License* rewritten.   `LandingFooter.spec.js` gains 2 tests (author link,
  license link).
- **Site icon (favicon)** (follow-up request): the browser tab had no icon at
  all — `public/favicon.ico` was a 0-byte placeholder from the scaffold. Added
  `public/favicon.svg` (the product mark: brand `#4f39f6` rounded badge + white
  check, i.e. `IconLogo` geometry scaled/centred), a real 32×32
  `public/favicon.ico` and a 180×180 `public/apple-touch-icon.png`, all
  declared in `app.blade.php` (`<link rel="icon">` + `apple-touch-icon`). The
  rasters were generated with a throwaway, dependency-free PHP script
  (4× supersampled rasteriser + hand-rolled PNG/ICO encoders) because neither
  GD nor ImageMagick is available locally; the script was not kept.
  `WelcomeTest` gains 2 tests (the head declares the icons; the files exist).


**Decisions:**

- The landing keeps its own sign-in form rather than refactoring
  `Pages/Auth/Login.vue` into a shared component: extracting `LoginForm` would
  mean moving `PasswordField`/`AuthStatusMessage` out of `Pages/Auth` and
  touching every auth page, and #90 explicitly scoped the auth flow untouched.
  Trade-off: the landing's password input is a plain `FormInput` (no show/hide
  toggle). A shared form is a follow-up if the toggle is wanted there too.
- Authenticated visitors are **redirected from `/` to `/dashboard`** even though
  #90 listed it as out of scope: a marketing page with a sign-in form makes no
  sense for a signed-in user, and `AppLayout`'s logo links to `/`.
- Page title is "Project & task management for teams" — the Inertia title
  template in `app.js` already appends "· TaskFlow", so the app name is not
  repeated.
- The landing is composed of small, stateless `Components/Landing/*` components
  and follows the #18 design tokens only (light **and** dark).
- Feature copy describes **shipped** capabilities (projects, boards/filters,
  invitations, comments/mentions, activity log, KPI dashboard) — no invented
  numbers or testimonials.
- **License decision (author's call):** the recommendation was a software-grade
  restrictive license (proprietary "All rights reserved", or PolyForm
  Strict/Elastic for source-available), because **Creative Commons advises
  against using its licenses for software** and CC BY-NC-ND 4.0 adds no patent
  grant while its "NoDerivatives" clause conflicts with ordinary code use
  (forking, patching). The author deliberately chose **CC BY-NC-ND 4.0** anyway
  as the most restrictive CC variant; recorded here so the choice is traceable
  rather than accidental. The `composer.json` SPDX field uses
  `CC-BY-NC-ND-4.0`; `package.json` gained the same `license` field.

**Known flake (pre-existing, not caused by this work):** `ActivityFeedTest` —
*describes an entry with its actor, its action, its target and when it
happened* — compares two `now()` calls made a few ms apart, so it fails when
the second ticks over between the write and the assertion (reproduced once under
`--parallel` at 12 processes; passes 3/3 in isolation). Candidate one-line fix:
assert against the created model's `created_at` instead of `now()`.

**Verification:** `make lint` green (Pint 201 files, ESLint 0) · `make test`
**350 passed** (2080 assertions, 12 processes) · `make test-fe` **529 passed**
(67 files, 97.18% statements; every `Components/Landing/*` at 100%) · `make e2e`
**14 passed**. Manual check against `https://taskflow.josebianco.local/` (guest,
no console errors): hero + features + steps + footer render; the footer shows the
author credit and the CC BY-NC-ND 4.0 link; the favicon renders in the tab
(pixel-checked: transparent corners, brand fill, white check); a wrong password
submitted from the landing stays on `/` and shows *"These credentials do not
match our records."* on the card (`aria-invalid` + `role="alert"`); the nav
`Sign in` anchor scrolls to the card without an Inertia visit.

**Status (end of session):** branch `feature/90-landing-page` (from `develop`
`2d386a4`), changes **uncommitted** pending a review of the view with the product
owner. Open issues: #23, #24 (deferred cloud phase), **#90**. Next: review the
page, then commit + PR to `develop`.

---

## 2026-10-14 — Round 11b: Mailpit delivery (#87) + test-env precedence bug

**Context:** The product owner reported that transactional emails never show up
in Mailpit and asked for an issue plus delivery tests. Investigating surfaced a
second, more serious bug in the test bootstrap.

**Findings:**

- **Mail never arrives because no queue worker runs.** Every notification
  implements `ShouldQueue`, so Laravel queues a `SendQueuedNotifications` job
  instead of sending inline; the dev stack only offers the *attached*
  `make queue` target (Round-5 decision), and nothing tells the developer it is
  required — so `make up` alone silently drops all mail. Proof: draining the
  backlog with `php artisan queue:work redis --stop-when-empty --tries=3`
  delivered the accumulated **4 messages** to Mailpit immediately; the mail
  config and the notifications themselves are fine.
- **`make test` was running against the dev MySQL database.** The `app`
  container exports `DB_CONNECTION`/`MAIL_*`/`QUEUE_CONNECTION` into the process
  environment (`env_file: .env` + `environment:`), Laravel's env repository reads
  **`$_SERVER` before `$_ENV`**, and PHPUnit's `<env>` entries only populate
  `$_ENV` — so the `sqlite`/`sync`/`array` values in `phpunit.xml` were ignored.
  Consequence: `RefreshDatabase` performed a `migrate:fresh` on the **dev**
  database, wiping it (the local `taskflow` DB is now fully migrated but empty;
  the single dev user account was lost — no projects/tasks existed yet).

**Changes (working tree, uncommitted):**

- **Issue [#87]** opened: *transactional emails never reach Mailpit — no queue
  worker runs in the dev stack* (root cause, evidence, two candidate fixes,
  acceptance criteria).
- `tests/Feature/MailDeliveryTest.php` (new): 4 Pest feature tests driving the
  real mailer (`Mail::mailer()->getSymfonyTransport()`) for password-reset,
  email-verification, team-invitation and mention notifications, asserting the
  rendered subject is actually delivered.
- `phpunit.xml`: every `<env>` gains `force="true"` (+ comment) so the testing
  values are authoritative over the container environment.
- `tests/TestCase.php`: `createApplication()` mirrors the PHPUnit-provided
  `$_ENV` values into `$_SERVER` before the app boots — the piece that makes the
  suite hermetic in Docker (Laravel resolves `$_SERVER` first).
- **Queue worker fix (option 1 of #87), implemented:** `docker/app/supervisord.conf`
  gains a `[program:queue-worker]` running
  `php artisan queue:work redis --tries=3 --max-time=3600 --sleep=1`
  (`autorestart=true`), so the worker always runs inside the `app` container and
  `make up` alone delivers mail. Requires an `app` image rebuild.
- Docs updated to match: `docs/ARCHITECTURE.md` § *Queued mail & the queue worker*
  and README § *Queued email & Mailpit* now describe the supervised worker;
  `make queue` is kept as an optional foreground worker for debugging.

**Incident (reported):** the earlier test runs on the dev MySQL database caused a
`migrate:fresh`, deleting the local `taskflow` data — notably the single dev user
account (`bianquiviri@gmail.com`). It has since re-registered (id 1, unverified).
The DB is now fully migrated. Stale queued jobs that referenced the deleted
`TeamInvitation`/`Comment` rows failed with `ModelNotFoundException` and were
flushed (`php artisan queue:flush`).

**Decisions:**

- The queue-worker fix landed as **option 1** (supervised worker in the `app`
  container), reversing the Round-5 "no long-running worker, minimal footprint"
  call — a missing worker made every transactional email invisible in dev, which
  outweighs the small footprint saving. `make queue` survives as a debugging
  convenience. `docs/ARCHITECTURE.md` and README were updated accordingly.
- The test-env bug is fixed in the test bootstrap (test-side, no compose
  change) because the container-level alternative risks regenerating
  `APP_KEY` on every boot.

**Verification:** `make test` → **346 passed** (2067 assertions, 12 processes;
342 + the 4 new mail tests), up from the red `326 failed` before the fix.
`make lint` green (Pint 201 files, ESLint 0). `MailDeliveryTest` 4/4 green.
**End-to-end after the rebuild:** the 5 backlogged Redis jobs were drained by the
new worker and 2 verification emails reached Mailpit; dispatching a fresh
`sendEmailVerificationNotification()` delivered a message to Mailpit within ~4 s
with no manual `make queue`.

**Status (end of session):** `develop` `a1fc902` plus the uncommitted changes
above (DEVLOG, `phpunit.xml`, `tests/TestCase.php`, `tests/Feature/MailDeliveryTest.php`,
`docker/app/supervisord.conf`, `docs/ARCHITECTURE.md`, `README.md`). Open issues
now #23, #24, **#87**. Next: commit the work on a `bugfix/87-*` branch + PR, and
(separately) confirm whether to file the test-env/data-loss bug as its own issue.

---

## 2026-10-14 — Round 11: v1.0.0 shipped to `main` + post-release fixes (#85/#86)

**Context:** The v1.0.0 release train left `develop` after Round 10 and two
post-release follow-ups landed on `develop`. This session caught this DEVLOG up
(the release itself had never been logged) and fixed a real gap found while
auditing the repo: the `v1.0.0` tag exists, but **no GitHub Release was ever
published** — `gh release list` still advertised `v0.2.0` as *Latest*.

**Changes:**

- **Release v1.0.0** (`release/v1.0.0` → PR #82 → PR #83 to `main`, plus the
  release-housekeeping PR #84): `develop` was released to `main` with the full
  `[1.0.0]` CHANGELOG entry (#25) and an annotated tag `v1.0.0` (`6ffe87c`).
  `main` carries one unique commit, `f885782` (`chore(release): v1.0.0`), from
  the release branch; `develop` is ahead by the post-release fixes below — normal
  GitFlow drift, no sync-back PR was opened.
- **#85 — a11y live region** (`feature/80-a11y-followup`, PR #85, *Closes #80*):
  the filtered-results count in `Components/TaskList.vue` gains
  `aria-live="polite"` + `aria-atomic="true"` so screen-reader users are told how
  many tasks matched. The other half of #80 — raw Tailwind palette classes on the
  four pages #33 rewrote — was verified **already token-based** (a repo-wide
  search finds no raw `*-[0-9]` palette classes in `resources/js` outside a spec
  fixture), so the issue could close complete.
- **#86 — broken nav links** (`bugfix/79-fix-broken-nav-links`, PR #86,
  *Closes #79*): `routes/web.php` gains `/tasks` and `/settings` redirects to the
  real destinations, fixing the 404 every signed-in user hit from #16/#79.
- **v1.0.0 GitHub Release published (this session):** created from the
  CHANGELOG's `[1.0.0]` section and marked **Latest**, superseding `v0.2.0`.
- **Repo hygiene:** a stray `package-lock.json` rewrite found in the working tree
  (root package name `taskflow` → `html`, `libc` fields dropped) was reverted —
  the fingerprint of an accidental `npm` run outside the project, not a
  dependency change.
- **Pruned remotes:** `git fetch --prune` removed ~20 merged/stale remote
  branches (all feature/docs/release branches already squashed into `develop`);
  the two deferred cloud branches (`feature/21-cloud-run`,
  `feature/22-managed-data`) were deliberately kept as reference.

**Decisions:**

- The missing GitHub Release is a **release-process defect**, not cosmetics:
  consumers rely on the *Latest* marker and the generated tarballs, both of which
  pointed at `v0.2.0`. `v1.0.0` is now the published latest.
- **No `main` → `develop` sync-back merge** was performed: `main`'s only unique
  commit is the release-housekeeping `chore`, and `develop` already carries the
  CHANGELOG. A merge would add noise for no content. Revisit only if a future
  release branch needs it.
- Release notes are kept **single-sourced** in `CHANGELOG.md` and copied into the
  GitHub Release body, so the two can be diffed against each other.

**Verification:** docs + release-metadata session, no application code touched.
Local: `make lint` (Pint + ESLint) green, working tree clean, no test suite
impact. Release verified with `gh release view v1.0.0` and `gh release list`
(`v1.0.0` → *Latest*).

**Status (end of session):** `develop` `a1fc902`. `main` `f885782`, tag `v1.0.0`
with a published GitHub Release. Shipped since Round 10: v1.0.0 + #85 + #86.
Open issues: **#23** and **#24** only (deferred cloud custom domain + deploy
docs, v1.1.0). Next: pick up the cloud phase (#21–#24) or open the first v1.1.0
feature round.

---

## 2026-09-28 — Round 10: empty states/onboarding (#33) + a11y pass (#34)

**Context:** The v1.0.0 polish pair. #33 (first-run UX) and #34 (accessibility)
touch the same pages, so #34 was scoped **away** from every file #33 rewrote —
the pair ran in parallel with provably disjoint file sets and merged clean.
Before them, a deploy slice (#21 Cloud Run, #22 managed MySQL/Redis) was
written and deliberately **parked** (see Decisions).

**Changes:**

- **#33 — Empty states, skeletons, onboarding** (`feature/33-empty-states-onboarding`,
  PR #77): `usePageLoading()` extracts the rule #20 already used in
  `Projects/Show` — `router.on('start'/'finish')` per page, skeleton only for a
  full-page GET, never for a mutation (the Inertia progress bar answers that) or
  a partial reload; `useActiveFilters()` de-duplicates the "filters are hiding
  things" predicate; `PageSkeleton` + `DashboardSkeleton` compose #17's
  `Skeleton` with `role="status"` + `aria-busy` + sr-only "Loading…";
  `Onboarding/Checklist.vue` (create team → invite → create project → first task)
  shown only while `kpis.projects === 0`; **`ProjectFormModal.vue` closes a real
  gap — `POST /projects` existed since #11 with no create UI at all** (frontend
  only, gated by the shared `can('projects.create')`); `EmptyState` now reused
  consistently (projects index bare line removed, task-list copy page-owned so
  "first run" and "nothing matches these filters" read differently). CTA→route
  map verified against `routes/web.php`; the checklist states that team creation
  is unavailable instead of inventing a link (no `POST /teams` exists).
  +51 Vitest (61 files / 457), coverage 97.08% stmt, build 186 kB.
- **#34 — Accessibility pass** (`feature/34-accessibility`, PR #78), scoped to
  AppLayout/Auth pages/Teams/Profile/Task show + shared components: skip link as
  first focusable element, focusable `<main>`, labelled `nav`, off-canvas drawer
  leaves the tab order via `inert` with `aria-expanded`/`aria-controls`, focus
  taken on open and restored to the trigger on `Escape` (only when focus was
  inside, so a mouse close is left alone); focus rings where missing (modal
  close, toast dismiss, both pagers). **Contrast was measured, not eyeballed** —
  a script composites the OKLCH token values per surface (light *and* dark):
  `--line-strong` 1.34:1 → **3.06:1** light and 1.42:1 → **3.16:1** dark (control
  boundaries), `--content-subtle` 4.39:1 → 4.51:1 on sunken surfaces,
  `--content-inverted` dark 2.20–2.76:1 → **6.44–8.07:1** (primary buttons),
  `--danger` dark keeps a white label at 4.67:1; placeholders/toast labels moved
  off `--content-faint` (2.36–2.60:1). ARIA: `FlashMessages` renders its live
  region *before* content is inserted and pauses auto-dismiss on hover/focus
  (WCAG 2.2.1), `hint` + `aria-describedby` on all form fields, `Avatar`
  `decorative` prop (header avatar is now announced), `Icon` `aria-hidden` +
  `focusable=false` by default, `aria-current` matching on path only so
  `/tasks/mine?status=todo` still marks Tasks. 448 Vitest · 342 Pest · 5 new
  Playwright a11y specs (11 e2e total) · 32 files, none from #33's set.
- **#79 — new issue**: `AppLayout` links to `/tasks` and `/settings`, **neither
  route exists** (bug from #16, 404 for every signed-in user), and there is no
  `POST /teams` / team-create UI. Decision taken with the product owner: the
  routes are **not** created inside #33/#34 (it would have re-created the file
  overlap); they get wired in a dedicated issue, with an acceptance criterion
  asserting every nav href resolves to a real route.
- **#80 — new issue**: second a11y pass for what #34 had to skip — the filtered
  results count inside `TaskList` has no live region, and the four pages #33
  rewrote still carry raw palette classes.

**Decisions:**

- **The deploy slice (#21/#22) was parked, not merged.** Both agents finished and
  pushed (`feature/21-cloud-run` @ `f97a09b`, `feature/22-managed-data` @
  `9fcdea9`) with no PR. `docs/ARCHITECTURE.md` says the repo is *developed
  locally only until the cloud integration phase* and #21 itself says *"once
  milestone code is ready"* — v1.0.0 was not ready, so shipping deployment IaC
  was premature. Product owner chose to **keep the branches as reference and
  defer**: both issues closed as `not planned` with the branch + reason recorded,
  to be reopened in the v1.1.0 cloud phase (#21–#24). Nothing was provisioned:
  the environment has no `gcloud`, no terraform and no GCP credentials, so the
  deliverable was IaC + runbooks only — explicitly unverified against the real
  GCP API (the #22 agent caught 5 real gcloud flag bugs precisely by checking the
  official references instead of trusting memory, which is the honest signal that
  this work needs a real project before it can be trusted).
- Parallelising #33 and #34 required **file-level scope fences**, not just
  "different components": the prompt listed every file #33 creates/edits as
  forbidden and the agent had to `git diff --name-only origin/develop...HEAD` and
  report the list as proof. That check is what made the clean merge possible.
- Skeletons are **per page**, not a global overlay in `AppLayout`: Inertia v3 has
  no skeleton slot, and an overlay would also cover auth pages and modal submits.
- Contrast was fixed by **adjusting existing tokens**, not by adding a colour
  system; the measured before/after ratios are recorded above so a future
  re-tokenisation can be checked against a baseline.
- Dead nav links were left untouched on purpose (issue #79) rather than
  repointed ad hoc: choosing the destination is a product decision, not a11y
  work.

**Verification:** PRs #77/#78 green on all 6 checks; `develop` CI green after
each merge. Local: ESLint 0, Vitest 448 (#34) / 457 (#33, after the other branch
landed), Pint 200 files, Pest 342 (2046 assertions), Playwright 11, build ok.

**Status (end of session):** `develop` `4539a8f`. Open issues: **#25** (release
v1.0.0 QA + changelog — the only remaining v1.0.0 task), #79, #80, and the
deferred cloud slice #23/#24. Next: #25 — release `v1.0.0` (changelog, release
notes, tag) merging `develop` into `main`.

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