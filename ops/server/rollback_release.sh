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

ROLLBACK_TARGET="${1:-${ROLLBACK_TARGET:-}}"
APP_BASE_DIR="${APP_BASE_DIR:-/srv/saas}"
RELEASES_DIR="${RELEASES_DIR:-${APP_BASE_DIR}/releases}"
CURRENT_LINK="${CURRENT_LINK:-${APP_BASE_DIR}/app}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://127.0.0.1/healthz}"
HEALTHCHECK_ATTEMPTS="${HEALTHCHECK_ATTEMPTS:-6}"
HEALTHCHECK_SLEEP_SECONDS="${HEALTHCHECK_SLEEP_SECONDS:-5}"

find_previous_release() {
    local current_release="$1"

    find "${RELEASES_DIR}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
        | sort -nr \
        | awk '{print $2}' \
        | while IFS= read -r release; do
            [[ "${release}" == "${current_release}" ]] && continue
            printf '%s\n' "${release}"
            break
        done
}

resolve_target_release() {
    local current_release="$1"

    if [[ -n "${ROLLBACK_TARGET}" ]]; then
        if [[ ! -d "${RELEASES_DIR}/${ROLLBACK_TARGET}" ]]; then
            log_error "Requested release not found: ${RELEASES_DIR}/${ROLLBACK_TARGET}"
            exit 1
        fi
        printf '%s\n' "${RELEASES_DIR}/${ROLLBACK_TARGET}"
        return 0
    fi

    local previous_release
    previous_release="$(find_previous_release "${current_release}")"

    if [[ -z "${previous_release}" ]]; then
        log_error "No previous release found in ${RELEASES_DIR}"
        exit 1
    fi

    printf '%s\n' "${previous_release}"
}

switch_release() {
    local target_release="$1"

    ln -sfn "${target_release}" "${CURRENT_LINK}"
    log_info "Current symlink updated -> ${target_release}"
}

restart_stack() {
    if [[ ! -d "${CURRENT_LINK}" ]]; then
        log_error "Current link is not a directory: ${CURRENT_LINK}"
        exit 1
    fi

    cd "${CURRENT_LINK}"
    log_info "Restarting stack in ${CURRENT_LINK}"
    docker_compose down
    docker_compose up -d --remove-orphans
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

main() {
    require_command docker
    require_command curl
    require_command readlink

    if [[ ! -L "${CURRENT_LINK}" ]]; then
        log_error "Current release symlink missing: ${CURRENT_LINK}"
        exit 1
    fi

    local current_release
    current_release="$(readlink -f "${CURRENT_LINK}")"

    local target_release
    target_release="$(resolve_target_release "${current_release}")"

    if [[ "${target_release}" == "${current_release}" ]]; then
        log_warn "Rollback target equals current release. Nothing to do."
        return 0
    fi

    log_info "Rollback from ${current_release} -> ${target_release}"

    switch_release "${target_release}"
    restart_stack

    if wait_for_health; then
        log_info "Rollback completed successfully."
        return 0
    fi

    log_error "Rollback healthcheck failed. Reverting to previous release ${current_release}"
    switch_release "${current_release}"
    restart_stack

    if wait_for_health; then
        log_error "Recovery succeeded, rollback canceled."
    else
        log_error "Recovery healthcheck failed. Manual intervention required."
    fi

    exit 1
}

main "$@"
