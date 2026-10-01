# TaskFlow — Cloud Run environment contract (web service AND queue worker).
#
# This fragment is a YAML *list* of env entries, injected verbatim into both
# deploy/cloudrun/service.yaml.tpl (web) and deploy/cloudrun/worker-job.yaml.tpl
# (queue worker) by deploy/cloudrun/render.sh. Single source of truth: the two
# templates never spell out an environment variable between them.
#
# ─────────────────────────────────────────────────────────────────────────────
# SECRET ↔ ENVIRONMENT VARIABLE CONTRACT (issue #22 owns provisioning)
#
# Every credential lives in Secret Manager and is mapped onto exactly the
# variable Laravel already reads. No secret value is ever written into a YAML,
# a command line or a log.
#
#   Secret Manager secret        →  container env var   →  Laravel config
#   ─────────────────────────────────────────────────────────────────────────────
#   taskflow-app-key             →  APP_KEY             (php artisan key)
#   taskflow-db-host             →  DB_HOST             (database.connections.mysql.host)
#   taskflow-db-port             →  DB_PORT             (database.connections.mysql.port)
#   taskflow-db-database         →  DB_DATABASE         (database.connections.mysql.database)
#   taskflow-db-username         →  DB_USERNAME         (database.connections.mysql.username)
#   taskflow-db-password         →  DB_PASSWORD         (database.connections.mysql.password)
#   taskflow-redis-host          →  REDIS_HOST          (database.redis.default.host)
#   taskflow-redis-port          →  REDIS_PORT          (database.redis.default.port)
#   taskflow-redis-password      →  REDIS_PASSWORD      (database.redis.default.password)
#   taskflow-mail-password       →  MAIL_PASSWORD       (mail.mailers.smtp.password)
#
# db:<version>/dbUser is delivered as a JSON document with `host`, `port`,
# `db`, `user`, `password` keys — deploy/cloudrun/README.md documents the
# `jq` one-liner that turns each key into a secret of the name above. The web
# service and the queue job therefore read the identical set.
#
# Anything NOT in this table is a plain (non-secret) value and lives below.
# ─────────────────────────────────────────────────────────────────────────────
- name: APP_NAME
  value: TaskFlow
- name: APP_ENV
  value: production
- name: APP_DEBUG
  value: "false"
# APP_URL is rendered per deploy by render.sh (the run.app URL, or the custom
# domain from issue #23 once it exists): verification and password-reset links
# are emailed as absolute URLs and must match the origin the browser used.
- name: APP_MAINTENANCE_DRIVER
  value: file
- name: BCRYPT_ROUNDS
  value: "12"
# Logs go to the container's stderr, which Cloud Run turns into Cloud Logging —
# same destination as the local stack's /dev/stderr, no file shipping.
- name: LOG_CHANNEL
  value: stderr
- name: LOG_LEVEL
  value: info
- name: LOG_DEPRECATIONS_CHANNEL
  value: "null"
- name: DB_CONNECTION
  value: mysql
- name: SESSION_DRIVER
  value: redis
- name: SESSION_LIFETIME
  value: "120"
- name: SESSION_ENCRYPT
  value: "false"
- name: SESSION_SECURE_COOKIE
  value: "true"
- name: SESSION_SAME_SITE
  value: lax
- name: CACHE_STORE
  value: redis
- name: CACHE_PREFIX
  value: taskflow_production
- name: QUEUE_CONNECTION
  value: redis
- name: FILESYSTEM_DISK
  value: local
- name: REDIS_CLIENT
  value: phpredis
- name: REDIS_DB
  value: "0"
- name: REDIS_CACHE_DB
  value: "1"
- name: BROADCAST_CONNECTION
  value: log
# Queued mail (invitations, mentions, verification) is sent by the worker from
# an SMTP relay. Credentials follow the contract table above; the host/user are
# plain values to be aligned with issue #22 — see deploy/README.md.
- name: MAIL_MAILER
  value: smtp
- name: MAIL_SCHEME
  value: smtp
- name: MAIL_HOST
  value: smtp.postmarkapp.com
- name: MAIL_PORT
  value: "587"
- name: MAIL_USERNAME
  value: taskflow
- name: MAIL_FROM_ADDRESS
  value: noreply@taskflow.example.com
- name: MAIL_FROM_NAME
  value: TaskFlow