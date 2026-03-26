#!/usr/bin/env bash

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../lib/common.sh"

init_logging
setup_error_trap

ENV_FILE="${1:-${SCRIPT_DIR}/../config/server.env}"
MONITORING_USER_INPUT="${2:-}"
MONITORING_PASSWORD_INPUT="${3:-}"

random_password() {
    tr -dc 'A-Za-z0-9@#%+=_:.,-' </dev/urandom | head -c 24
}

upsert_env_value() {
    local env_file="$1"
    local key="$2"
    local value="$3"
    local tmp_file

    tmp_file="$(mktemp)"

    if [[ -f "${env_file}" ]] && grep -q "^${key}=" "${env_file}"; then
        sed "s#^${key}=.*#${key}=${value}#" "${env_file}" > "${tmp_file}"
    else
        if [[ -f "${env_file}" ]]; then
            cat "${env_file}" > "${tmp_file}"
        fi
        printf '%s=%s\n' "${key}" "${value}" >> "${tmp_file}"
    fi

    write_file_if_changed "${env_file}" "${tmp_file}" "0600"
    rm -f "${tmp_file}"
}

main() {
    require_root
    require_command caddy

    if [[ ! -f "${ENV_FILE}" ]]; then
        log_error "Env file not found: ${ENV_FILE}"
        exit 1
    fi

    load_env_file "${ENV_FILE}"

    local monitoring_user="${MONITORING_USER_INPUT:-${MONITORING_BASIC_AUTH_USER:-monitoring}}"
    local monitoring_password="${MONITORING_PASSWORD_INPUT:-}"
    if [[ -z "${monitoring_password}" ]]; then
        monitoring_password="$(random_password)"
    fi

    local monitoring_hash
    monitoring_hash="$(caddy hash-password --plaintext "${monitoring_password}")"

    backup_file_once "${ENV_FILE}"
    upsert_env_value "${ENV_FILE}" "MONITORING_BASIC_AUTH_USER" "${monitoring_user}"
    upsert_env_value "${ENV_FILE}" "MONITORING_BASIC_AUTH_PASSWORD_HASH" "${monitoring_hash}"

    cat <<OUT
Monitoring credentials updated in ${ENV_FILE}
MONITORING_BASIC_AUTH_USER=${monitoring_user}
MONITORING_BASIC_AUTH_PASSWORD=${monitoring_password}

Next step:
sudo ${SCRIPT_DIR}/configure_monitoring_proxy.sh ${ENV_FILE}
OUT
}

main "$@"
