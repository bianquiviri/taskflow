#!/bin/sh
# Logical backup of the TaskFlow database to Cloud Storage.
#
# Automated Cloud SQL backups + PITR are the first line of defence (see
# provision-cloudsql.sh). This script is the second: a portable dump that
# survives the instance itself and that a restore drill can be rehearsed
# against. Run it daily from Cloud Scheduler through the same Cloud Run Job the
# migrations use.
#
# `gcloud sql export sql` is a managed operation: Cloud SQL streams the dump
# straight into Cloud Storage, so the runner needs no `mysqldump` and no
# inbound network access to the instance. It needs roles/cloudsql.client plus
# write access to the bucket:
#
#   gsutil iam ch gs:roles/storage.objectCreator <service-account>
#   gsutil iam ch -u <sa>@<project>.iam.gserviceaccount.com \
#       gs:roles/storage.admin gs://<bucket>
#
# Usage:
#   GCP_PROJECT=my-project GCP_REGION=europe-west1 ./deploy/data/scripts/backup.sh
#   ... BACKUP_BUCKET=my-bucket BACKUP_PREFIX=daily
#   BACKUP_DRY_RUN=1 ./deploy/data/scripts/backup.sh   # print the plan only
#
set -eu

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
# shellcheck source=../env.sh
. "$script_dir/../env.sh"
# shellcheck source=lib.sh
. "$script_dir/lib.sh"

: "${BACKUP_PREFIX:=daily}"
: "${BACKUP_KEEP:=14}" # objects per prefix kept by the cleanup below

timestamp=$(date -u +%Y%m%dT%H%M%SZ)
prefix_uri="gs://${BACKUP_BUCKET}/${BACKUP_PREFIX}"

log "source : Cloud SQL ${SQL_INSTANCE} / database ${DB_NAME} (project ${GCP_PROJECT})"
log "target : ${prefix_uri}/${SQL_INSTANCE}-${DB_NAME}-${timestamp}.sql.gz"

if [ "${BACKUP_DRY_RUN:-0}" = "1" ]; then
    log "dry run: would export, verify the dump, then prune to ${BACKUP_KEEP} objects"
    exit 0
fi

require_project
command -v gsutil >/dev/null 2>&1 || die "gsutil not found (it ships with the gcloud SDK)"

# `--gzip` moved around between gcloud versions and Cloud SQL gzips MySQL
# exports by default: only pass the flag when this gcloud knows it, and let
# dump_reader() decide how to read the result.
export_flags=''
if gcloud sql export sql --help 2>/dev/null | grep -q -- '--gzip'; then
    export_flags='--gzip'
    log "export: compressed (--gzip)"
else
    log "export: no --gzip flag in this gcloud, relying on the default"
fi

object="${prefix_uri}/${SQL_INSTANCE}-${DB_NAME}-${timestamp}.sql.gz"

# shellcheck disable=SC2086 # export_flags is intentionally word-split
if ! gcloud sql export sql "$SQL_INSTANCE" "$object" \
    --database="$DB_NAME" \
    --project="$GCP_PROJECT" \
    $export_flags \
    --quiet; then
    die "export failed. Is gs://${BACKUP_BUCKET} in this project and writable by the caller (roles/storage.objectCreator)?"
fi

log "verifying the dump before declaring it a backup"
size=$(gsutil ls -l "$object" | awk '{print $3}')
[ -n "$size" ] || die "cannot stat ${object}: wrong project or missing gsutil permissions"
[ "$size" -gt 1024 ] || die "${object} is ${size} bytes: an export that small is not a usable backup"

tmp=$(mktemp)
trap 'rm -f "$tmp"' EXIT INT TERM
gsutil cat "$object" >"$tmp"

reader=$(dump_reader "$tmp")
log "dump format: ${reader%% *}"

# `grep -c` exits 1 when it counts nothing, which `set -e` would turn into a
# silent early exit: keep the 0 and decide explicitly.
tables=$($reader "$tmp" | grep -c 'CREATE TABLE' || true)
migrations=$($reader "$tmp" | grep -c 'INSERT INTO `migrations`' || true)
users=$($reader "$tmp" | grep -c 'INSERT INTO `users`' || true)

log "size      : ${size} bytes"
log "tables    : ${tables}"
log "migrations: ${migrations} rows"
log "users     : ${users} rows"

[ "${tables:-0}" -gt 0 ] || die "${object} contains no CREATE TABLE statement: this is not a usable backup"

log ""
log "pruning ${prefix_uri} to the newest ${BACKUP_KEEP} objects"
# Sort *descending* first, then skip the first BACKUP_KEEP entries: the names are
# UTC timestamps, so reverse order is newest first. `head -n -N` is a GNU-ism and
# fails on macOS, hence the awk. (Sorting ascending here would keep the OLDEST
# dumps and delete the fresh one, which is the opposite of a retention policy.)
gsutil ls "${prefix_uri}/" |
    sort -r |
    awk -v keep="$BACKUP_KEEP" 'NR <= keep { next } { print }' |
    while read -r old; do
        log "  deleting $old"
        gsutil rm "$old"
    done

log "OK: ${object}"
log ""
log "rehearse a restore (into a scratch database, never over production):"
log "  GCP_PROJECT=${GCP_PROJECT} ./deploy/data/scripts/restore.sh --verify-only ${object}"
