# Deployment

How TaskFlow reaches production. Two halves, one deploy path:

| Path | Owner | What it covers |
| ---- | ----- | -------------- |
| [`cloudrun/`](cloudrun/) | infra (issue #21) | The container image contract, the Cloud Run service, the queue worker, the deploy script and its runbook |
| [`data/`](data/README.md) | data (issue #22) | Managed MySQL and Redis, Secret Manager entries, migrations and the data-layer runbook |

The split is deliberate: the container side has no credentials in it at all, and
the data side has no knowledge of Cloud Run. They meet at exactly one place —
the env-var ↔ secret-name contract, which both sides document.

---

## The container image

Built by [`Dockerfile.prod`](../Dockerfile.prod) (issue #2): a 4-stage build that
compiles the Vite bundle, the PHP extensions and the production Composer
dependencies, and ends in an nginx + php-fpm runtime that carries only the
application. No `.env`, no build toolchain, no state — every configuration value
arrives as an environment variable at start time, and the entrypoint rebuilds
the framework caches on every boot to pick it up.

Two runtime facts shape the whole deployment:

- **The container serves plain HTTP on `$PORT`.** Cloud Run injects `PORT=8080`
  and terminates TLS in front of the container, so the image renders its nginx
  listen port from `$PORT` on every boot (`docker/prod/entrypoint.sh`) and the
  same image still serves `:80` locally. php-fpm stays on a unix socket and is
  never exposed.
- **The web process is not the queue worker.** Queued mail (invitations,
  mentions, verification, password resets) is written to Redis by the web
  revision and consumed elsewhere. Dropping the worker would silently stop all
  transactional email, so it is deployed explicitly — see
  [the worker](#queue-worker) below.

Verify the image locally before trusting any of it:

```sh
docker build -f Dockerfile.prod -t taskflow:local .

# Smoke test on the default port…
docker run --rm -d --name taskflow -p 8080:80 \
  -e APP_ENV=production -e APP_DEBUG=false \
  -e APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY= \
  -e APP_URL=http://localhost:8080 \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=/tmp/database.sqlite \
  -e CACHE_STORE=file -e SESSION_DRIVER=file -e QUEUE_CONNECTION=sync \
  taskflow:local
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:8080/up

# …and on the port Cloud Run injects, which is the case that actually matters.
docker run --rm -d --name taskflow8080 -p 8081:8080 -e PORT=8080 … taskflow:local
curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:8081/up
```

`make lint` and the CI job `Production Image (Dockerfile.prod)` run the same
build plus a `/up` and `/` smoke test on every push, so an image that cannot boot
never reaches a deploy.

---

## Cloud Run service

[`cloudrun/service.yaml`](cloudrun/service.yaml) is the declarative service.
It is never applied verbatim: the committed file carries `__IMAGE__`,
`__REGION__`, `__PROJECT_ID__` and the two env placeholders, so it *cannot* be
applied by accident. `cloudrun/deploy.sh` renders it and hands the result to
`gcloud run services replace`.

Decisions encoded there, and why:

| Decision | Value | Reason |
| -------- | ----- | ------ |
| Scale to zero | `minScale: "0"`, `maxScale: "10"` | An idle service costs nothing and stays inside the free tier. The trade is a cold start on the first request after an idle window. |
| Startup probe | `GET /up` on port 8080 | `/up` is Laravel's health endpoint (`bootstrap/app.php`); it does not touch the database, so it reports "the framework booted" — exactly what readiness means here. `failureThreshold: 24` × `periodSeconds: 5` allows two minutes, enough for `php artisan optimize` on a cold container. |
| Liveness probe | `GET /up`, every 30s | Keeps a wedged container from serving traffic; it does not restart a container that is merely slow to boot, because that is the startup probe's job. |
| Concurrency | `containerConcurrency: 80` | Laravel serialises nothing per container beyond php-fpm's `ondemand` pool (`max_children = 10`); the Redis-backed session/cache/lock handling is what makes high concurrency safe. Raise php-fpm's pool *before* lowering this. |
| Timeout | `60s` | Matches `fastcgi_read_timeout` in `docker/prod/nginx.conf`, so a slow request surfaces as php-fpm's 504 rather than the platform killing it. |
| Ingress | `all` | The custom domain (issue #23) terminates TLS in front of the service; the container only ever sees plain HTTP from Google's front end. |

### Unauthenticated entrypoint: yes, on purpose

The service is granted `roles/run.invoker` to `allUsers`. This is a deliberate
decision, not an oversight:

- TaskFlow authenticates its own users — Laravel sessions, policies, email
  verification — and that is the security boundary.
- The flows that *cannot* work behind Cloud Run IAM are exactly the ones a
  product needs: the registration and password-reset links in transactional mail
  are opened in a user's browser, which holds no Google credentials.
- Putting IAM in front would mean a second, invisible auth system in the request
  path, plus a Google login screen in front of TaskFlow's own login.

For a stricter posture later, the options are Cloud Run IAP / Identity-Aware
Proxy in front of the app (keeps a public URL, adds Google auth), or an
external auth proxy. Both are documented as follow-ups; neither is needed for
the product to work, and the app-level auth is already the boundary that matters.

`deploy.sh --no-invoker` skips the binding if a deployment wants to manage IAM
separately.

---

## Queue worker

The web revision writes to Redis (`QUEUE_CONNECTION=redis`); nothing in the web
process consumes it. The worker is therefore a **Cloud Run Job**, not a second
Service:

- [`cloudrun/worker-job.yaml`](cloudrun/worker-job.yaml) runs the *same image*
  with `php artisan queue:work redis --stop-when-empty --tries=3`. Because only
  the command differs, the worker can never drift from the app it processes jobs
  for. `--stop-when-empty` is what makes it a Job: an idle queue costs one cold
  start and the container exits 0, instead of running until the timeout and being
  billed for it.
- [`cloudrun/schedule-job.yaml`](cloudrun/schedule-job.yaml) is a Cloud
  Scheduler job that starts an execution every 2 minutes through the Cloud Run
  Jobs API, authenticated with an OIDC token as a service account (no keys). The
  Job itself is *not* granted `allUsers`.

Trade-off, stated plainly: a schedule-based worker means up to ~2 minutes of
latency between a queued job and its delivery. That is fine for invitation and
password-reset mail. If per-message latency ever has to be seconds, switch to a
second Service with `minScale: 1` running a plain long-lived `queue:work` — that
leaves the free tier (it is a permanently running instance) but removes the
latency. The manifests here are the switch: point `worker-job.yaml` at a Service
shape and grant it no invoker.

---

## Deploying

### From GitHub Actions (the supported path)

[`.github/workflows/deploy.yml`](.github/workflows/deploy.yml) is the only
automated deploy path.

Triggers, both documented rather than accidental:

- `workflow_dispatch` — a human picks the version (tag, branch or SHA) and runs
  it. The only trigger available on the default branch.
- `push` on tags `v*` — a release tag deploys itself. A tag is an explicit human
  statement of intent, and it does **not** bypass approval: the job still runs in
  the `production` environment. Delete the `push:` block if tags should not
  deploy at all.

Neither trigger is a pull request event. `environment: production` is what makes
this "gated": configure required reviewers on that environment (Settings →
Environments) and no deploy starts without an approval.

Required repository configuration:

| Setting | Kind | Value |
| ------- | ---- | ----- |
| `GCP_PROJECT_ID` | variable | The project id that hosts the service |
| `WIF_PROVIDER_NAME` | variable | The Workload Identity Federation provider (`projects/PROJECT/locations/global/workloadIdentityPools/POOL/providers/PROVIDER`) |
| `WIF_SERVICE_ACCOUNT` | variable | e.g. `taskflow-deploy@PROJECT.iam.gserviceaccount.com` |

The service account needs Artifact Registry writer, Cloud Run admin, Cloud
Scheduler admin and Secret Manager accessor — nothing broader. No key file is
stored in the repository; auth is a short-lived OIDC token.

Steps the workflow runs, in order: checkout the requested ref → authenticate →
build and push the image → resolve the pushed image to a **digest** → preview the
rendered manifests → `deploy.sh` → verify the worker and its trigger → summary.

Two deliberate properties:

- The revision is pinned to an image **digest**, not a tag, so a re-pushed tag
  cannot change what is already running.
- The rendered manifests are printed *before* anything is replaced, so the run
  log shows the exact image and env contract a reviewer approved.

Migrations are opt-in (`migrate: true`), run as a one-off Job before traffic
moves, and default to off: a migration that fails leaves the previous revision
serving, which is the behaviour you want while a schema change is rolling out.

### Locally, or from Cloud Build

```sh
gcloud builds submit --config cloudbuild.yaml \
  --project PROJECT --region REGION \
  --substitutions=_IMAGE=REGION-docker.pkg.dev/PROJECT/taskflow/app:v1.0.0

deploy/cloudrun/deploy.sh \
  --project PROJECT --region REGION \
  --image REGION-docker.pkg.dev/PROJECT/taskflow/app:v1.0.0 \
  --app-url https://taskflow.example.com
```

`deploy.sh --dry-run` renders and prints every manifest and touches nothing —
useful for reviewing a change without a GCP account, and it is what CI's manifest
check runs.

---

## Rollback

Cloud Run keeps the previous revisions, so a rollback is a traffic switch, not a
rebuild:

```sh
gcloud run services update-traffic taskflow \
  --project PROJECT --region REGION \
  --to-revisions taskflow-00041-abc=100
```

Find the revision to roll back to with
`gcloud run services list-revisions taskflow --project PROJECT --region REGION`.
If the release also ran migrations, the rollback story is the database, not the
container — which is why migrations are additive and a separate step.

---

## First-time setup (needs a real GCP project)

Nothing in this repository can create these; they are listed here so the first
deploy is a checklist instead of an exploration.

```sh
PROJECT=your-project
REGION=europe-west1

# 1. APIs
gcloud services enable \
  run.googleapis.com artifactregistry.googleapis.com \
  cloudbuild.googleapis.com cloudscheduler.googleapis.com \
  secretmanager.googleapis.com

# 2. Artifact Registry (one repository for images)
gcloud artifacts repositories create taskflow \
  --repository-format=docker --location="$REGION"

# 3. Service accounts: least privilege, three of them
for sa in taskflow-web taskflow-worker taskflow-deploy taskflow-scheduler; do
  gcloud iam service-accounts create "$sa@$PROJECT.iam.gserviceaccount.com"
done

# taskflow-web / taskflow-worker read only what they need
for sa in taskflow-web taskflow-worker; do
  gcloud projects add-iam-policy-binding "$PROJECT" \
    --member "serviceAccount:${sa}@${PROJECT}.iam.gserviceaccount.com" \
    --role roles/secretmanager.secretAccessor
done

# taskflow-deploy: build + deploy, not owner
gcloud projects add-iam-policy-binding "$PROJECT" \
  --member "serviceAccount:taskflow-deploy@$PROJECT.iam.gserviceaccount.com" \
  --role roles/artifactregistry.writer
gcloud projects add-iam-policy-binding "$PROJECT" \
  --member "serviceAccount:taskflow-deploy@$PROJECT.iam.gserviceaccount.com" \
  --role roles/run.admin
gcloud projects add-iam-policy-binding "$PROJECT" \
  --member "serviceAccount:taskflow-deploy@$PROJECT.iam.gserviceaccount.com" \
  --role roles/cloudscheduler.admin

# taskflow-scheduler: may start a worker execution, nothing else
gcloud run jobs add-iam-policy-binding taskflow-worker \
  --project "$PROJECT" --region "$REGION" \
  --member "serviceAccount:taskflow-scheduler@$PROJECT.iam.gserviceaccount.com" \
  --role roles/run.jobUser

# 4. Workload Identity Federation for GitHub Actions (no key files)
gcloud iam workload-identity-pools create taskflow-deploy \
  --location=global --display-name="TaskFlow deploy (GitHub Actions)"
gcloud iam workload-identity-pools providers create github \
  --location=global --workload-identity-pool=taskflow-deploy \
  --display-name="GitHub Actions" \
  --attribute-mapping="google.subject=assertion.sub,attribute.repository=assertion.repository" \
  --attribute-condition="assertion.repository == 'bianquiviri/taskflow'" \
  --issuer-uri="https://token.actions.githubusercontent.com"

gcloud iam service-accounts add-iam-policy-binding \
  taskflow-deploy@$PROJECT.iam.gserviceaccount.com \
  --role roles/iam.workloadIdentityUser \
  --member "principalSet://iam.googleapis.com/projects/$NUMBER/locations/global/workloadIdentityPools/taskflow-deploy/attribute.repository/bianquiviri/taskflow"
```

Secrets, the database and Redis are provisioned by issue #22 — see
[`data/README.md`](data/README.md). The names the container side expects are
listed in [`cloudrun/env.plain.inc`](cloudrun/env.plain.inc).

### Env-var ↔ secret contract

Nothing here is a guess about *values*; it is the mapping both halves agree on.
The container never contains a credential, so a deployment fails closed — the
revision does not become ready — if a secret is missing.

| Secret Manager secret | Container env var | Laravel reads |
| -------------------- | ----------------- | ------------- |
| `taskflow-app-key` | `APP_KEY` | `app.key` |
| `taskflow-db-host` | `DB_HOST` | `database.connections.mysql.host` |
| `taskflow-db-port` | `DB_PORT` | `database.connections.mysql.port` |
| `taskflow-db-database` | `DB_DATABASE` | `database.connections.mysql.database` |
| `taskflow-db-username` | `DB_USERNAME` | `database.connections.mysql.username` |
| `taskflow-db-password` | `DB_PASSWORD` | `database.connections.mysql.password` |
| `taskflow-redis-host` | `REDIS_HOST` | `database.redis.default.host` |
| `taskflow-redis-port` | `REDIS_PORT` | `database.redis.default.port` |
| `taskflow-redis-password` | `REDIS_PASSWORD` | `database.redis.default.password` |
| `taskflow-mail-password` | `MAIL_PASSWORD` | `mail.mailers.smtp.password` |

Everything else is a plain, non-secret value in
[`cloudrun/env.plain.inc`](cloudrun/env.plain.inc): `APP_ENV=production`,
`APP_DEBUG=false`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`,
`QUEUE_CONNECTION=redis`, `SESSION_SECURE_COOKIE=true`, `LOG_CHANNEL=stderr`
(container stderr → Cloud Logging, same destination as the local stack) and the
`MAIL_*` host/user, which #22 must align with the chosen relay.

`APP_URL` is a deploy-time input, not a committed value: verification and
password-reset links are absolute URLs and must match the origin the browser
used. Issue #23's custom domain becomes the value.

---

## Verification status

Being precise about what is proven and what is not, because this repository was
developed **locally only** — there is no GCP project behind it yet.

**Verified locally, on this machine:**

- `docker build -f Dockerfile.prod .` succeeds and produces the image; `/up`,
  `/login` and `/register` all answer `200` both on the default port and with
  `PORT=8080` injected, and the image's own `HEALTHCHECK` reports `healthy` in
  both cases.
- Every YAML added here parses (`ruby -ryaml`), including the rendered output of
  `deploy.sh --dry-run` for all three manifests.
- `bash -n deploy/cloudrun/deploy.sh`; the script renders, validates its own
  output and refuses to emit a manifest with an unrendered token.
- `cloudbuild.yaml` uses Cloud Build's `$_VAR` substitution syntax (single
  dollar), matching `gcloud builds submit --substitutions`.

**Not verified — needs a real GCP project:**

- That `gcloud run services replace` accepts these manifests verbatim. The
  structure follows the Cloud Run Admin API v1 schema, but the API is the
  authority.
- Startup-probe timings on a real cold start (the two-minute budget is reasoned
  from the entrypoint's cache build, not measured).
- That the service accounts, WIF pool and Secret Manager names above line up with
  the project as provisioned by #22.
- Worker latency and mail delivery end to end.

First real deploy should therefore be a manual `deploy.sh` run against a scratch
project, then the automated path.