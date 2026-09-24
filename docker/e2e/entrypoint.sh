#!/bin/sh
# Prepares the e2e run inside Docker, then execs Playwright.
#
# 1. Networking: the app domain must resolve to the Docker gateway (where
#    Traefik :443 is published). `localhost` cannot be remapped — curl and
#    Chromium hardcode it to 127.0.0.1 — so the page must not depend on the
#    Vite dev server either.
# 2. Assets: Chromium inside this container cannot reach the dev server, so we
#    build production assets and temporarily hide public/hot (which makes
#    Laravel serve those assets). public/hot is restored on exit so the host
#    browser keeps its HMR session.
set -e

GATEWAY_IP="$(getent ahostsv4 host.docker.internal | awk 'NR==1 {print $1}')"

cat > /etc/hosts <<EOF
$GATEWAY_IP taskflow.josebianco.local
EOF

HOT_FILE=/work/public/hot
RESTORE_HOT=0
if [ -f "$HOT_FILE" ]; then
    mv "$HOT_FILE" "$HOT_FILE.bak"
    RESTORE_HOT=1
fi

restore_hot() {
    if [ "$RESTORE_HOT" = 1 ]; then
        mv "$HOT_FILE.bak" "$HOT_FILE"
    fi
}
trap restore_hot EXIT

echo "[e2e] Building production assets (public/hot hidden)..."
npm run build >/dev/null

# Note: no `exec` here — the EXIT trap must run after Playwright finishes to
# restore public/hot, and `exec` would replace this shell and drop the trap.
npx playwright test