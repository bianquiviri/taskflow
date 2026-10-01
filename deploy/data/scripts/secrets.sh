#!/bin/sh
# Create (or adopt) the Secret Manager secrets TaskFlow production needs, and
# print the contract the Cloud Run service is deployed with.
#
#   taskflow-app-key          APP_KEY         Laravel AES-256-CBC key
#   taskflow-db-root-password (never injected) Cloud SQL instance root
#   taskflow-db-password      DB_PASSWORD     application MySQL role
#   taskflow-redis-password   REDIS_PASSWORD  Memorystore AUTH
#
# Why a secret per value: `gcloud run deploy --set-secrets APP_KEY=...:latest`
# resolves the version at container start, so no payload is ever written to a
# dotenv file, committed, or passed on a command line. See deploy/data/README.md.
#
# Idempotent. NEVER rotates an existing secret on its own — rotation is an
# ordered operation that touches the database, the running containers and the
# secret version in that order (deploy/data/README.md § Rotating a secret), so
# a surprise rotation cannot lock the app out of its own database.
#
# Usage:
#   GCP_PROJECT=my-project GCP_REGION=europe-west1 ./deploy/data/scripts/secrets.sh
#   ./deploy/data/scripts/secrets.sh --print   # contract only, no gcloud calls
#
set -eu

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
# shellcheck source=../env.sh
. "$script_dir/../env.sh"
# shellcheck source=lib.sh
. "$script_dir/lib.sh"

print_only=0
if [ "${1:-}" = "--print" ]; then
    print_only=1
fi

# Generates a secret payload without ever logging it.
#
# APP_KEY is generated with the framework itself so the format is guaranteed
# (`base64:` + 32 random bytes decoded), and the other two are random
# alphanumeric strings: no shell-special character can break the SQL statements
# in provision-cloudsql.sh or a dotenv file.
generate_payload() {
    case "$1" in
        app-key)
            php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;' 2>/dev/null ||
                printf 'base64:%s\n' "$(openssl rand -base64 32)"
            ;;
        *)
            LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | dd bs=40 count=1 2>/dev/null |
                LC_ALL=C tr -dc 'A-Za-z0-9' | cut -c1-40
            ;;
    esac
}

describe() {
    printf '  %-28s -> %-20s -> %s\n' "$1" "$2" "$3"
}

log "contract (env var <- Secret Manager secret <- payload)"
describe APP_KEY "$SECRET_APP_KEY" "AES-256-CBC application key (Laravel config/app.php)"
describe DB_PASSWORD "$SECRET_DB_PASSWORD" "MySQL role '$DB_USER'"
describe REDIS_PASSWORD "$SECRET_REDIS_PASSWORD" "Memorystore AUTH"
printf '  %-28s -> %-20s -> operator only, never injected into the service\n' \
    DB_ROOT_PASSWORD "$SECRET_DB_ROOT_PASSWORD"

if [ "$print_only" = "1" ]; then
    exit 0
fi

require_project

payload_file=$(mktemp)
trap 'rm -f "$payload_file"' EXIT INT TERM

for pair in "APP_KEY:$SECRET_APP_KEY" \
    "DB_ROOT_PASSWORD:$SECRET_DB_ROOT_PASSWORD" \
    "DB_PASSWORD:$SECRET_DB_PASSWORD" \
    "REDIS_PASSWORD:$SECRET_REDIS_PASSWORD"; do
    kind=${pair%%:*}
    name=${pair#*:}

    if secret_exists "$name"; then
        versions=$(gcloud secrets versions list "$name" --project "$GCP_PROJECT" --limit=1 \
            --format='value(name)' 2>/dev/null | wc -l | tr -d ' ')
        log "secret exists: $name (latest version: ${versions:-0}) — left untouched"
        continue
    fi

    generate_payload "$kind" >"$payload_file"
    [ -s "$payload_file" ] || die "could not generate a payload for $name"
    secret_add_version "$name" "$payload_file"
    log "secret created: $name (1 version)"
done

log ""
log "verify the service identity can read them:"
log "  gcloud projects add-iam-policy-binding $GCP_PROJECT \\"
log "    --member=serviceAccount:${GCP_PROJECT}-compute@developer.gserviceaccount.com \\"
log "    --role=roles/secretmanager.secretAccessor"
log ""
log "deploy with:"
log "  gcloud run deploy $APP_SERVICE … --set-secrets=$(comma_join $(data_secret_bindings | tr '\n' ' '))"
