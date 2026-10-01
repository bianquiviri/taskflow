#!/bin/sh
# Managed data services contract for TaskFlow production.
#
# This file is the single source of truth for the managed MySQL / Redis
# provisioning and for the env vars the Cloud Run service and the Cloud Run Job
# must receive. Both `deploy/data/scripts/*` and
# `.github/workflows/db-migrate.yml` source it, so the mapping cannot drift
# between the provision scripts and the deploy.
#
# Nothing secret is defined here: only secret NAMES. The payload lives in
# Secret Manager and is resolved by the Cloud Run service account at container
# start (see deploy/data/README.md).
#
# The third field of every entry names the config key that reads the variable,
# so every line can be checked against config/*.php by eye.
#
#   load_data_env            # set every TASKFLOW_* variable (shell)
#   data_env_file PATH       # write the non-secret vars to a dotenv file
#   data_secret_bindings     # "<env var>=<secret id>:latest" pairs for gcloud
#   data_secret_bindings_gh  # "env=SECRET" pairs for GitHub Actions
#
# shellcheck shell=sh

# --- Values that differ per project/instance -------------------------------
: "${GCP_PROJECT:?GCP_PROJECT must be set (the Google Cloud project id)}"
: "${GCP_REGION:?GCP_REGION must be set (e.g. europe-west1)}"
: "${DB_NAME:=taskflow}"
: "${DB_USER:=taskflow}"
: "${SQL_INSTANCE:=taskflow-mysql}"
# `gcloud sql instances create --database-version`. The MySQL 8.0 line tracks the
# latest 8.0.x patch, so this is a major version, never a patch pin.
: "${SQL_DATABASE_VERSION:=MYSQL_8_0}"
: "${REDIS_INSTANCE:=taskflow-redis}"
: "${RUN_JOBS_NAME:=taskflow-jobs}"
# The identity the Cloud Run service and the Jobs run as. It needs
# roles/cloudsql.client + roles/secretmanager.secretAccessor, and it is the
# service account the GitHub Actions workload identity impersonates
# (secrets.GCP_SERVICE_ACCOUNT must be the same address). The project's default
# compute service account is only a sane default.
: "${RUN_SERVICE_ACCOUNT:=${GCP_PROJECT}-compute@developer.gserviceaccount.com}"
: "${RUN_MIGRATE_JOB:=taskflow-migrate}"
: "${RUN_QUEUE_JOB:=taskflow-queue}"
: "${APP_SERVICE:=taskflow}"

# Secret Manager names. Referenced as projects/$GCP_PROJECT/secrets/<name>.
: "${SECRET_APP_KEY:=taskflow-app-key}"
# The instance root password is a separate secret on purpose: the application's
# role must not be able to take over the instance (a leaked DB_PASSWORD must
# not be a leaked root).
: "${SECRET_DB_ROOT_PASSWORD:=taskflow-db-root-password}"
: "${SECRET_DB_PASSWORD:=taskflow-db-password}"
: "${SECRET_REDIS_PASSWORD:=taskflow-redis-password}"

# How the application reaches MySQL and Redis.
#   cloudsql-connector  unix socket mounted by `--add-cloudsql-instances`
#                       (Cloud SQL connector, no VPC, TLS in transit)
#   vpc-connector       private IP over a Serverless VPC Access connector
#   public-ip           public IP + authorised networks
: "${MYSQL_ACCESS:=cloudsql-connector}"
: "${REDIS_ACCESS:=vpc-connector}"

# Optional: restrict the public IP to a known egress CIDR (authorised network).
: "${AUTHORISED_NETWORK:=}"

# Memorystore has no public option: it always lives in a VPC network. With
# REDIS_ACCESS=vpc-connector (the default) that is the network the Serverless VPC
# Access connector is attached to, so it has to exist before provisioning — see
# deploy/data/README.md §2 for the network + peering commands.
: "${VPC_NETWORK:=taskflow-vpc}"
: "${REDIS_CONNECT_MODE:=direct-peering}"
# Optional: an explicit CIDR for the Memorystore peering. Empty = Google picks
# one from the peering range, which fails if the default /20 collides with
# something already in the VPC.
: "${REDIS_IP_RANGE:=}"

# Where backups are written (see backup.sh).
: "${BACKUP_BUCKET:=${GCP_PROJECT}-taskflow-backups}"
# Automated backups. Cloud SQL expresses the point-in-time recovery window in
# *days* (`--retained-transaction-log-days`) and the number of automated dumps
# separately (`--retained-backups-count`); there is no hours flag.
: "${BACKUP_RETENTION_DAYS:=7}"
: "${BACKUP_RETAINED_COUNT:=7}"
: "${BACKUP_START_TIME:=03:00}"

# --- Read-only facts, asserted against config/*.php -------------------------
# Everything below is verified against the committed configuration:
#
#   config/database.php  DB_CONNECTION, DB_SOCKET (socket wins over host/port
#                        via MySqlConnector::getDsn), DB_HOST, DB_PORT,
#                        DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_CHARSET,
#                        DB_COLLATION, MYSQL_ATTR_SSL_CA
#   config/cache.php    CACHE_STORE
#   config/session.php  SESSION_DRIVER
#   config/queue.php    QUEUE_CONNECTION, REDIS_QUEUE, REDIS_QUEUE_RETRY_AFTER
#   config/database.php REDIS_CLIENT, REDIS_HOST, REDIS_PORT, REDIS_DB,
#                        REDIS_CACHE_DB (config/database.php redis.cache)
#
# Every table Laravel creates gets `DB_CHARSET`/`DB_COLLATION` from the
# *connection* (MySqlGrammar::compileCreateEncoding falls back to the connection
# config), so these two — not the database default — decide the collation of the
# schema. The database is still created with the same pair so that anything a
# migration does not create (a restored dump, a hand-made table) matches too: a
# mix of utf8mb4_unicode_ci and utf8mb4_0900_ai_ci columns is the classic source
# of "Illegal mix of collations" and of `->json()` (activity_logs.meta)
# failures. Do not override either in production without rebuilding every table.
: "${DB_CHARSET:=utf8mb4}"
: "${DB_COLLATION:=utf8mb4_unicode_ci}"

: "${CACHE_STORE:=redis}"
: "${SESSION_DRIVER:=redis}"
: "${QUEUE_CONNECTION:=redis}"
: "${REDIS_CLIENT:=phpredis}"
: "${REDIS_QUEUE:=default}"
: "${REDIS_QUEUE_RETRY_AFTER:=90}"
: "${REDIS_DB:=0}"
: "${REDIS_CACHE_DB:=1}"

# Non-secret env vars of the Cloud Run service. `--set-secrets` injects the
# three secret payloads on top of this; that split is deliberate.
data_env_vars() {
    cat <<EOF
APP_NAME=TaskFlow
APP_ENV=production
APP_DEBUG=false
APP_URL=${APP_URL:-https://taskflow.example.com}
LOG_CHANNEL=stderr
LOG_LEVEL=info
DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_CHARSET=${DB_CHARSET}
DB_COLLATION=${DB_COLLATION}
CACHE_STORE=${CACHE_STORE}
SESSION_DRIVER=${SESSION_DRIVER}
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=${QUEUE_CONNECTION}
REDIS_CLIENT=${REDIS_CLIENT}
REDIS_HOST=${REDIS_HOST:-127.0.0.1}
REDIS_PORT=${REDIS_PORT:-6379}
REDIS_DB=${REDIS_DB}
REDIS_CACHE_DB=${REDIS_CACHE_DB}
REDIS_QUEUE=${REDIS_QUEUE}
REDIS_QUEUE_RETRY_AFTER=${REDIS_QUEUE_RETRY_AFTER}
MAIL_MAILER=${MAIL_MAILER:-log}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-noreply@taskflow.example.com}
MAIL_FROM_NAME=TaskFlow
EOF
}

# Database / cache / session: where does MySQL live?
# Echoes the MySQL access mode plus, for cloudsql-connector, the socket path
# Cloud Run mounts (`--add-cloudsql-instances`).
mysql_access_summary() {
    case "$MYSQL_ACCESS" in
        cloudsql-connector)
            echo "cloudsql-connector ${RUN_JOBS_NAME}: DB_SOCKET=/cloudsql/${GCP_PROJECT}:${GCP_REGION}:${SQL_INSTANCE}"
            ;;
        vpc-connector | public-ip)
            echo "${MYSQL_ACCESS}: DB_HOST=<instance host> (DB_SOCKET empty)"
            ;;
        *)
            echo "unknown MYSQL_ACCESS=$MYSQL_ACCESS" >&2
            return 1
            ;;
    esac
}

redis_access_summary() {
    case "$REDIS_ACCESS" in
        cloudsql-connector)
            echo "cloudsql-connector ${RUN_JOBS_NAME}: redis over the SQL proxy port map (no VPC)"
            ;;
        vpc-connector)
            echo "vpc-connector: redis over the VPC, REDIS_HOST=<memorystore host>"
            ;;
        *)
            echo "unknown REDIS_ACCESS=$REDIS_ACCESS" >&2
            return 1
            ;;
    esac
}

data_env_file() {
    _file=$1
    data_env_vars >"$_file"
    chmod 600 "$_file"
    echo "wrote non-secret env vars to $_file" >&2
    echo "add the secret payloads yourself (they are never written to disk by this repo):" >&2
    echo "  APP_KEY=$(data_secret_payload_command APP_KEY)" >&2
}

# `--set-secrets` form for `gcloud run deploy/update`: the runtime resolves the
# secret version, so no payload ever appears on a command line.
data_secret_bindings() {
    echo "APP_KEY=projects/${GCP_PROJECT}/secrets/${SECRET_APP_KEY}:latest"
    echo "DB_PASSWORD=projects/${GCP_PROJECT}/secrets/${SECRET_DB_PASSWORD}:latest"
    echo "REDIS_PASSWORD=projects/${GCP_PROJECT}/secrets/${SECRET_REDIS_PASSWORD}:latest"
}

# Never injected into the service: only used by the operator to bootstrap the
# instance (scripts/provision-cloudsql.sh, restore into an empty database).
data_secret_root_binding() {
    echo "DB_ROOT_PASSWORD=projects/${GCP_PROJECT}/secrets/${SECRET_DB_ROOT_PASSWORD}:latest"
}

# GitHub Actions form (`--set-secrets NAME=SECRET`).
data_secret_bindings_gh() {
    echo "APP_KEY=${SECRET_APP_KEY}"
    echo "DB_PASSWORD=${SECRET_DB_PASSWORD}"
    echo "REDIS_PASSWORD=${SECRET_REDIS_PASSWORD}"
}

# Helper text only: prints where the payload has to be added. Never prints a
# payload itself.
data_secret_payload_command() {
    case "$1" in
        APP_KEY) echo "(value of Secret Manager secret ${SECRET_APP_KEY})" ;;
        DB_PASSWORD) echo "(value of Secret Manager secret ${SECRET_DB_PASSWORD})" ;;
        REDIS_PASSWORD) echo "(value of Secret Manager secret ${SECRET_REDIS_PASSWORD})" ;;
        *) echo "(no secret for $1)" ;;
    esac
}
