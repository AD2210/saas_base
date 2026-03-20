#!/usr/bin/env bash

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../lib/common.sh"

init_logging
setup_error_trap

ENV_FILE="${SCRIPT_DIR}/../config/server.env"
if [[ $# -gt 0 && -f "$1" ]]; then
    ENV_FILE="$1"
    shift
fi

if [[ -f "${ENV_FILE}" ]]; then
    load_env_file "${ENV_FILE}"
else
    log_warn "No env file provided. Using script defaults."
fi

SOURCE_DIR="${1:-$(pwd)}"
APP_BASE_DIR="${APP_BASE_DIR:-/srv/saas}"
RELEASES_DIR="${RELEASES_DIR:-${APP_BASE_DIR}/releases}"
CURRENT_LINK="${CURRENT_LINK:-${APP_BASE_DIR}/app}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://127.0.0.1/healthz}"
HEALTHCHECK_ATTEMPTS="${HEALTHCHECK_ATTEMPTS:-6}"
HEALTHCHECK_SLEEP_SECONDS="${HEALTHCHECK_SLEEP_SECONDS:-5}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
APP_SERVICE_NAME="${APP_SERVICE_NAME:-app}"
DEPLOY_PROFILE="${DEPLOY_PROFILE:-prod}"
DEPLOY_EXCLUDES="${DEPLOY_EXCLUDES:-.git .github tests docs .idea var/cache var/log .env .env.dev .env.test docker-compose.dev.yml}"
PROD_DEPLOY_EXCLUDES="${PROD_DEPLOY_EXCLUDES:-src/Debug src/DataFixtures config/routes/debug.yaml public/test.php}"

if [[ "${DEPLOY_PROFILE}" == "prod" ]]; then
    DEPLOY_EXCLUDES="${DEPLOY_EXCLUDES} ${PROD_DEPLOY_EXCLUDES}"
fi

RELEASE_ID="$(date -u +"%Y%m%dT%H%M%SZ")"
TARGET_RELEASE="${RELEASES_DIR}/${RELEASE_ID}"
RUNTIME_ENV_FILE=".env.runtime"
DOTENV_STUB_FILE=".env"

sync_release() {
    mkdir -p "${TARGET_RELEASE}"

    local rsync_excludes=()
    local entry
    for entry in ${DEPLOY_EXCLUDES}; do
        rsync_excludes+=("--exclude=${entry}")
    done

    log_info "Syncing source ${SOURCE_DIR} to ${TARGET_RELEASE} (profile=${DEPLOY_PROFILE})"
    rsync -a --delete "${rsync_excludes[@]}" "${SOURCE_DIR}/" "${TARGET_RELEASE}/"
}

quote_env_value() {
    local value="$1"

    printf "'%s'" "$(printf '%s' "${value}" | sed "s/'/'\\\\''/g")"
}

append_runtime_var() {
    local file_path="$1"
    local name="$2"
    local value="$3"

    printf '%s=' "${name}" >> "${file_path}"
    quote_env_value "${value}" >> "${file_path}"
    printf '\n' >> "${file_path}"
}

render_runtime_env() {
    local runtime_file="${TARGET_RELEASE}/${RUNTIME_ENV_FILE}"
    local child_var

    : > "${runtime_file}"

    append_runtime_var "${runtime_file}" "APP_ENV" "${APP_ENV:-prod}"
    append_runtime_var "${runtime_file}" "APP_DEBUG" "${APP_DEBUG:-0}"
    append_runtime_var "${runtime_file}" "APP_SECRET" "${APP_SECRET:-change-me-app-secret}"
    append_runtime_var "${runtime_file}" "APP_SECRETBOX_KEY" "${APP_SECRETBOX_KEY:-d13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a}"
    append_runtime_var "${runtime_file}" "APP_ADMIN_PASSWORD" "${APP_ADMIN_PASSWORD:-change-this-admin-password}"
    append_runtime_var "${runtime_file}" "COMPOSE_PROJECT_NAME" "${COMPOSE_PROJECT_NAME:-app}"
    append_runtime_var "${runtime_file}" "SERVER_NAME" "${SERVER_NAME:-:80}"
    append_runtime_var "${runtime_file}" "BASE_URI" "${BASE_URI:-http://127.0.0.1}"
    append_runtime_var "${runtime_file}" "APP_HTTP_PORT" "${APP_HTTP_PORT:-80}"
    append_runtime_var "${runtime_file}" "APP_HTTPS_PORT" "${APP_HTTPS_PORT:-443}"
    append_runtime_var "${runtime_file}" "POSTGRES_USER" "${POSTGRES_USER:-postgres}"
    append_runtime_var "${runtime_file}" "POSTGRES_PASSWORD" "${POSTGRES_PASSWORD:-postgres}"
    append_runtime_var "${runtime_file}" "MAIN_DB_NAME" "${MAIN_DB_NAME:-saas_base_main}"
    append_runtime_var "${runtime_file}" "DATABASE_URL" "${DATABASE_URL:-postgresql://postgres:${POSTGRES_PASSWORD:-postgres}@db:5432/${MAIN_DB_NAME:-saas_base_main}?serverVersion=16&charset=utf8}"
    append_runtime_var "${runtime_file}" "MESSENGER_TRANSPORT_DSN" "${MESSENGER_TRANSPORT_DSN:-doctrine://default?auto_setup=0}"
    append_runtime_var "${runtime_file}" "MAILER_DSN" "${MAILER_DSN:-null://null}"
    append_runtime_var "${runtime_file}" "MAILER_FROM" "${MAILER_FROM:-noreply@dsn-dev.com}"
    append_runtime_var "${runtime_file}" "DEFAULT_CHILD_APP_KEY" "${DEFAULT_CHILD_APP_KEY:-vault}"
    append_runtime_var "${runtime_file}" "NETDATA_PUBLIC_URL" "${NETDATA_PUBLIC_URL:-}"
    append_runtime_var "${runtime_file}" "UPTIME_KUMA_PUBLIC_URL" "${UPTIME_KUMA_PUBLIC_URL:-}"
    append_runtime_var "${runtime_file}" "NETDATA_PORT" "${NETDATA_PORT:-19999}"
    append_runtime_var "${runtime_file}" "UPTIME_KUMA_PORT" "${UPTIME_KUMA_PORT:-3001}"

    while IFS= read -r child_var; do
        [[ "${child_var}" == "DEFAULT_CHILD_APP_KEY" ]] && continue
        append_runtime_var "${runtime_file}" "${child_var}" "${!child_var}"
    done < <(compgen -A variable | grep '^CHILD_APP_' | sort)

    chmod 0600 "${runtime_file}"
    log_info "Runtime env file rendered: ${runtime_file}"
}

render_dotenv_stub() {
    local dotenv_file="${TARGET_RELEASE}/${DOTENV_STUB_FILE}"

    cat > "${dotenv_file}" <<CFG
APP_ENV=${APP_ENV:-prod}
APP_DEBUG=${APP_DEBUG:-0}
CFG

    chmod 0644 "${dotenv_file}"
    log_info "Dotenv stub rendered: ${dotenv_file}"
}

switch_current_symlink() {
    local release_path="$1"
    ln -sfn "${release_path}" "${CURRENT_LINK}"
    log_info "Current symlink updated -> ${release_path}"
}

start_stack() {
    cd "${CURRENT_LINK}"
    log_info "Starting stack for release $(readlink -f "${CURRENT_LINK}")"
    COMPOSE_ENV_FILE="${CURRENT_LINK}/${RUNTIME_ENV_FILE}" docker_compose pull
    COMPOSE_ENV_FILE="${CURRENT_LINK}/${RUNTIME_ENV_FILE}" docker_compose up -d --build --remove-orphans
}

run_migrations_if_needed() {
    if [[ "${RUN_MIGRATIONS}" != "true" ]]; then
        log_info "Migrations skipped by configuration."
        return 0
    fi

    cd "${CURRENT_LINK}"
    log_info "Running doctrine migrations"
    COMPOSE_ENV_FILE="${CURRENT_LINK}/${RUNTIME_ENV_FILE}" docker_compose exec -T "${APP_SERVICE_NAME}" php bin/console doctrine:migrations:migrate --no-interaction
}

wait_for_health() {
    local attempt=1

    while [[ "${attempt}" -le "${HEALTHCHECK_ATTEMPTS}" ]]; do
        if curl -fsS --max-time 10 "${HEALTHCHECK_URL}" >/dev/null; then
            log_info "Healthcheck succeeded on attempt ${attempt}"
            return 0
        fi

        log_warn "Healthcheck failed on attempt ${attempt}/${HEALTHCHECK_ATTEMPTS}"
        attempt=$((attempt + 1))
        sleep "${HEALTHCHECK_SLEEP_SECONDS}"
    done

    return 1
}

rollback_to_previous() {
    local previous_release="$1"

    if [[ -z "${previous_release}" ]]; then
        log_error "No previous release to rollback to."
        return 1
    fi

    log_warn "Reverting to previous release: ${previous_release}"
    switch_current_symlink "${previous_release}"
    start_stack
}

main() {
    require_command rsync
    require_command docker
    require_command curl
    require_command readlink

    if [[ ! -d "${SOURCE_DIR}" ]]; then
        log_error "Source directory not found: ${SOURCE_DIR}"
        exit 1
    fi

    mkdir -p "${RELEASES_DIR}"

    local previous_release=""
    if [[ -L "${CURRENT_LINK}" ]]; then
        previous_release="$(readlink -f "${CURRENT_LINK}")"
    fi

    sync_release
    render_runtime_env
    render_dotenv_stub
    switch_current_symlink "${TARGET_RELEASE}"
    start_stack
    run_migrations_if_needed

    if wait_for_health; then
        log_info "Deployment completed successfully (release: ${RELEASE_ID})."
        return 0
    fi

    log_error "Deployment healthcheck failed, starting rollback."
    rollback_to_previous "${previous_release}"

    if wait_for_health; then
        log_error "Rollback succeeded. Current release restored."
    else
        log_error "Rollback failed. Manual intervention required."
    fi

    exit 1
}

main "$@"
