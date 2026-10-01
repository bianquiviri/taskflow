# Production data services (MySQL + Redis)

Managed MySQL and Redis for TaskFlow production, the Secret Manager contract
that carries them into the Cloud Run service, the migration job, and the
backup/restore runbook.

> **Nothing here has been provisioned.** This repository contains scripts and a
> runbook; no Cloud SQL instance, Memorystore instance or secret exists yet.
> There is no `gcloud` CLI, no billing-enabled project and no network path to
> Google Cloud in the environment these files were written in, so every
> `gcloud` command below is **unexecuted**. What *was* verified locally is
> listed in [Verified vs. unverified](#verified-vs-unverified) — read it before
> trusting a step.

| What             | Choice                                    | Where it is defined                     |
| ---------------- | ----------------------------------------- | --------------------------------------- |
| MySQL            | Cloud SQL for MySQL 8.0                    | `env.sh` → `SQL_DATABASE_VERSION`        |
| Redis            | Memorystore for Redis 7                    | `env.sh` → `REDIS_VERSION`               |
| Secrets          | Secret Manager, 3 injected env vars        | [contract](#the-env-var--secret-table)   |
| Migrations       | Cloud Run Job from the production image    | `.github/workflows/db-migrate.yml`       |
| Backups          | Cloud SQL automated + PITR, plus daily dump | `scripts/backup.sh`, `scripts/restore.sh` |

---

## Files

| File                            | Purpose                                                            |
| ------------------------------- | ------------------------------------------------------------------ |
| `env.sh`                        | The contract. Names, instances, regions, env vars. Sourced by every script *and* by `db-migrate.yml`. |
| `scripts/lib.sh`                | `gcloud` helpers: SQL execution, secret access, Memorystore lookups. |
| `scripts/secrets.sh`            | Creates the four secrets if missing and prints the contract.         |
| `scripts/provision-cloudsql.sh` | Instance + database + role + grants + Memorystore + IAM + backups.   |
| `scripts/backup.sh`             | Logical dump to Cloud Storage, verified, with retention pruning.    |
| `scripts/restore.sh`            | Validate / rehearse / restore a dump.                               |
| `scripts/verify-connection.sh`  | Runs the real application against the managed services.             |

```sh
export GCP_PROJECT=taskflow-prod GCP_REGION=europe-west1

# 1. Memorystore has no public-IP option: it always joins a VPC. Do this first
#    (§2 has the full form, including the peering the connector needs).
gcloud compute networks create taskflow-vpc --subnet-mode=custom
gcloud services vpc-peerings connect --service=servicenetworking.googleapis.com \
  --network=taskflow-vpc --range=10.8.0.0/28

# 2. Secrets, Cloud SQL, database, role, grants, Memorystore, IAM.
./deploy/data/scripts/provision-cloudsql.sh   # idempotent

# 3. Prove the *application* can reach all of it.
./deploy/data/scripts/verify-connection.sh
```

Provisioning knobs (all overridable from the environment, defaults in
`env.sh`): `SQL_TIER=db-custom-1-4096`, `SQL_DATABASE_VERSION=MYSQL_8_0`,
`VPC_NETWORK=taskflow-vpc`, `REDIS_CONNECT_MODE=direct-peering`,
`REDIS_IP_RANGE=` (empty = Google allocates from the peering range),
`REDIS_MEMORY_GB=1`, `BACKUP_RETENTION_DAYS=7`, `BACKUP_RETAINED_COUNT=7`,
`BACKUP_BUCKET=${GCP_PROJECT}-taskflow-backups`,
`RUN_SERVICE_ACCOUNT=${GCP_PROJECT}-compute@developer.gserviceaccount.com`.

---

## Why Cloud SQL and not Aiven

Both were viable; the deciding factors were the free-tier budget and the shape of
the Cloud Run service the other agent is deploying.

| | **Cloud SQL for MySQL** ✅ | **Aiven** |
| --- | --- | --- |
| Cost | Billed, no free tier (~`db-custom-1-4096`) | Free tier (1 vCPU / 5 GB), hard resource ceilings |
| Path from Cloud Run | **Built-in connector**: `--add-cloudsql-instances` mounts a unix socket, no VPC, TLS in transit, IAM-scoped | Public TCP only: needs `DB_HOST` + `MYSQL_ATTR_SSL_CA` pointing at a CA file **inside the image** |
| Backups | Automated backups, 7-day PITR, `gcloud sql export/import` | Aiven's own backups; a restorable cross-region dump is yours to build |
| Migrations | Plain role + grants, no IAM entanglement | Same role model, but the dump/restore equivalent of `gcloud sql export/import` is a hand-rolled upload |
| Consistency | Same project/region/IAM/billing as Cloud Run | Second provider, second account, second set of credentials |
| Local dev parity | Same engine as `mysql:8.4` | Same |

**Decision: Cloud SQL.** The free tier matters only until the first real user;
carrying Aiven to save nothing today would mean shipping a CA file into the
image (`MYSQL_ATTR_SSL_CA` is a *path*, and `config/filesystems.php`/`config/*`
are not this issue's to change) and a second provider forever. The Aiven path is
documented in [Appendix A](#appendix-a--the-aiven-alternative) in case the
budget changes.

### MySQL version

`SQL_DATABASE_VERSION=MYSQL_8_0` (it is `--database-version`, not
`--engine-version`) — **Cloud SQL's 8.0 line tracks the latest 8.0.x
patch**, so it is not pinned, but it will not jump to 8.4.

* **Local** is `mysql:8.4`; **production** is 8.0.x. Nothing in the schema or the
  16 migrations needs 8.4 (JSON columns on `utf8mb4`, generated columns, CHECK
  constraints and window functions all predate it), so the gap is not a feature
  gap — which is also why the local suite passing says nothing about 8.0
  compatibility either way; the Cloud Run job's `migrate` is the real test.
* **The upgrade caveat.** Going 8.0 → 8.4 is a **major-version** upgrade. Do
  not assume it is an in-place `gcloud sql instances patch`: check the current
  Cloud SQL release notes and the versions your region offers before booking the
  window, because the supported path is version- and region-dependent and an
  export → import into a new instance is always the fallback. Whichever route
  it turns out to be, it needs a maintenance window and a fresh PITR baseline, so
  rehearse it with `restore.sh --into` and a scratch instance first, then
  re-check the collation assertions (`provision-cloudsql.sh` step 6) and
  `deploy/data/scripts/verify-connection.sh` afterwards. Never let a major
  upgrade happen as a side effect of someone running `patch`.
* On the Aiven side the equivalent constraint is that the free plan may not offer
  the version you want, and switching version means a restore-and-switch.

### Charset and collation (checked, not assumed)

The collation is **not** inherited from the database. `MySqlGrammar::compileCreateEncoding()`
falls back to the *connection* config, so `php artisan migrate` stamps every
table it creates with:

```sql
CREATE TABLE `tasks` … default character set utf8mb4 collate 'utf8mb4_unicode_ci'
```

from `config/database.php` (`DB_CHARSET` / `DB_COLLATION`). Verified locally:
against a database whose own default was `utf8mb4_0900_ai_ci` (what Cloud SQL
hands you), all 19 migrated tables came out `utf8mb4_unicode_ci`.

Two consequences:

* **Never override `DB_CHARSET`/`DB_COLLATION` in production.** They are the
  single source of truth for every table's collation; changing them later means
  rebuilding every table, not a config flip. `env.sh` therefore pins them to the
  committed defaults and `provision-cloudsql.sh` asserts the result.
* The database is still created with an explicit
  `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`, so anything *not* created
  by a migration — a restored dump, a table made by hand, a `CREATE TABLE` from
  another tool — matches too. Mixing `utf8mb4_unicode_ci` columns with
  `utf8mb4_0900_ai_ci` ones is the classic `Illegal mix of collations`
  (error 1267), and with `strict => true` a too-small `utf8mb4` column fails at
  insert time rather than at boot.

`utf8mb4_unicode_ci` is deprecated-but-supported on MySQL 8.0. Moving to
`utf8mb4_0900_ai_ci` is an `env.sh` change **plus** a table rebuild, so it is not
something to do casually; at 8.0 the two compare nearly identically.

---

## Provisioning

### 0. What has to be installed locally

```sh
brew install cloud-sql-proxy mysql-client      # macOS
# Linux: gcloud components install cloud-sql-proxy && apt-get install mysql-client
```

`gcloud sql connect` **cannot** run SQL non-interactively — its whole flag list
is `--database/--user/--port/--skip-ssl/--run-connection-test/--debug-logs/
--auto-ip/--private-ip/--psc`, with no way to hand it a statement and no
`--password`, so it ends in an interactive `mysql` prompt. Creating the database
and the role's grants therefore goes through the **Cloud SQL Auth Proxy binary**
(the same one Cloud Run mounts) plus the `mysql` client, which is what
`scripts/lib.sh` starts on demand. `cloud-sql-proxy` authenticates with your
`gcloud` credentials and needs `roles/cloudsql.client` **on the service account
you run as** (your own user for an operator laptop), plus
`roles/serviceusage.serviceUsageConsumer`.

### 1. Cloud SQL — Cloud Run connector (chosen)

Cloud Run mounts a **unix socket** for any instance passed with
`--add-cloudsql-instances`. No VPC, no private IP, no authorised network; the
Auth Proxy inside the container provides TLS in transit and authenticates with
the runtime service account (`roles/cloudsql.client`).

```sh
export GCP_PROJECT=taskflow-prod GCP_REGION=europe-west1
./deploy/data/scripts/provision-cloudsql.sh
```

What it does, in order:

1. `scripts/secrets.sh` — creates the four secrets if missing (never rotates).
2. `gcloud sql instances create` — MySQL 8.0 (`--database-version`), regional
   availability, automated backups at 03:00 with a 7-day PITR window
   (`--retained-transaction-log-days`) and `--retained-backups-count` dumps,
   `--enable-point-in-time-recovery`, automatic minor-version upgrades,
   `--retain-backups-on-delete`, `--deletion-protection`, `--no-assign-ip`.
3. Patches backup settings so an *existing* instance ends up with them too.
4. `CREATE DATABASE … CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`.
5. Creates the application role with the grants `migrate` needs — including
   `CREATE ROUTINE`/`ALTER ROUTINE`/`EXECUTE`, which a plain
   `GRANT ALL ON taskflow.*` does **not** include and which
   `php artisan migrate` (schema dump) would trip over.
6. Asserts version ≥ 8.0, charset, collation, `STRICT_TRANS_TABLES` and that
   grants exist.
7. Creates the Memorystore instance (`--redis-version=redis_7_0`, 1 GiB,
   `--transit-encryption-mode=server-authentication`, `--enable-auth`) inside
   `$VPC_NETWORK`, after checking that network exists — Memorystore has no
   public-IP option, and `--network` defaults to `default`.
8. Grants `roles/cloudsql.client` to `$RUN_SERVICE_ACCOUNT` (the Cloud Run
   identity) and `roles/secretmanager.secretAccessor` on the project. Set
   `RUN_SERVICE_ACCOUNT` if the service does not run as the project's default
   compute service account; it must be the same address the GitHub workflow
   impersonates via `secrets.GCP_SERVICE_ACCOUNT`.

Steps 4–5 run **as the instance root**, once. There is no IAM shortcut:
`gcloud sql users create` only makes a *proxy* user with no MySQL grants, and
the unix socket refuses `root`. Root's password lives in its own secret
(`taskflow-db-root-password`) so the application's role can never take over the
instance. Those two statements can equally be pasted into the Cloud SQL console
if you would rather the root password never touch a command line — the script
says so when it needs them.

The service and the jobs must then be deployed with:

```sh
gcloud run deploy taskflow … \
  --add-cloudsql-instances=PROJECT:REGION:taskflow-mysql \
  --set-secrets=APP_KEY=…,DB_PASSWORD=…,REDIS_PASSWORD=…
```

**`DB_SOCKET` and `DB_HOST` are mutually exclusive.** `MySqlConnector::getDsn()`
uses the socket whenever `unix_socket` is non-empty and ignores host/port
entirely. Set `DB_SOCKET=/cloudsql/PROJECT:REGION:taskflow-mysql` and nothing
else about the host; leave `DB_SOCKET` unset and it falls back to
`DB_HOST`/`DB_PORT`.

### 2. Cloud SQL — private IP over Serverless VPC Access (alternative)

Use this when the instance must also be reachable from other VPC workloads, or
when you want Memorystore over the VPC as well.

```sh
gcloud compute networks create taskflow-vpc --subnet-mode=custom
gcloud compute networks subnets create taskflow-subnet --network=taskflow-vpc \
  --region=europe-west1 --range=10.10.0.0/24 --enable-private-ip-google-access

gcloud services vpc-peerings connect --service=servicenetworking.googleapis.com \
  --network=taskflow-vpc --range=10.8.0.0/28

gcloud compute networks vpc-access connectors create taskflow-connector \
  --region=europe-west1 --network=taskflow-vpc --range=10.10.1.0/24

# instance with a private IP
gcloud sql instances patch taskflow-mysql --network=projects/taskflow-prod/global/networks/taskflow-vpc
gcloud sql instances connect taskflow-mysql --enable-private-service-connect

# then the service/job
MYSQL_ACCESS=vpc-connector REDIS_ACCESS=vpc-connector ./deploy/data/scripts/provision-cloudsql.sh
gcloud run deploy taskflow --network=taskflow-vpc --subnet=taskflow-subnet \
  --vpc-connector=taskflow-connector …
```

Cost and complexity: the connector has its own (small) idle cost and an egress
per GB, and VPC peering does not replace the Cloud SQL connector — you end up
with both `--vpc-connector` and `--add-cloudsql-instances`. Worth it only if the
VPC is needed for something else.

### 3. Cloud SQL — public IP + authorised networks (not recommended)

```sh
MYSQL_ACCESS=public-ip AUTHORISED_NETWORK=203.0.113.0/24 \
  ./deploy/data/scripts/provision-cloudsql.sh
```

That CIDR is the *laptop* case. For GitHub-hosted runners the egress addresses
are **dynamic and not published**, so `AUTHORISED_NETWORK` cannot be maintained
and the workflow will break without warning. Only acceptable behind a
Cloudflare Tunnel / IAP TCP forwarder, or with a fixed egress IP (a NAT gateway
you pay for anyway). It also forces `MYSQL_ATTR_SSL_CA` handling (see
[Appendix A](#appendix-a--the-aiven-alternative)) because the connector is no
longer terminating TLS for you.

### 4. Redis reachability

| Path | How | When |
| --- | --- | --- |
| **VPC connector** ✅ | `--vpc-connector` + Memorystore private IP | the web service (low latency, needs the VPC from §2) |
| **SQL proxy port map** ✅ | `--add-cloudsql-instances` + `--add-cloudsql-instances …:6379:redis` | the migration Job: works with the same single flag the MySQL socket already needs, no extra VPC |
| Public IP | Memorystore with a client proxy | not recommended |

Memorystore is created with `--enable-auth`, so `REDIS_PASSWORD` is always a
real value in production.

### 5. Confirm it end to end

```sh
./deploy/data/scripts/verify-connection.sh
```

It boots the real image (`Dockerfile.prod`) with the contract from `env.sh` plus
the secret payloads, then asserts — through the application, not through a SQL
client — that the framework boots, PDO reaches the configured database (name
asserted, table listing counted), a cache value round-trips, a session writes,
`QUEUE_CONNECTION` really resolves to the redis driver on `REDIS_QUEUE`, and
the `failed_jobs` sink is readable. `VERIFY_REMOTE=1` runs the same checks inside
the Cloud Run Job instead; `VERIFY_DOCKER_NETWORK` points the container at a
docker-compose stack.

---

## The env var ↔ Secret Manager table

**This is the contract with the Cloud Run service.** The service injects exactly
these three secrets; everything else is a plain, non-secret env var from
`data_env_vars` in `env.sh`.

| Env var          | Secret Manager secret      | Version | Payload                          | Reaches Laravel as |
| ---------------- | -------------------------- | ------- | -------------------------------- | ------------------ |
| `APP_KEY`        | `taskflow-app-key`         | latest  | `base64:` + 32 random bytes      | `config/app.php:102` (`'key' => env('APP_KEY')`) |
| `DB_PASSWORD`    | `taskflow-db-password`     | latest  | 40 random alphanumeric chars     | `config/database.php:56` |
| `REDIS_PASSWORD` | `taskflow-redis-password`  | latest  | Memorystore AUTH string           | `config/database.php:162` |
| *(`DB_ROOT_PASSWORD`)* | `taskflow-db-root-password` | latest | Cloud SQL instance root | **never injected** — operator only |

```sh
# gcloud form
--set-secrets=APP_KEY=projects/$PROJECT/secrets/taskflow-app-key:latest,DB_PASSWORD=projects/$PROJECT/secrets/taskflow-db-password:latest,REDIS_PASSWORD=projects/$PROJECT/secrets/taskflow-redis-password:latest

# GitHub Actions form (--set-secrets ENV=SECRET)
APP_KEY=taskflow-app-key
DB_PASSWORD=taskflow-db-password
REDIS_PASSWORD=taskflow-redis-password
```

Non-secret env vars (all from `deploy/data/env.sh`, overridable per project):

| Env var                      | Value                   | Read by |
| ---------------------------- | ----------------------- | ------- |
| `DB_CONNECTION`              | `mysql`                 | `config/database.php:22` |
| `DB_SOCKET`                  | `/cloudsql/…` or unset  | `config/database.php:57` |
| `DB_HOST` / `DB_PORT`        | `127.0.0.1` / `3306`, ignored when `DB_SOCKET` is set | `config/database.php:52-53` |
| `DB_DATABASE`                | `taskflow`              | `config/database.php:54` |
| `DB_USERNAME`                | `taskflow`              | `config/database.php:55` |
| `DB_CHARSET` / `DB_COLLATION`| `utf8mb4` / `utf8mb4_unicode_ci` | `config/database.php:58-59` |
| `CACHE_STORE`                | `redis`                 | `config/cache.php:20` (store `redis` → connection `cache` → `REDIS_CACHE_DB`) |
| `SESSION_DRIVER`             | `redis`                 | `config/session.php:23` |
| `QUEUE_CONNECTION`           | `redis`                 | `config/queue.php:18` (connection `redis` → `REDIS_QUEUE`) |
| `REDIS_CLIENT`               | `phpredis`              | `config/database.php:150` |
| `REDIS_HOST` / `REDIS_PORT`  | Memorystore host / `6379` | `config/database.php:160,163` |
| `REDIS_DB` / `REDIS_CACHE_DB`| `0` / `1`               | `config/database.php:164,177` |
| `REDIS_QUEUE`                | `default`               | `config/queue.php:71` |
| `REDIS_QUEUE_RETRY_AFTER`    | `90`                    | `config/queue.php:72` |
| `LOG_CHANNEL`                | `stderr`                | `config/logging.php` (Cloud Run collects it) |
| `MAIL_MAILER`                | `log`                   | `config/mail.php` — **set a real SMTP transport before inviting users** |

### How they reach Laravel, and why

Laravel has **no Google Secret Manager driver**, and it does not need one: the
Cloud Run service account holds `roles/secretmanager.secretAccessor`, so
`--set-secrets=APP_KEY=projects/…/taskflow-app-key:latest` makes the platform
resolve the version at container start and materialise it as an environment
variable. Laravel reads `env('APP_KEY')` exactly as it would from a dotenv file
— which is the point, since `Dockerfile.prod` ships no `.env` at all.

The alternative — a custom loader that fetches secrets from inside the
container at boot — was rejected: it would mean a hand-written loader in
`bootstrap/app.php` or `docker/prod/entrypoint.sh` (neither belongs to this
issue), a second code path for every secret, and an HTTP round trip on every
cold start, to obtain exactly what the platform already does. **Trade-off to
know about:** with `--set-secrets`, `gcloud run services describe` does *not*
print the values (good), but any process inside the container can read them, and
a secret version bump only takes effect on the **next revision** — which is
exactly why rotation redeploys.

### Versioning and rotation

Secrets are created with `--replication-policy=automatic` (regional, and the
versions are what you actually restore). `:latest` is an alias, not a version
number, so it always follows the newest one. Old versions stay readable — which
is the rollback path, and also the reason to prune them.

Rotation order matters: **the secret and the database cannot change in the same
instant**, and a secret bump only reaches a container on its next revision.

1. Add the new version — `:latest` immediately moves to it, so no existing
   container changes yet (they hold the old value in their environment):
   `gcloud secrets versions add taskflow-db-password --data-file=…`
2. `ALTER USER 'taskflow'@'%' IDENTIFIED BY '<new>';` via
   `gcloud sql connect taskflow-mysql --user=root`, taking `<new>` from the new
   secret version. **From here to step 4, running containers are broken.**
3. Redeploy the service **and** the migration job so they pick up the new
   version. If even that window is too wide, run a second role
   (`taskflow_next`, same grants), point a new revision at it with
   `DB_USERNAME`, then drop the old role — no window at all.
4. Verify (`/up`, plus a login round trip).
5. Only then destroy the previous version —
   `gcloud secrets versions disable <n>` — keeping it until step 4 is the
   rollback.

`APP_KEY` rotation is different and self-contained: sessions, cookies and
anything encrypted (`APP_PREVIOUS_KEYS`) are invalidated. For a pre-1.0 project
that is usually just a re-login; set `APP_PREVIOUS_KEYS` first if not.

---

## Migrations as a Cloud Run Job

**Why a job and not a request:** migrations must run to completion, produce a
non-zero exit code on failure, and must not be tied to an HTTP request.
Cloud Run Jobs are the primitive for that, and running them from the *same*
image (`Dockerfile.prod`) removes the classic "the migrator is on a different
commit than the app" failure.

```sh
gcloud run jobs create taskflow-migrate \
  --image=$REGION-docker.pkg.dev/$PROJECT/taskflow/app:TAG \
  --region=europe-west1 \
  --command=php --args=artisan,migrate,--force \
  --tasks=1 --task-timeout=30m \
  --add-cloudsql-instances=$PROJECT:$REGION:taskflow-mysql \
  --add-cloudsql-instances=$PROJECT:$REGION:taskflow-mysql:6379:taskflow-redis \
  --set-env-vars="$(…data_env_vars…)" \
  --set-secrets=APP_KEY=…,DB_PASSWORD=…,REDIS_PASSWORD=…
```

**`--command` and `--image` belong to the job template, not to the execution.**
`gcloud run jobs execute` accepts only `--args`, `--update-env-vars`, `--tasks`,
`--task-timeout` (plus the usual `--region/--async/--wait`) as per-execution
overrides — it has no `--image` and no `--command` flag, and passing
`--set-env-vars` there fails too (the flag is `--update-env-vars`). So:

* the job is **created** with `--command=php --args=artisan,migrate,--force`;
* a dry run is the same job with `--args=artisan,migrate,--pretend`, which is
  what `db-migrate.yml` does;
* changing the image is a `gcloud run jobs update … --image=…` (a deliberate,
  reviewed act), which is why the workflow's `image` input is an **assertion**
  and not an override: it fails the run if the job does not already point at the
  tag the service runs.

The image's `ENTRYPOINT` (`docker/prod/entrypoint.sh`) always runs first: it
creates the writable `storage/` paths, chowns them to `www-data`, builds the
framework caches and then `exec`s the command. Verified locally with the built
image: `docker run … php artisan migrate --force` applied all 16 migrations,
exit 0.

Use `--command php`, **not** `--entrypoint=artisan`: skipping the entrypoint also
skips the directory bootstrap, so the job would run as root and leave
root-owned cache files that php-fpm cannot replace on the next boot.

`.github/workflows/db-migrate.yml` is the human gate:

* `workflow_dispatch` only — a schema change is never automatic.
* **`dry_run` defaults to `true`**: `migrate --pretend` reports what is pending
  and writes nothing.
* For `production`, a real run additionally requires
  `confirm_production` to equal the environment name.
* `backup_first` defaults to `true` and runs `scripts/backup.sh` first.
* `concurrency: db-migrate-<environment>`, `cancel-in-progress: false` — two
  `migrate` runs against one database race on the `migrations` table.
* Every variable comes from `deploy/data/env.sh`, the same file the provision
  scripts use, so the job cannot drift from the contract.
* The `image` input asserts which tag the job runs; it does **not** silently
  repoint the shared job, and the run reads `--command` back from the template so
  the log states what actually ran.

Required GitHub **environment variables** (`vars.*`, not secrets — they are not
secret): `GCP_PROJECT`, `GCP_REGION`, `DB_NAME`, `DB_USER`, `SQL_INSTANCE`,
`REDIS_INSTANCE`, `RUN_JOBS_NAME`, `RUN_MIGRATE_JOB`, `APP_SERVICE`,
`BACKUP_BUCKET`. Required **secrets**: `GCP_WORKLOAD_IDENTITY_PROVIDER`,
`GCP_SERVICE_ACCOUNT` (Workload Identity, `id-token: write`).

---

## Backup and restore

Three layers, in the order they should save you:

1. **Cloud SQL automated backups + PITR** — every day at 03:00, 7-day window.
   Managed, invisible to the app, and the fastest way back from "someone deleted
   a row".
2. **Daily logical dump** — `scripts/backup.sh`, `gcloud sql export sql` into
   Cloud Storage, verified (gzip stream + `CREATE TABLE` + row counts) before it
   is called a backup, pruned to `BACKUP_KEEP=14`. Run it from Cloud Scheduler
   through the same job definition as the migrations; the export is a managed
   operation, so the runner needs no `mysqldump` and no inbound connectivity.
3. **A rehearsed restore** — `scripts/restore.sh --verify-only` on a schedule.
   A backup nobody has restored is a hypothesis.

### Daily

```sh
GCP_PROJECT=taskflow-prod GCP_REGION=europe-west1 \
  BACKUP_PREFIX=daily ./deploy/data/scripts/backup.sh
```

`provision-cloudsql.sh` creates `gs://$BACKUP_BUCKET` (uniform bucket-level
access, public access prevention) if it is missing. Grant whoever runs the
export `roles/cloudsql.client` + `roles/storage.objectAdmin` on it. Keep a copy
in a second bucket **in another region** — a regional outage takes both otherwise.

### Restore, rehearsed (safe)

```sh
./deploy/data/scripts/restore.sh --verify-only gs://…/taskflow-mysql-taskflow-20260928T030000Z.sql.gz
./deploy/data/scripts/restore.sh --into taskflow_restore gs://…/taskflow-mysql-taskflow-20260928T030000Z.sql.gz
```

The second form imports into a scratch database on the same instance and reports
its table and migration counts; point the migration job at it
(`--update-env-vars=DB_DATABASE=taskflow_restore`) to rehearse the *whole*
recovery, then drop it.

### Restore, for real

1. **Stop the writes.** `--no-traffic` first, then the queue worker, or it will
   happily write to the database you are about to replace:
   ```sh
   gcloud run services update taskflow --region europe-west1 --no-traffic
   # stop the worker job / scale it to 0 for the duration
   ```
2. Take a safety dump of the *current* (broken) state first: `backup.sh` with
   `BACKUP_PREFIX=pre-restore`. You may need what you are about to overwrite.
3. `RESTORE_CONFIRM=taskflow ./deploy/data/scripts/restore.sh --production gs://…`
4. **Roll the schema forward.** A dump is not necessarily the head of `main`:
   ```sh
   gcloud run jobs execute taskflow-migrate --region europe-west1 \
     --args=artisan,migrate,--force --wait
   ```
   (`--args` only — the job template already carries `--command php`.) Never run
   `migrate:rollback` against a restored database — the `migrations` table
   describes a *different* history.
5. Re-enable traffic and restart the worker:
   `gcloud run services update taskflow --min-instances=1`, then
   `curl -fsS https://<host>/up`.

### PITR, when you do not have a dump

```sh
gcloud sql backups list --instance=taskflow-mysql
gcloud sql backups restore <backup-id>
```

This is a **full-instance** restore into a **new** instance; it does not
restore in place. Re-point `DB_SOCKET`/`--add-cloudsql-instances`, then run
steps 4–5 above.

---

## Verified vs. unverified

There is no `gcloud`, no billing-enabled project and no network route to Google
Cloud in the environment this was written in. **No resource was created and no
`gcloud` command in this repository has ever been executed.**

Verified locally, mechanically:

* **The production image runs the migration command the job relies on.**
  `Dockerfile.prod` was built from this tree and `php artisan migrate --force`
  was executed through the entrypoint (`docker run … php artisan migrate
  --force` — the shape Cloud Run's `--command php --args=…` produces) against a
  scratch MySQL 8.4 database with exactly the env vars `env.sh` emits. Exit 0,
  16 migrations applied. That is what backs the job design, not a claim about it.
* **`scripts/verify-connection.sh` passes end to end** against the local stack
  (MySQL 8.4.9 + Redis 7, docker-compose): framework boot, `db:show` over raw
  PDO, a cache round trip, a session write, the queue resolving to the redis
  driver on the `default` queue, and the `failed_jobs` sink. The production
  script and the production image were used; only the endpoints were local.
* **The collation mechanism was established empirically**, not read off: in a
  database whose default is `utf8mb4_0900_ai_ci`, `migrate` produced 19 tables
  all at `utf8mb4_unicode_ci` (see above). An earlier draft of this document
  claimed tables inherit the database default; the local stack's own `taskflow`
  database disproved it.
* **Every `gcloud`/flag pair was checked against the application's own
  configuration.** `DB_SOCKET` really does win over `DB_HOST`/`DB_PORT`
  (`MySqlConnector::getDsn()` reads `unix_socket` first); `REDIS_URL` really
  would override `REDIS_HOST`/`REDIS_PORT`/`REDIS_PASSWORD`/`REDIS_DB`
  (`RedisManager::parseConnectionConfiguration()`), which is why it is never set;
  `! empty($config['password'])` really does fire `Redis::auth()` (so the
  literal string `null` — which is what `.env` carries — would be sent as a
  password); the cache store really resolves to connection `cache` /
  `REDIS_CACHE_DB` and the queue to `REDIS_QUEUE`; `strict => true` really does
  mean every write runs under `STRICT_TRANS_TABLES`.
* **Every gcloud flag was checked against the official command references**, not
  from memory — which caught five mistakes that would each have failed on first
  contact with a real project: `gcloud sql connect` has no `--execute-file` and
  no `--password` (it cannot run SQL non-interactively at all), `gcloud run jobs
  execute` has no `--image`/`--command` and spells it `--update-env-vars`,
  Cloud SQL backup retention is `--retained-transaction-log-days` in *days* (and
  `--retained-backups-count`), not `--retain-backups-hours`, Memorystore AUTH is
  `--enable-auth` (not `--auth-enabled`), and `gcloud redis instances create`
  needs an explicit `--network` because Memorystore has no public-IP option.
  Where the flag surface is not self-evident, the correction is commented at the
  call site.
* `.github/workflows/db-migrate.yml` parses as YAML
  (`ruby -e 'require "yaml"; YAML.load_file(…)'`), with jobs, steps and inputs
  enumerated — the failure mode that silently killed CI in a previous PR.
* `sh -n` **and** `bash -n` clean on `env.sh` and all six scripts;
  `secrets.sh --print` emits the contract with no `gcloud` installed.
* Pint clean on the whole tree.

Incidental framework findings, in case they save someone a debugging session:
Laravel's `db:show` counts tables across *every* user schema, not just the
connected one (hence the script asserts the database name and table listing
itself); and `Queue::push()` refuses an anonymous-class job outright
(`Serialization of 'ShouldQueue@anonymous' is not allowed`), so the queue check
asserts the resolved driver and the Redis list rather than pushing a job.

Unverified — these need a real project and were never run:

* every `gcloud sql` / `redis` / `run` / `secrets` invocation (flag *names* were
  checked against the references, the calls themselves never ran), and the
  Cloud SQL Auth Proxy + mysql client path, which needs an instance;
* Cloud SQL's actual default collation on the chosen version, and whether
  `CREATE DATABASE … COLLATE utf8mb4_unicode_ci` is accepted (it is expected to
  be: the collation exists on 8.0, and it was accepted by MySQL 8.4 locally);
* Memorystore with `--enable-auth`, `--network=$VPC_NETWORK`,
  `--connect-mode=direct-peering` and `--transit-encryption-mode
  server-authentication` (needs the VPC of §2 to exist first, and its peering);
* `gcloud storage buckets create` for the backup bucket, and every
  `roles/*` IAM binding (the service account needs `roles/cloudsql.client`, and
  for a VPC-attached Memorystore also `roles/compute.networkUser` on the VPC);
* the Cloud Run Job itself (creation, the socket mount, `--command php` inside
  it, the execution and its logs). Its *flag surface* is not guesswork: the
  `--args/--update-env-vars` split above comes from the official
  `gcloud run jobs execute` reference, and the container-side behaviour it relies
  on was verified locally;
* the Cloud SQL unix socket itself (`/cloudsql/…`) — the local run used TCP;
* `scripts/backup.sh` / `scripts/restore.sh` / `scripts/provision-cloudsql.sh`
  end to end: all of them need Cloud SQL and Cloud Storage.

**Before trusting any of it:** run `provision-cloudsql.sh` against a throwaway
project, then `restore.sh --into`, then `verify-connection.sh`, and read the
output rather than this file.

---

## Appendix A — the Aiven alternative

If the budget forces the free tier, this is the complete path. It was **not**
executed or tested.

```sh
# 1. Plans: service_type=mysql, service_version=8.0.latest, service_plan=hobbyist
aivenctl service create --project my-project --service-name taskflow-mysql \
  --service-type mysql --service_version 8.0.latest --service_plan hobbyist \
  --region google-europe-west1
aivenctl service create --project my-project --service-name taskflow-redis \
  --service_type redis --service_version 7.2 --service_plan hobbyist \
  --region google-europe-west1

# 2. TLS. MYSQL_ATTR_SSL_CA is a *path* (config/database.php:65) and the image
#    ships no CA bundle for it, so the CA has to be added to Dockerfile.prod.
aivenctl service get-ca --project my-project --service-name taskflow-mysql --ca-cert
#   -> COPY deploy/data/aiven-ca.pem /etc/ssl/certs/aiven-mysql.pem  (a #22 follow-up)
#   -> env var: MYSQL_ATTR_SSL_CA=/etc/ssl/certs/aiven-mysql.pem

# 3. No unix socket, no IAM: the service connects over public TCP.
gcloud run deploy taskflow \
  --set-env-vars=DB_HOST=<aiven-host>,DB_PORT=<port>,MYSQL_ATTR_SSL_CA=/etc/ssl/certs/aiven-mysql.pem,… \
  --set-secrets=DB_PASSWORD=…
```

Differences from the Cloud SQL path:

* `mysql: DROP DATABASE taskflow; CREATE DATABASE …` — you own the database; Aiven
  hands you a pre-created one and does not let root create databases.
* Backups are Aiven's (or your own `mysqldump` to an Aiven bucket); there is no
  `gcloud sql export/import`. `scripts/backup.sh` and `scripts/restore.sh` would
  need Aiven-specific equivalents — the runbook above does **not** apply as is.
* Redis has no IAM either: `REDIS_HOST` + `REDIS_PASSWORD` + TLS. Memorystore's
  `REDIS_CACHE_DB`/`REDIS_DB` split works unchanged.
* Free plan limits (1 vCPU, 5 GB disk, capped connections) are real constraints,
  not a starting point.

## Appendix B — known gaps

1. **Uploads are lost on scale-to-zero.** `config/filesystems.php` hardcodes the
   `attachments` and `local` disk roots to `storage_path('app/…')`, with no env
   override. On Cloud Run that is the container filesystem: task attachments and
   avatars disappear with the instance. Fixing it needs either a config change
   (not this issue's file) or a Cloud Storage FUSE sidecar, plus
   `AWS_*`-style credentials for the existing `s3` disk. **Open issue needed —
   flagged, not worked around.**
2. **The queue worker has no home yet.** `queue:work` is long-running, so it
   needs a Cloud Run *service* with `--no-timeout`-style settings or a job with
   `--task-count=-1` and no timeout. Owned by the Cloud Run service (#21), noted
   here because it consumes the same Redis queue.
3. **Redis has no managed HA story for a 1 GiB instance.** Memorystore
   Standard Tier is a single node with a 4-hour maintenance window that briefly
   fails over. Acceptable at this size; the app degrades to cache misses and
   delayed mail rather than errors, except for the session.
