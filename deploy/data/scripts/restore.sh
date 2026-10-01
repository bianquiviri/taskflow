#!/bin/sh
# Restore the TaskFlow database from a Cloud Storage dump.
#
# Two modes, and the safe one is the default:
#
#   --verify-only   download + structurally validate the dump, change nothing
#                   (use this for a scheduled restore drill)
#   --into NAME     import into a scratch database `NAME` on the same instance
#   --production    import into the live database. Destructive: it drops and
#                   recreates it. Writes must be stopped first, see the README.
#
# Import is a managed operation (`gcloud sql import sql`) so no mysqldump and no
# inbound connectivity is required — the same posture as backup.sh.
#
# Usage:
#   GCP_PROJECT=my-project ./deploy/data/scripts/restore.sh --verify-only gs://b/taskflow-daily/taskflow-mysql-20260928T030000Z.sql.gz
#   GCP_PROJECT=my-project ./deploy/data/scripts/restore.sh --into taskflow_restore gs://b/...
#   GCP_PROJECT=my-project ./deploy/data/scripts/restore.sh --production gs://b/...
#
set -eu

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
# shellcheck source=../env.sh
. "$script_dir/../env.sh"
# shellcheck source=lib.sh
. "$script_dir/lib.sh"

mode=verify
target_db=''

while [ "$#" -gt 0 ]; do
    case "$1" in
        --verify-only)
            mode=verify
            shift
            ;;
        --into)
            mode=scratch
            [ "$#" -ge 2 ] || die "--into needs a database name"
            target_db=$2
            shift 2
            ;;
        --production)
            mode=production
            shift
            ;;
        -h | --help)
            sed -n '2,22p' "$0"
            exit 0
            ;;
        *)
            break
            ;;
    esac
done

object=${1:-}
[ -n "$object" ] || die "usage: restore.sh [--verify-only|--into NAME|--production] gs://bucket/object.sql.gz"
case "$object" in
    gs://*) : ;;
    *) die "the dump must be a Cloud Storage object (gs://…)" ;;
esac

sql_instance_exists || die "no Cloud SQL instance named $SQL_INSTANCE in $GCP_PROJECT"
require_project
command -v gsutil >/dev/null 2>&1 || die "gsutil not found (it ships with the gcloud SDK)"

tmp=$(mktemp)
trap 'rm -f "$tmp"' EXIT INT TERM

# --- 1. Download and validate ----------------------------------------------
log "downloading $object"
gsutil cp "$object" "$tmp"
size=$(wc -c <"$tmp" | tr -d ' ')
[ "${size:-0}" -gt 1024 ] || die "the dump is ${size} bytes: too small to be a real database"

reader=$(dump_reader "$tmp")
log "format: ${reader%% *}, ${size} bytes"

tables=$($reader "$tmp" | grep -c 'CREATE TABLE' || true)
migrations=$($reader "$tmp" | grep -c 'INSERT INTO `migrations`' || true)
users=$($reader "$tmp" | grep -c 'INSERT INTO `users`' || true)
log "tables=${tables} migrations=${migrations} users=${users}"

[ "${tables:-0}" -gt 0 ] ||
    die "no CREATE TABLE in the dump. It is either the wrong file or a truncated export."

# The migrations table is what `php artisan migrate` reads; without it Laravel
# would happily re-run every migration against a restored database.
[ "${migrations:-0}" -gt 0 ] ||
    warn "no rows in the \`migrations\` table: php artisan migrate would replay every migration. Restore, then inspect before pointing traffic at it."

log "the dump is structurally valid"

if [ "$mode" = "verify" ]; then
    log "--verify-only: nothing was written. This is the scheduled drill."
    exit 0
fi

# --- 2. Import --------------------------------------------------------------
if [ "$mode" = "scratch" ]; then
    [ -n "$target_db" ] || die "--into needs a database name"
    log "creating scratch database $target_db"
    printf 'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s;' \
        "$target_db" "$DB_CHARSET" "$DB_COLLATION" | sql_exec_as_root
    import_db=$target_db
else
    log "PRODUCTION restore into $DB_NAME"
    warn "this drops and recreates $DB_NAME. Stop the writers first:"
    warn "  gcloud run services update $APP_SERVICE --region $GCP_REGION --no-traffic"
    warn "  (…and stop the queue worker job, otherwise it keeps writing)"
    if [ "${RESTORE_CONFIRM:-}" != "$DB_NAME" ]; then
        die "refusing to touch production. Re-run with RESTORE_CONFIRM=$DB_NAME once traffic is drained."
    fi
    # Importing *over* a populated database leaves rows that the dump does not
    # contain (a user deleted before the dump was taken would come back, a user
    # created after it would survive) and can fail outright on duplicate keys.
    # A restore has to be a clean replacement, so the database is dropped and
    # recreated with the same charset/collation the app expects, and only then
    # imported. The grants live at the database level and go with it.
    log "dropping and recreating $DB_NAME (as the instance root)"
    {
        printf 'DROP DATABASE IF EXISTS `%s`;\n' "$DB_NAME"
        printf 'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s;\n' \
            "$DB_NAME" "$DB_CHARSET" "$DB_COLLATION"
        printf "GRANT ALL PRIVILEGES ON \`%s\`.* TO '%s'@'%%';\n" "$DB_NAME" "$DB_USER"
        printf "GRANT CREATE ROUTINE, ALTER ROUTINE, EXECUTE ON \`%s\`.* TO '%s'@'%%';\n" \
            "$DB_NAME" "$DB_USER"
    } | sql_exec_as_root
    import_db=$DB_NAME
fi

log "importing into $import_db (this takes a while)"
if ! gcloud sql import sql "$SQL_INSTANCE" "$tmp" \
    --database="$import_db" \
    --project="$GCP_PROJECT" \
    --quiet; then
    die "import failed. Check the dump's charset/collation matches $DB_CHARSET/$DB_COLLATION and that the role owns the target database."
fi

if [ "$mode" = "scratch" ]; then
    log "verifying the imported scratch database"
    log "  tables: $(sql_scalar "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${target_db}';")"
    log "  migrations: $(sql_scalar "SELECT COUNT(*) FROM ${target_db}.migrations;")"
    log ""
    log "point the app at it to rehearse a full recovery:"
    log "  gcloud run jobs update $RUN_MIGRATE_JOB --region $GCP_REGION \\"
    log "    --update-env-vars=DB_DATABASE=${target_db}"
    log "and then delete it: printf 'DROP DATABASE \`%s\`;' | scripts/… (or the Cloud SQL console)"
    exit 0
fi

# --- 3. Post-restore checks, then roll forward ------------------------------
log "verifying the restored production database"
log "  tables   : $(sql_scalar "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_NAME}';")"
log "  charset  : $(sql_scalar "SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${DB_NAME}';")"
log "  collation: $(sql_scalar "SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${DB_NAME}';")"

log ""
log "next steps (see deploy/data/README.md § Restore):"
log "  1. roll the schema forward:  gcloud run jobs execute $RUN_MIGRATE_JOB --region $GCP_REGION"
log "  2. smoke test while traffic is still 0%: curl -fsS https://<service>/up"
log "  3. re-enable traffic:       gcloud run services update $APP_SERVICE --region $GCP_REGION --min-instances=1"
log "  4. restart the queue worker job so it picks up the restored queue"
