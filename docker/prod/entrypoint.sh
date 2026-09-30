#!/bin/sh
# Boot script for the TaskFlow production image.
#
# The image is built without any environment baked in: every secret and
# endpoint arrives as a container environment variable at start time. Because
# of that the framework caches (config/routes/views) are built here, on every
# boot, instead of at build time.
#
# Usage:
#   entrypoint serve        nginx + php-fpm (the default CMD)
#   entrypoint <command>    run a one-off command instead, e.g.
#                           entrypoint php artisan migrate --force
set -eu

cd /var/www/html

log() {
    echo "[entrypoint] $*" >&2
}

bootstrap() {
    # Writable runtime directories. storage/ and bootstrap/cache/ are the only
    # paths the php-fpm workers (www-data) need to write to.
    mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache

    # Set to 1 for one-off commands (migrations, queue workers started by an
    # orchestrator) that do not need the cache warm-up.
    if [ "${TASKFLOW_SKIP_BOOTSTRAP:-0}" = "1" ]; then
        return
    fi

    if [ ! -e public/storage ]; then
        php artisan storage:link || log "storage:link failed; continuing"
    fi

    # Drop any cache left over from a previous boot: a cached config would
    # otherwise keep serving the values of the environment that wrote it.
    php artisan config:clear --quiet || true

    if php artisan optimize; then
        log "framework caches built (config, events, routes, views)"
    else
        # The caches are an optimisation, not a requirement: without them the
        # app reads the injected environment directly. Stay available.
        log "WARNING: could not build the framework caches, continuing uncached"
    fi
}

serve() {
    php_fpm_pid=''
    nginx_pid=''

    stop() {
        log "shutting down"
        kill -QUIT "$php_fpm_pid" 2>/dev/null || true
        kill -TERM "$nginx_pid" 2>/dev/null || true
        wait "$php_fpm_pid" 2>/dev/null || true
        exit 0
    }
    trap stop TERM INT

    # Both processes inherit this shell's stdout/stderr, so their output is
    # already on the container log. Either one exiting ends the container:
    # the platform restarts a clean pair instead of a half-working one, and
    # no extra process supervisor is needed in the image.
    php-fpm -F &
    php_fpm_pid=$!

    nginx -g 'daemon off;' &
    nginx_pid=$!

    log "nginx (pid $nginx_pid) + php-fpm (pid $php_fpm_pid) up, socket /run/php-fpm.sock"

    # Returns as soon as either process exits; the trap tears down the other.
    wait -n
}

bootstrap

if [ "${1:-serve}" = "serve" ]; then
    serve
else
    exec "$@"
fi
