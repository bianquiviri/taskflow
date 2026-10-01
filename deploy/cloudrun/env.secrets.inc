# TaskFlow — Secret Manager bindings for the Cloud Run web service.
#
# One YAML list of - secretKeyRef: { secretName:, keyVersion: latest } entries,
# appended to the container's envValueSources by deploy/cloudrun/deploy.sh (the
# Cloud Run Admin API v1 field name; --set-secrets writes the same structure).
#
# The secret names are the contract documented at the top of env.plain.inc,
# which issue #22 provisions. A deployment fails closed — the revision never
# becomes ready — when one of them is missing, which is the intended failure
# mode: no silent fallback to an empty password.
- secretKeyRef:
    secretName: taskflow-app-key
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-db-host
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-db-port
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-db-database
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-db-username
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-db-password
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-redis-host
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-redis-port
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-redis-password
    keyVersion: latest
- secretKeyRef:
    secretName: taskflow-mail-password
    keyVersion: latest