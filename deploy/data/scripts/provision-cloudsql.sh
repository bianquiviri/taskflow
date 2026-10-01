#!/bin/sh
# Provision the managed data services TaskFlow production runs on.
#
#   Cloud SQL for MySQL 8.0   (no VPC needed for Cloud Run: the built-in
#                             connector mounts /cloudsql/<connection name>)
#   Memorystore for Redis 7   (reached over a Serverless VPC Access connector;
#                             the migration Job uses the SQL proxy port map
#                             instead, see deploy/data/README.md)
#   Secret Manager            (three secrets, created if missing)
#
# Idempotent: safe to re-run. It never rotates an existing secret password and
# never drops anything; a mismatch is reported instead.
#
# Usage:
#   GCP_PROJECT=my-project GCP_REGION=europe-west1 ./deploy/data/scripts/provision-cloudsql.sh
#
# Optional:
#   MYSQL_ACCESS=cloudsql-connector|vpc-connector|public-ip
#   AUTHORISED_NETWORK=203.0.113.0/24   (MYSQL_ACCESS=public-ip only)
#   SQL_TIER=db-custom-1-4096  REDIS_MEMORY_GB=1  BACKUP_RETENTION_DAYS=7
#   BACKUP_START_TIME=03:00  DB_NAME=taskflow  DB_USER=taskflow
#   RUN_SERVICE_ACCOUNT=sa@project.iam.gserviceaccount.com  BACKUP_BUCKET=my-bucket
#
set -eu

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
# shellcheck source=../env.sh
. "$script_dir/../env.sh"
# shellcheck source=lib.sh
. "$script_dir/lib.sh"

: "${SQL_TIER:=db-custom-1-4096}"
: "${SQL_EDITION:=ENTERPRISE}"
: "${SQL_DATABASE_VERSION:=MYSQL_8_0}"
: "${SQL_AVAILABILITY_ZONE:=}"
: "${SQL_STORAGE_SIZE_GB:=10}"
: "${REDIS_MEMORY_GB:=1}"
: "${REDIS_VERSION:=redis_7_0}"

require_project

log "project=$GCP_PROJECT region=$GCP_REGION"
log "instance=$SQL_INSTANCE version=$SQL_DATABASE_VERSION tier=$SQL_TIER"
log "redis=$REDIS_INSTANCE version=$REDIS_VERSION memory=${REDIS_MEMORY_GB}GiB"

# --- 0. Secrets first: the database password is needed to create the role ----
"$script_dir/secrets.sh"

# --- 1. Cloud SQL instance --------------------------------------------------
if sql_instance_exists; then
    log "Cloud SQL instance already exists: $SQL_INSTANCE"
else
    log "creating Cloud SQL instance (this takes a few minutes)…"
    # Flag names below are the ones in the `gcloud sql instances create`
    # reference: --database-version (not --engine-version),
    # --retained-backups-count / --retained-transaction-log-days (PITR window in
    # *days*, not a --retain-backups-hours).
    set -- --database-version="$SQL_DATABASE_VERSION" --edition="$SQL_EDITION" \
        --tier="$SQL_TIER" --region="$GCP_REGION" \
        --storage-size="${SQL_STORAGE_SIZE_GB}GB" \
        --availability-type=regional \
        --backup-start-time="$BACKUP_START_TIME" \
        --retained-transaction-log-days="$BACKUP_RETENTION_DAYS" \
        --retained-backups-count="$BACKUP_RETAINED_COUNT" \
        --enable-point-in-time-recovery \
        --retain-backups-on-delete \
        --enable-auto-upgrade-minor-version \
        --deletion-protection \
        --no-assign-ip \
        --root-password="$(secret_payload "$SECRET_DB_ROOT_PASSWORD")"
    if [ -n "$SQL_AVAILABILITY_ZONE" ]; then
        set -- "$@" --zone="$SQL_AVAILABILITY_ZONE"
    fi
    gcloud sql instances create "$SQL_INSTANCE" --project "$GCP_PROJECT" "$@" >/dev/null
    log "instance created: $(sql_instance_connection_name)"
fi

if [ "$MYSQL_ACCESS" = "public-ip" ]; then
    if [ -z "$(sql_instance_ip)" ]; then
        log "assigning a public IP to the instance"
        gcloud sql instances connect "$SQL_INSTANCE" --project "$GCP_PROJECT" >/dev/null
    fi
    if [ -n "$AUTHORISED_NETWORK" ]; then
        log "authorising network $AUTHORISED_NETWORK"
        gcloud sql instances connect "$SQL_INSTANCE" \
            --authorized-networks="$AUTHORISED_NETWORK" --project "$GCP_PROJECT" >/dev/null
    fi
fi

# --- 2. Backups -------------------------------------------------------------
# Automated backups + PITR are a restore requirement, not an optimisation, so
# they are enforced here rather than assumed: creation sets them, and an
# existing instance is patched to match.
if [ "${BACKUP_FORCE:-1}" = "1" ]; then
    gcloud sql instances patch "$SQL_INSTANCE" --project "$GCP_PROJECT" \
        --backup-start-time="$BACKUP_START_TIME" \
        --retained-transaction-log-days="$BACKUP_RETENTION_DAYS" \
        --retained-backups-count="$BACKUP_RETAINED_COUNT" \
        --enable-point-in-time-recovery >/dev/null
fi
_settings=$(gcloud sql instances describe "$SQL_INSTANCE" --project "$GCP_PROJECT" \
    --format='value(settings.backupStartTime,settings.retainedTransactionLogDays,settings.retainedBackupsCount,settings.enablePointInTimeRecovery)')
log "backup settings (startTime|pitrDays|backupsCount|pitrEnabled): $_settings"
case "$_settings" in
    *"$BACKUP_RETENTION_DAYS"*) : ;;
    *) warn "PITR window is not ${BACKUP_RETENTION_DAYS}d; a restore drill will age out sooner than documented" ;;
esac

# The offline export target for deploy/data/scripts/backup.sh. `gcloud sql export`
# writes straight to Cloud Storage, so the bucket has to exist first; created
# idempotently here with uniform bucket-level access (no per-object ACLs).
if gcloud storage buckets describe "gs://${BACKUP_BUCKET}" --project "$GCP_PROJECT" >/dev/null 2>&1; then
    log "backup bucket exists: gs://$BACKUP_BUCKET"
else
    log "creating backup bucket gs://$BACKUP_BUCKET in $GCP_REGION"
    gcloud storage buckets create "gs://${BACKUP_BUCKET}" \
        --project="$GCP_PROJECT" \
        --location="$GCP_REGION" \
        --uniform-bucket-level-access \
        --public-access-prevention >/dev/null
fi
log "note: keep a copy outside this region (backup.sh writes here with gsutil; gcloud storage cp is the modern equivalent)"

# --- 3. Database and application role ---------------------------------------
# Both need the instance root once, on a fresh instance. There is no IAM
# shortcut: `gcloud sql users create` only makes a *proxy* user (no MySQL
# grants, useless to PDO) and the unix socket refuses root. After this block
# everything runs as the application role.
if sql_user_can_connect; then
    log "role '$DB_USER' authenticates with the password in '$SECRET_DB_PASSWORD'"

    if db_exists; then
        log "database already exists: $DB_NAME"
    else
        log "creating database $DB_NAME (CHARACTER SET $DB_CHARSET COLLATE $DB_COLLATION)"
        printf 'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s;' \
            "$DB_NAME" "$DB_CHARSET" "$DB_COLLATION" | sql_exec_as_root
    fi
else
    log "first run: creating database, role and grants (as the instance root)"
    db_password=$(secret_payload "$SECRET_DB_PASSWORD")
    cat <<SQL | sql_exec_as_root
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET $DB_CHARSET COLLATE $DB_COLLATION;
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$db_password';
ALTER USER '$DB_USER'@'%' IDENTIFIED BY '$db_password';
-- migrate dumps the schema (SHOW CREATE VIEW) and can touch routines; a plain
-- GRANT ALL ON \`db\`.* does not include the routine privileges.
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
GRANT CREATE ROUTINE, ALTER ROUTINE, EXECUTE ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
SQL
    log "role '$DB_USER' created"
fi

# Assert what the app depends on instead of trusting the run above.
version=$(sql_scalar 'SELECT VERSION();')
charset=$(sql_scalar "SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${DB_NAME}';")
collation=$(sql_scalar "SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${DB_NAME}';")
log "server version : $version"
log "database       : $DB_NAME ($charset / $collation)"

[ "$charset" = "$DB_CHARSET" ] || die "database charset is '$charset', expected '$DB_CHARSET'"
# Tables get their collation from the *connection* (DB_CHARSET/DB_COLLATION in
# config/database.php), not from this database default — but a dump, a restored
# database or a hand-made table would land on whatever this says, so it still
# has to agree.
[ "$collation" = "$DB_COLLATION" ] ||
    die "database collation is '$collation', expected '$DB_COLLATION' (restored dumps and manually created tables would inherit it)"

# The dev stack runs MySQL 8.4; production must not go below 8.0 (see the
# upgrade caveat in deploy/data/README.md).
version_number=$(printf '%s' "$version" | awk -F. '{ printf "%d%02d%02d", $1, $2, $3 }')
[ "$version_number" -ge 80000 ] || die "MySQL >= 8.0 required (found '$version')"

# `strict => true` in config/database.php runs every write under
# STRICT_TRANS_TABLES, so a bad utf8mb4 column fails at insert time, not at boot.
sql_mode=$(sql_scalar 'SELECT @@SESSION.sql_mode;')
case "$sql_mode" in
    *STRICT_TRANS_TABLES*) log "sql_mode: STRICT_TRANS_TABLES active" ;;
    *) warn "sql_mode does not include STRICT_TRANS_TABLES: $sql_mode" ;;
esac

# --- 4. Grants are part of the contract --------------------------------------
grants=$(sql_scalar "SELECT COUNT(*) FROM information_schema.user_privileges WHERE grantee LIKE '${DB_USER}@%';")
log "grants recorded for '$DB_USER': $grants"
[ "${grants:-0}" -gt 0 ] 2>/dev/null || warn "no grants found for '$DB_USER'; migrate will fail"

# --- 5. Memorystore ---------------------------------------------------------
# Memorystore has no public-IP option: the instance always joins a VPC network
# (default "default", which is never where the VPC connector lives), so the
# network is checked first and named explicitly. AUTH is enabled, so
# REDIS_PASSWORD is a real value in production.
if redis_instance_exists; then
    log "Memorystore instance already exists: $REDIS_INSTANCE"
else
    if ! gcloud compute networks describe "$VPC_NETWORK" --project "$GCP_PROJECT" >/dev/null 2>&1; then
        die "VPC network '$VPC_NETWORK' does not exist. Memorystore must live in a VPC; create it and its peering first (deploy/data/README.md §2), or set VPC_NETWORK=."
    fi
    log "creating Memorystore instance in $VPC_NETWORK…"
    set -- --project="$GCP_PROJECT" --region="$GCP_REGION" \
        --network="$VPC_NETWORK" \
        --connect-mode="$REDIS_CONNECT_MODE" \
        --size="$REDIS_MEMORY_GB" --redis-version="$REDIS_VERSION" \
        --transit-encryption-mode=server-authentication \
        --enable-auth
    if [ -n "$REDIS_IP_RANGE" ]; then
        set -- "$@" --reserved-ip-range="$REDIS_IP_RANGE"
    fi
    gcloud redis instances create "$REDIS_INSTANCE" "$@" >/dev/null
fi
log "redis host: $(redis_instance_host):$(redis_instance_port)"

# --- 6. IAM: the Cloud Run service account reads the secrets and the instance -
run_sa="$RUN_SERVICE_ACCOUNT"
gcloud iam service-accounts add-iam-policy-binding "$run_sa" \
    --project="$GCP_PROJECT" \
    --member="serviceAccount:$run_sa" \
    --role="roles/cloudsql.client" >/dev/null
gcloud projects add-iam-policy-binding "$GCP_PROJECT" \
    --member="serviceAccount:$run_sa" \
    --role="roles/secretmanager.secretAccessor" >/dev/null
log "IAM: $run_sa has roles/cloudsql.client + roles/secretmanager.secretAccessor"
log "note: set RUN_SERVICE_ACCOUNT if the Cloud Run service runs as a dedicated (non-default) service account"

# --- 7. Report the resolved contract ---------------------------------------
log "access summary: $(mysql_access_summary)"
log "access summary: $(redis_access_summary)"
log ""
log "the Cloud Run service and job must be deployed with:"
log "  --add-cloudsql-instances=$(sql_instance_connection_name)"
log "  --set-secrets=$(comma_join \
        "APP_KEY=$(secret_id "$SECRET_APP_KEY"):latest" \
        "DB_PASSWORD=$(secret_id "$SECRET_DB_PASSWORD"):latest" \
        "REDIS_PASSWORD=$(secret_id "$SECRET_REDIS_PASSWORD"):latest")"
log "and the non-secret env vars from 'deploy/data/env.sh' (data_env_vars)."
log ""
log "next: ./deploy/data/scripts/verify-connection.sh"
