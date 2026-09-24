# Generates mkcert TLS certificates inside Docker — no host tooling required.
# Persists the root CA in /certs/ca so certificates stay signed by a stable CA.
FROM alpine:3.20

RUN apk add --no-cache curl

ARG TARGETARCH
RUN curl -fsSL -o /usr/local/bin/mkcert \
        "https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-linux-${TARGETARCH:-amd64}" \
    && chmod +x /usr/local/bin/mkcert

COPY docker/certs/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]