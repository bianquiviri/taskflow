#!/bin/sh
# Generates (or reuses) the mkcert root CA and a certificate for the app domain.
# All artifacts live under /certs (mounted from docker/traefik/ on the host).
set -e

CA_DIR="${CAROOT_DIR:-/certs/ca}"
DOMAIN="${DOMAIN:-taskflow.josebianco.local}"

mkdir -p "$CA_DIR"
export CAROOT="$CA_DIR"

# Create a root CA only if none exists yet (reuses the existing one otherwise,
# so browsers that already trust the CA keep trusting new certificates).
if [ ! -f "$CA_DIR/rootCA-key.pem" ]; then
    echo "[certs] No root CA found in $CA_DIR — creating a new one."
    # -install tries to update the container's trust store; harmless if it fails.
    mkcert -install >/dev/null 2>&1 || true
else
    echo "[certs] Reusing existing root CA from $CA_DIR."
fi

if [ ! -f /certs/cert.pem ] || [ ! -f /certs/key.pem ]; then
    echo "[certs] Generating certificate for ${DOMAIN}..."
    mkcert -cert-file /certs/cert.pem -key-file /certs/key.pem "$DOMAIN"
else
    echo "[certs] Certificate already exists — leaving it untouched."
fi

echo "[certs] Done."
echo "[certs]   cert : docker/traefik/cert.pem"
echo "[certs]   key  : docker/traefik/key.pem"
echo "[certs]   CA   : docker/traefik/ca/rootCA.pem"
echo "[certs] If your browser does not trust the CA yet, run: make trust-ca"