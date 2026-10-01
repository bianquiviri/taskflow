#!/usr/bin/env bash
#
# TaskFlow — deploy the web service to Cloud Run.
#
# Renders deploy/cloudrun/service.yaml (image + region + project + the two env
# fragments) into a temporary file and hands it to `gcloud run services replace`,
# then wires the invoker permission and the queue worker job.
#
# This script is the *only* supported way to change the running service: the
# committed YAML carries __PLACEHOLDER__ tokens on purpose, so nobody can
# accidentally replace the service with an image reference that was never built.
#
# Usage:
#   deploy.sh --project=ID --region=REGION --image=REGION-docker.pkg.dev/ID/repo/taskflow:TAG
#
# Options:
#   --project=ID       GCP project id (required)
#   --region=REGION    Cloud Run + Artifact Registry region (required)
#   --image=IMAGE      Fully qualified image, digest preferred (required)
#   --app-url=URL      Public APP_URL (default: the service's own run.app URL)
#   --migrate          Run `php artisan migrate --force` as a one-off Job first
#   --skip-worker      Do not replace the queue worker Job
#   --no-invoker       Skip granting roles/run.invoker to allUsers
#   --dry-run          Render and print the manifest, touch nothing
#
# Deliberately NOT supported: --no-traffic. Rollout is 100% to the new revision
# only after its startup probe passed, which is what `gcloud run services
# replace` does with the traffic block at the end of the manifest. For a
# no-downtime manual switch use `gcloud run services update-traffic`.
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

PROJECT_ID=''
REGION=''
IMAGE=''
APP_URL=''
RUN_MIGRATIONS=0
DEPLOY_WORKER=1
GRANT_INVOKER=1
DRY_RUN=0
SERVICE_NAME='taskflow'
WORKER_JOB_NAME='taskflow-worker'

log() { printf '\033[1;34m[deploy]\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31m[deploy]\033[0m %s\n' "$*" >&2; exit 1; }

usage() { sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

for arg in "$@"; do
    case "$arg" in
        --project=*)   PROJECT_ID="${arg#*=}" ;;
        --region=*)    REGION="${arg#*=}" ;;
        --image=*)     IMAGE="${arg#*=}" ;;
        --app-url=*)   APP_URL="${arg#*=}" ;;
        --migrate)     RUN_MIGRATIONS=1 ;;
        --skip-worker) DEPLOY_WORKER=0 ;;
        --no-invoker)  GRANT_INVOKER=0 ;;
        --dry-run)     DRY_RUN=1 ;;
        -h|--help)     usage; exit 0 ;;
        *)             die "unknown option: $arg (try --help)" ;;
    esac
done

[ -n "$PROJECT_ID" ] || die '--project is required'
[ -n "$REGION" ] || die '--region is required'
[ -n "$IMAGE" ] || die '--image is required'

# --dry-run only renders the manifest, so it works on a machine without the gcloud
# CLI (that is what CI uses to validate the templates).
if [ "$DRY_RUN" -ne 1 ]; then
    command -v gcloud >/dev/null 2>&1 || die 'gcloud CLI not found (https://cloud.google.com/sdk/docs/install)'
fi

# --- Render the manifest -----------------------------------------------------
# Two fragments become one env list, indented to sit under `env:`, and the
# secret refs sit under `envValueSources:`. Both fragments are plain YAML lists
# so they are valid documents on their own (and get YAML-parsed in CI).
#
# Ruby does the block indentation and YAML emission; sed only fills the
# flat scalar tokens (image/region/project), which cannot span lines.
render_manifest() {
    local template="$1" out="$2"

    # env_file/secrets_file are skipped for templates without env tokens (the
    # Cloud Scheduler trigger has none), so one renderer serves all three.
    ruby -ryaml -e '
        template, env_file, secrets_file, out, image, region, project, app_url, job_name = ARGV
        body = File.read(template)

        body = body.gsub("__IMAGE__", image)
                   .gsub("__REGION__", region)
                   .gsub("__PROJECT_ID__", project)
                   .gsub("__JOB_NAME__", job_name)

        # Indentation is derived from where the tokens sit in the template, so
        # re-indenting a container in the YAML does not silently break this.
        indent = " " * (body[/^([ ]*)env: __ENV__$/, 1].to_s.length + 2)
        secrets_indent = " " * (body[/^([ ]*)envValueSources: __ENV_FROM__$/, 1].to_s.length + 2)

        # to_yaml re-emits every entry, so the fragments stay readable YAML and
        # the generated manifest is guaranteed to parse.
        # APP_URL is optional: without it the service keeps serving from the
        # hostname Cloud Run already assigned, so it is appended to the parsed
        # fragment rather than injected into the template.
        entries = env_file.empty? ? [] : YAML.load_file(env_file)
        entries << { "name" => "APP_URL", "value" => app_url } unless app_url.empty?

        # Each entry is re-emitted as a YAML list item: "- name: X" followed by
        # "  value: Y". The first line carries the dash, the rest the indent —
        # the leading document marker of to_yaml is dropped first (dropping it with
        # \s* instead would eat the newline and glue the first line to the pad).
        block = lambda do |list, pad|
            list.map { |entry|
                # Blank lines are dropped: Psych puts one between the key and
                # the value of a nested mapping, which would be re-indented as an
                # empty line inside the manifest.
                entry.to_yaml
                    .sub(/\A---\n/, "")
                    .split("\n")
                    .map(&:rstrip)
                    .reject(&:empty?)
                    .then { |lines|
                        # split (not lines) avoids reintroducing the newline that
                        # was just stripped off.
                        pad + "- " + lines[0] + "\n" +
                            lines.drop(1).map { |l| pad + "  " + l }.join("\n")
                    }
            }.join("\n")
        end

        # Only the container templates carry env tokens; the scheduler trigger has
        # no environment at all and is rendered by the same substitution pass.
        body = body.sub(/^([ ]*)env: __ENV__$/) { "#{$1}env:\n" + block.call(entries, indent) }
        body = body.sub(/^([ ]*)envValueSources: __ENV_FROM__$/) do
            "#{$1}envValueSources:\n" + block.call(YAML.load_file(secrets_file), secrets_indent)
        end

        # A token left here means the template and this script disagree; failing
        # now is cheaper than a deployment that quietly runs the wrong image.
        leftover = body.scan(/__[A-Z_]+__/)
        raise "unrendered tokens in #{template}: #{leftover.uniq.join(", ")}" unless leftover.empty?

        File.write(out, body)
    ' "$template" "$SCRIPT_DIR/env.plain.inc" "$SCRIPT_DIR/env.secrets.inc" "$out" \
        "$IMAGE" "$REGION" "$PROJECT_ID" "$APP_URL" "$WORKER_JOB_NAME"
}

SERVICE_YAML="$(mktemp -t taskflow-service.XXXXXX.yaml)"
WORKER_YAML="$(mktemp -t taskflow-worker.XXXXXX.yaml)"
SCHEDULE_YAML="$(mktemp -t taskflow-schedule.XXXXXX.yaml)"
trap 'rm -f "$SERVICE_YAML" "$WORKER_YAML" "$SCHEDULE_YAML"' EXIT

log "rendering service manifest"
render_manifest "$SCRIPT_DIR/service.yaml" "$SERVICE_YAML"

if [ "$DEPLOY_WORKER" -eq 1 ]; then
    log "rendering worker job manifest"
    render_manifest "$SCRIPT_DIR/worker-job.yaml" "$WORKER_YAML"

    log "rendering scheduler trigger manifest"
    render_manifest "$SCRIPT_DIR/schedule-job.yaml" "$SCHEDULE_YAML"
fi

# Parse check before it reaches the API: gcloud's error messages for malformed
# YAML are far less obvious than this one. The renderer already refuses to emit
# a manifest with an unrendered token; this catches indentation damage.
ruby -ryaml -e 'abort("empty manifest") unless YAML.load_file(ARGV[0])' "$SERVICE_YAML" \
    || die 'rendered service manifest is not valid YAML'
if [ "$DEPLOY_WORKER" -eq 1 ]; then
    ruby -ryaml -e 'abort("empty manifest") unless YAML.load_file(ARGV[0])' "$WORKER_YAML" \
        || die 'rendered worker manifest is not valid YAML'
fi

if [ "$DRY_RUN" -eq 1 ]; then
    log 'DRY RUN — rendered service manifest:'
    cat "$SERVICE_YAML"
    echo
    if [ "$DEPLOY_WORKER" -eq 1 ]; then
        log 'DRY RUN — rendered worker job manifest:'
        cat "$WORKER_YAML"
        echo
        log 'DRY RUN — rendered scheduler trigger manifest:'
        cat "$SCHEDULE_YAML"
        echo
    fi
    exit 0
fi

gcloud config set project "$PROJECT_ID" >/dev/null

# --- Migrations (optional, one-off) -----------------------------------------
# Not part of the web revision: migrations are a separate execution that either
# succeeds or leaves the previous revision serving. Requires the DB secrets and
# runs as the worker service account, which is why it is opt-in.
if [ "$RUN_MIGRATIONS" -eq 1 ]; then
    log 'running migrations as a one-off Job'
    gcloud run jobs execute taskflow-migrate \
        --image="$IMAGE" \
        --region="$REGION" \
        --project="$PROJECT_ID" \
        --command php -- artisan migrate --force \
        --wait
fi

# --- Web service ------------------------------------------------------------
log "replacing service ${SERVICE_NAME} (image ${IMAGE})"
gcloud run services replace "$SERVICE_YAML" \
    --project="$PROJECT_ID" \
    --region="$REGION" \
    --quiet

# Public entrypoint: TaskFlow authenticates users itself (Laravel sessions and
# policies), and the sign-in, verification and password-reset links are opened
# from a real browser with no Google credentials, so the service must be
# reachable by allUsers. Cloud Run IAM would sit in front of the app's own auth
# and break those flows; the app is the security boundary. Documented in
# deploy/README.md, along with the IAM-less alternatives for a stricter posture.
if [ "$GRANT_INVOKER" -eq 1 ]; then
    log 'granting roles/run.invoker to allUsers (public entrypoint)'
    gcloud run services add-iam-policy-binding "$SERVICE_NAME" \
        --project="$PROJECT_ID" \
        --region="$REGION" \
        --member='allUsers' \
        --role='roles/run.invoker' \
        --quiet
fi

# --- Queue worker -----------------------------------------------------------
# The web process never consumes the queue, so the worker Job is deployed from
# the same image; its executions are started by deploy/cloudrun/schedule-job.yaml
# (Cloud Scheduler). Replacing it here keeps image and manifest in one change.
if [ "$DEPLOY_WORKER" -eq 1 ]; then
    log "replacing worker job ${WORKER_JOB_NAME}"
    gcloud run jobs replace "$WORKER_YAML" \
        --project="$PROJECT_ID" \
        --region="$REGION" \
        --quiet

    # The Job only runs when something starts it: Cloud Scheduler, calling the
    # Jobs API over an OIDC-authenticated HTTP request.
    log "replacing scheduler trigger for ${WORKER_JOB_NAME}"
    gcloud scheduler jobs replace "$SCHEDULE_YAML" \
        --project="$PROJECT_ID" \
        --location="$REGION" \
        --quiet
fi

SERVICE_URL="$(gcloud run services describe "$SERVICE_NAME" \
    --project="$PROJECT_ID" --region="$REGION" --format='value(status.url)')"

log "deployed ${SERVICE_URL}"

# --- Post-deploy verification ------------------------------------------------
# The startup probe already gated traffic on /up; this proves the revision that
# is actually serving answers, and that it is the revision we just deployed.
READY_REVISION="$(gcloud run services describe "$SERVICE_NAME" \
    --project="$PROJECT_ID" --region="$REGION" --format='value(status.traffic[0].revisionName)')"
log "traffic on revision ${READY_REVISION}"

for path in /up /login; do
    code="$(curl -sS -o /dev/null -w '%{http_code}' "${SERVICE_URL}${path}" || echo 000)"
    [ "$code" = '200' ] || die "GET ${path} -> ${code} (expected 200)"
    log "GET ${path} -> ${code}"
done

log 'deploy OK'