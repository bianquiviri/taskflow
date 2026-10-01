#!/bin/sh
# Shared helpers for the TaskFlow managed data scripts.
#
# POSIX sh, no bashisms, so the scripts run on macOS and on a Linux runner.
# Every script fails loudly instead of silently creating a half-provisioned
# database: `set -eu` plus explicit checks on every gcloud call that matters.
#
# shellcheck shell=sh

log() { printf '\033[0;36m[data]\033[0m %s\n' "$*" >&2; }
warn() { printf '\033[0;33m[data] WARNING:\033[0m %s\n' "$*" >&2; }
die() {
    printf '\033[0;31m[data] ERROR:\033[0m %s\n' "$*" >&2
    exit 1
}

# gcloud is mandatory: these scripts are thin, auditable wrappers around it.
# (This is also why nothing here can be "verified" without a real GCP project.)
require_gcloud() {
    command -v gcloud >/dev/null 2>&1 ||
        die "gcloud CLI not found. Install it from https://cloud.google.com/sdk/docs/install then: gcloud auth login"
    gcloud config get-value project >/dev/null 2>&1 || true
}

require_project() {
    require_gcloud
    [ -n "${GCP_PROJECT:-}" ] || die "GCP_PROJECT is not set"
    gcloud projects describe "$GCP_PROJECT" >/dev/null 2>&1 ||
        die "cannot reach project '$GCP_PROJECT'. Check: gcloud auth login && gcloud config set project $GCP_PROJECT"
}

# --set-secrets is comma separated: joining with commas (and never a newline).
comma_join() {
    _out=''
    for _item in "$@"; do
        if [ -z "$_out" ]; then
            _out=$_item
        else
            _out="${_out},${_item}"
        fi
    done
    printf '%s' "$_out"
}

# --- Cloud SQL -------------------------------------------------------------

sql_instance_exists() {
    gcloud sql instances describe "$SQL_INSTANCE" --project "$GCP_PROJECT" >/dev/null 2>&1
}

sql_instance_connection_name() {
    gcloud sql instances describe "$SQL_INSTANCE" --project "$GCP_PROJECT" \
        --format='value(connectionName)'
}

sql_instance_ip() {
    gcloud sql instances describe "$SQL_INSTANCE" --project "$GCP_PROJECT" \
        --format='value(ipAddresses)' |
        awk '$1 == "PRIMARY" { print $2; exit }'
}

# --- SQL, through the Cloud SQL Auth Proxy -----------------------------------
#
# `gcloud sql connect` cannot be used here. Its entire flag list is
# --database/--user/--port/--skip-ssl/--run-connection-test/--debug-logs/
# --auto-ip/--private-ip/--psc plus the gcloud-wide flags: there is no way to
# hand it a statement (no --execute/--execute-file) and no --password, so it
# always ends in an interactive mysql prompt. What Cloud Run actually mounts is
# the Auth Proxy binary, so these helpers start `cloud-sql-proxy` on a local port
# and drive it with the ordinary mysql client — the same TLS + IAM-authenticated
# path production uses, and scriptable.
#
# Required locally: cloud-sql-proxy and a mysql client
#   macOS:  brew install cloud-sql-proxy mysql-client
#   Linux:  gcloud components install cloud-sql-proxy && apt-get install mysql-client
SQL_PROXY_PID=''
SQL_PROXY_PORT=''
SQL_PROXY_LOG=''

require_sql_client() {
    command -v cloud-sql-proxy >/dev/null 2>&1 ||
        die "cloud-sql-proxy not found (brew install cloud-sql-proxy) — 'gcloud sql connect' cannot run SQL non-interactively"
    command -v mysql >/dev/null 2>&1 ||
        die "mysql client not found (brew install mysql-client)"
}

sql_proxy_listening() {
    if command -v nc >/dev/null 2>&1; then
        nc -z 127.0.0.1 "$SQL_PROXY_PORT" 2>/dev/null
    else
        grep -qi ready "$SQL_PROXY_LOG" 2>/dev/null
    fi
}

sql_proxy_start() {
    [ -n "$SQL_PROXY_PID" ] && return 0
    require_sql_client
    SQL_PROXY_PORT="${SQL_PROXY_LOCAL_PORT:-33306}"
    SQL_PROXY_LOG=$(mktemp "${TMPDIR:-/tmp}/taskflow-sql-proxy.XXXXXX")
    log "starting Cloud SQL Auth Proxy on 127.0.0.1:$SQL_PROXY_PORT -> $(sql_instance_connection_name)"
    cloud-sql-proxy --address 127.0.0.1 --port "$SQL_PROXY_PORT" \
        "$(sql_instance_connection_name)" >"$SQL_PROXY_LOG" 2>&1 &
    SQL_PROXY_PID=$!
    # Self-cleaning: the proxy dies with the script, including on Ctrl-C.
    trap 'sql_proxy_stop' EXIT HUP INT TERM

    _waited=0
    while [ "$_waited" -lt 60 ]; do
        if sql_proxy_listening; then
            return 0
        fi
        if ! kill -0 "$SQL_PROXY_PID" 2>/dev/null; then
            die "cloud-sql-proxy exited immediately: $(cat "$SQL_PROXY_LOG" 2>/dev/null)"
        fi
        _waited=$((_waited + 1))
        sleep 0.25
    done
    die "cloud-sql-proxy was not listening on 127.0.0.1:$SQL_PROXY_PORT after ~15s: $(cat "$SQL_PROXY_LOG" 2>/dev/null)"
}

sql_proxy_stop() {
    if [ -n "$SQL_PROXY_PID" ]; then
        kill "$SQL_PROXY_PID" 2>/dev/null || true
        wait "$SQL_PROXY_PID" 2>/dev/null || true
        SQL_PROXY_PID=''
    fi
    [ -n "$SQL_PROXY_LOG" ] && rm -f "$SQL_PROXY_LOG"
    SQL_PROXY_LOG=''
    return 0
}

# Reads SQL from stdin. $1 = user, $2 = password, $3 = database (optional).
# The password goes through MYSQL_PWD rather than --password=, so it never
# appears in the process list (`ps` shows argv, not the environment).
sql_run() {
    sql_proxy_start
    if [ -n "${3:-}" ]; then
        MYSQL_PWD="$2" mysql --protocol=TCP --host=127.0.0.1 --port="$SQL_PROXY_PORT" \
            --user="$1" --database="$3" --batch --raw --skip-column-names \
            --connect-timeout=10 --default-character-set=utf8mb4
    else
        MYSQL_PWD="$2" mysql --protocol=TCP --host=127.0.0.1 --port="$SQL_PROXY_PORT" \
            --user="$1" --batch --raw --skip-column-names \
            --connect-timeout=10 --default-character-set=utf8mb4
    fi
}

# Run SQL as the application role.
#
#   sql_exec "SELECT 1"            # single statement
#   cat file.sql | sql_exec       # script
sql_exec() {
    if [ "$#" -ge 1 ]; then
        printf '%s' "$1" | sql_exec
        return 0
    fi
    _pass=$(secret_payload "$SECRET_DB_PASSWORD")
    [ -n "$_pass" ] || die "password for '$SECRET_DB_PASSWORD' is empty; run scripts/secrets.sh"
    sql_run "$DB_USER" "$_pass" "$DB_NAME"
}

# Run SQL as the instance root. Needed once per fresh instance, to create the
# database and the application role (see provision-cloudsql.sh): there is no IAM
# shortcut for that. If you would rather the root password never touch a laptop,
# the script prints the same statements for pasting into the Cloud SQL console.
sql_exec_as_root() {
    if [ "$#" -ge 1 ]; then
        printf '%s' "$1" | sql_exec_as_root
        return 0
    fi
    _pass=$(secret_payload "$SECRET_DB_ROOT_PASSWORD")
    [ -n "$_pass" ] || die "password for '$SECRET_DB_ROOT_PASSWORD' is empty; run scripts/secrets.sh"
    warn "running as the instance root (MYSQL_PWD is in this process's environment, not in argv)"
    sql_run root "$_pass" ''
}

# Single-value query: prints the last line of the result so callers can compare
# it against a literal.
sql_scalar() {
    printf '%s' "$1" | sql_exec 2>/dev/null |
        grep -v '^[[:space:]]*$' |
        tail -n 1 |
        tr -d '[:space:]'
}

sql_root_scalar() {
    printf '%s' "$1" | sql_exec_as_root 2>/dev/null |
        grep -v '^[[:space:]]*$' |
        tail -n 1 |
        tr -d '[:space:]'
}

# --- existence checks -------------------------------------------------------

db_exists() {
    case "$(sql_root_scalar "SELECT IF(COUNT(*) = 0, 'MISSING', 'PRESENT') FROM information_schema.schemata WHERE schema_name = '${DB_NAME}';")" in
        *PRESENT) return 0 ;;
        *) return 1 ;;
    esac
}

# True when the application role already exists and its password in Secret
# Manager still works (keeps provision-cloudsql.sh idempotent without rotating).
sql_user_can_connect() {
    printf 'SELECT 1;' | sql_exec >/dev/null 2>&1
}

# --- Secret Manager --------------------------------------------------------

secret_exists() {
    gcloud secrets describe "$1" --project "$GCP_PROJECT" >/dev/null 2>&1
}

# Reads the payload of a secret. Only ever used to feed MYSQL_PWD / --command
# input; the scripts never print it.
secret_payload() {
    gcloud secrets versions access latest --secret="$1" --project="$GCP_PROJECT"
}

secret_id() {
    printf 'projects/%s/secrets/%s' "$GCP_PROJECT" "$1"
}

# Adds a new version only when the secret does not exist yet, so re-running is
# safe and never rotates a live password behind the app's back.
secret_add_version() {
    _name=$1
    _file=$2
    secret_exists "$_name" ||
        gcloud secrets create "$_name" --project="$GCP_PROJECT" \
            --replication-policy=automatic --data-file="$_file" >/dev/null
}

# --- Memorystore ----------------------------------------------------------

redis_instance_exists() {
    gcloud redis instances describe "$REDIS_INSTANCE" --project "$GCP_PROJECT" --region "$GCP_REGION" >/dev/null 2>&1
}

redis_instance_host() {
    gcloud redis instances describe "$REDIS_INSTANCE" --project "$GCP_PROJECT" --region "$GCP_REGION" \
        --format='value(host)'
}

redis_instance_port() {
    gcloud redis instances describe "$REDIS_INSTANCE" --project "$GCP_PROJECT" --region "$GCP_REGION" \
        --format='value(port)'
}

# --- dumps ------------------------------------------------------------------

# Prints how to read a SQL dump: `gzip -dc` when the file starts with the gzip
# magic number, `cat` otherwise. `gcloud sql export sql` gzips by default, but
# the flag is spelled differently across gcloud versions, so the file is
# sniffed instead of assumed.
dump_reader() {
    _file=$1
    _magic=$(od -An -N2 -tx1 <"$_file" | tr -d ' \n')
    if [ "$_magic" = "1f8b" ]; then
        echo "gzip -dc"
    else
        echo "cat"
    fi
}
