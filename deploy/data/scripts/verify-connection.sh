#!/bin/sh
# End-to-end check that the managed data services and the *application* agree.
#
# A provisioned database is not enough: what matters is that this Laravel build
# can actually reach MySQL and Redis with the env vars the Cloud Run service is
# configured with, and that the cache/session/queue drivers resolve. This script
# runs the real application, not a SQL client:
#
#   1. php artisan about           boots the framework with the injected env
#   2. php artisan db:show         DB::connection()->getPdo() over the socket
#   3. Cache::put/get             CACHE_STORE + the redis.cache connection (DB 1)
#   4. session round trip         SESSION_DRIVER
#   5. the queue driver + the reserved Redis list  QUEUE_CONNECTION + REDIS_QUEUE
#   6. failed_jobs is writable    the queue's failure sink (database-uuids)
#
# Usage (from the repository root, with a locally built image):
#   docker build -f Dockerfile.prod -t taskflow:latest .
#   GCP_PROJECT=my-project ./deploy/data/scripts/verify-connection.sh
#
# Reaching a managed instance from a laptop needs the Cloud SQL Auth Proxy, which
# lib.sh starts on demand (cloud-sql-proxy + a mysql client must be installed:
# brew install cloud-sql-proxy mysql-client). VERIFY_REMOTE=1 runs the same checks
# inside Cloud Run instead, where the socket is already mounted.
#
set -eu

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
# shellcheck source=../env.sh
. "$script_dir/../env.sh"
# shellcheck source=lib.sh
. "$script_dir/lib.sh"

: "${IMAGE:=taskflow:latest}"
: "${VERIFY_REMOTE:=0}" # 1 = run the checks in Cloud Run instead of Docker
# Docker network for the checks. Empty = the host network (works with the
# Cloud SQL Auth Proxy); set it to reach a docker-compose stack by service name,
# e.g. VERIFY_DOCKER_NETWORK=taskflow_taskflow DB_HOST=mysql REDIS_HOST=redis
: "${VERIFY_DOCKER_NETWORK:=}"

if [ "$VERIFY_REMOTE" = "1" ]; then
    require_project
    log "running the checks inside the $RUN_JOBS_NAME job (region $GCP_REGION)"
    gcloud run jobs execute "$RUN_JOBS_NAME" --region "$GCP_REGION" --wait
    exit 0
fi

# The env file only ever holds non-secret values; the three payloads are read
# from Secret Manager and passed as environment variables, never written down.
command -v docker >/dev/null 2>&1 || die "docker not found"

if [ "${VERIFY_SKIP_SECRET:-0}" = "1" ]; then
    warn "VERIFY_SKIP_SECRET=1: using VERIFY_* variables instead of Secret Manager values"
    db_password=${VERIFY_DB_PASSWORD:-not-a-real-password}
    redis_password=${VERIFY_REDIS_PASSWORD:-}
    # A throwaway key is enough: these checks never encrypt anything.
    app_key=${VERIFY_APP_KEY:-"base64:$(od -An -N32 -tx1 < /dev/urandom | tr -d ' \n')"}
else
    require_gcloud
    for secret in "$SECRET_APP_KEY" "$SECRET_DB_PASSWORD" "$SECRET_REDIS_PASSWORD"; do
        secret_exists "$secret" || die "secret $secret not found; run scripts/secrets.sh"
    done
    app_key=$(secret_payload "$SECRET_APP_KEY")
    db_password=$(secret_payload "$SECRET_DB_PASSWORD")
    # Memorystore is created with --enable-auth, so in production this is a real
    # value. Empty stays empty for an unauthenticated local redis.
    redis_password=$(secret_payload "$SECRET_REDIS_PASSWORD" || true)
fi

if sql_instance_exists 2>/dev/null; then
    db_host=$(sql_instance_ip)
    [ -n "$db_host" ] || db_host=127.0.0.1
    db_port=3306
    db_socket=''
    # A private-IP instance is only reachable through the Auth Proxy, so start it
    # and point the container at 127.0.0.1. Same helper as the provision scripts
    # (`gcloud sql connect` cannot be used as a proxy: it launches an interactive
    # client and exits when that client does).
    sql_proxy_start
    db_host=127.0.0.1
    db_port=$SQL_PROXY_PORT
    log "connecting to Cloud SQL through the Auth Proxy on 127.0.0.1:$db_port"
else
    # Local stack (docker-compose): the mysql/redis service names.
    db_host=${DB_HOST:-mysql}
    db_port=${DB_PORT:-3306}
    db_socket=''
    log "no Cloud SQL instance found: falling back to the local docker-compose stack ($db_host:$db_port)"
fi

if [ -n "${REDIS_HOST_OVERRIDE:-}" ]; then
    redis_host=$REDIS_HOST_OVERRIDE
elif redis_instance_exists 2>/dev/null; then
    redis_host=$(redis_instance_host)
else
    redis_host=${REDIS_HOST:-redis}
fi
log "redis host: $redis_host"

env_file=$(mktemp)
GCP_PROJECT="$GCP_PROJECT" GCP_REGION="$GCP_REGION" data_env_file "$env_file"
trap 'rm -f "$env_file"; sql_proxy_stop' EXIT INT TERM

# --rm keeps the container from leaving a stopped container behind; the app is
# read-only for these checks (nothing here writes a row).
network_args=''
if [ -n "$VERIFY_DOCKER_NETWORK" ]; then
    network_args="--network=$VERIFY_DOCKER_NETWORK"
    log "container network: $VERIFY_DOCKER_NETWORK"
fi

# shellcheck disable=SC2086 # network_args is intentionally word-split
docker run --rm $network_args \
    --env-file="$env_file" \
    -e DB_HOST="$db_host" \
    -e DB_PORT="$db_port" \
    -e DB_SOCKET="$db_socket" \
    -e APP_KEY="$app_key" \
    -e DB_PASSWORD="$db_password" \
    -e REDIS_HOST="$redis_host" \
    -e REDIS_PASSWORD="$redis_password" \
    "$IMAGE" \
    sh -lc '
        set -eu
        echo "== 1/6 framework boot"
        php artisan about --only=environment 2>&1 | sed -n "1,12p"

        echo "== 2/6 MySQL through the app config"
        # db:show opens a raw PDO connection and prints the server version, the
        # database and the user. Its "Tables" line counts every user schema, not
        # just this one, so the database name is asserted separately below.
        php artisan db:show 2>&1 | sed -n "1,10p"
        php artisan tinker --execute="
            \$connection = Illuminate\\Support\\Facades\\DB::connection();
            if (\$connection->getDatabaseName() !== env(\"DB_DATABASE\")) {
                throw new RuntimeException(\"connected to the wrong database: \" . \$connection->getDatabaseName());
            }
            \$tables = \$connection->getSchemaBuilder()->getTableListing(env(\"DB_DATABASE\"));
            echo \"database asserted, \", count(\$tables), \" tables\", PHP_EOL;
        "

        echo "== 3/6 cache (CACHE_STORE=$CACHE_STORE)"
        php artisan tinker --execute="
            Cache::put(\"taskflow:verify\", \"ok\", 60);
            if (Cache::get(\"taskflow:verify\") !== \"ok\") { exit(1); }
            echo \"cache round trip ok\n\";
        "

        echo "== 4/6 session (SESSION_DRIVER=$SESSION_DRIVER)"
        php artisan tinker --execute="
            \$session = app(\"session.store\");
            \$session->put(\"taskflow:verify\", \"ok\");
            \$session->save();
            echo \"session write ok\n\";
        "

        echo "== 5/6 queue (QUEUE_CONNECTION=$QUEUE_CONNECTION, REDIS_QUEUE=$REDIS_QUEUE)"
        php artisan tinker --execute="
            \$connection = Illuminate\Support\Facades\Queue::connection();
            if (! \$connection instanceof Illuminate\Queue\RedisQueue) {
                throw new RuntimeException(\"QUEUE_CONNECTION did not resolve to the redis driver\");
            }
            \$key = config(\"queue.connections.redis.queue\");
            \$len = Illuminate\Support\Facades\Redis::connection(\"default\")->llen(\$key);
            echo \"redis driver ok; queue \", \$key, \" length \", \$len, PHP_EOL;
        "

        echo "== 6/6 failed_jobs sink (QUEUE_FAILED_DRIVER=database-uuids)"
        php artisan queue:failed --json >/dev/null
        echo "failed_jobs readable"

        echo
        echo "all checks passed"
    '

log "connection verified"
