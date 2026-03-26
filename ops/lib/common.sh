#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

SCRIPT_NAME="$(basename "${BASH_SOURCE[1]:-${BASH_SOURCE[0]}}")"
OPS_LOG_DIR="${OPS_LOG_DIR:-/var/log/saas}"
OPS_LOG_FILE="${OPS_LOG_FILE:-${OPS_LOG_DIR}/ops.log}"

# FR: Horodatage UTC lisible dans tous les environnements.
# EN: UTC timestamps keep logs comparable across environments.
timestamp_utc() {
    date -u +"%Y-%m-%dT%H:%M:%SZ"
}

init_logging() {
    mkdir -p "${OPS_LOG_DIR}"
    touch "${OPS_LOG_FILE}"
}

log_message() {
    local level="$1"
    shift
    local message="$*"
    printf '%s [%s] [%s] %s\n' "$(timestamp_utc)" "${level}" "${SCRIPT_NAME}" "${message}" | tee -a "${OPS_LOG_FILE}" >&2
}

log_info() {
    log_message "INFO" "$*"
}

log_warn() {
    log_message "WARN" "$*"
}

log_error() {
    log_message "ERROR" "$*"
}

on_error_trap() {
    local exit_code="$?"
    local line_number="${BASH_LINENO[0]:-unknown}"
    log_error "Unhandled error near line ${line_number} (exit=${exit_code})."
    exit "${exit_code}"
}

setup_error_trap() {
    trap on_error_trap ERR
}

run_cmd() {
    log_info "Run: $*"
    "$@"
}

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        log_error "This script must run as root."
        exit 1
    fi
}

require_command() {
    local cmd="$1"
    if ! command -v "${cmd}" >/dev/null 2>&1; then
        log_error "Required command not found: ${cmd}"
        exit 1
    fi
}

require_env() {
    local var_name="$1"
    if [[ -z "${!var_name:-}" ]]; then
        log_error "Missing required env var: ${var_name}"
        exit 1
    fi
}

load_env_file() {
    local env_file="$1"

    if [[ ! -f "${env_file}" ]]; then
        log_error "Env file not found: ${env_file}"
        exit 1
    fi

    # shellcheck disable=SC1090
    source "${env_file}"
    log_info "Loaded env file: ${env_file}"
}

backup_file_once() {
    local source_file="$1"
    local backup_file="${source_file}.bak"

    if [[ -f "${source_file}" && ! -f "${backup_file}" ]]; then
        cp "${source_file}" "${backup_file}"
        log_info "Backup created: ${backup_file}"
    fi
}

write_file_if_changed() {
    local target_file="$1"
    local source_file="$2"
    local mode="${3:-0644}"

    if [[ -f "${target_file}" ]] && cmp -s "${source_file}" "${target_file}"; then
        log_info "No change for ${target_file}"
        return 0
    fi

    install -D -m "${mode}" "${source_file}" "${target_file}"
    log_info "Updated ${target_file}"
}

resolve_compose_env_file() {
    if [[ -n "${COMPOSE_ENV_FILE:-}" ]]; then
        printf '%s\n' "${COMPOSE_ENV_FILE}"
        return 0
    fi

    if [[ -f ".env.runtime" ]]; then
        printf '%s/.env.runtime\n' "$(pwd)"
        return 0
    fi

    printf '\n'
}

docker_compose() {
    local env_file
    env_file="$(resolve_compose_env_file)"

    if [[ -n "${env_file}" ]]; then
        docker compose --env-file "${env_file}" "$@"
        return 0
    fi

    docker compose "$@"
}
