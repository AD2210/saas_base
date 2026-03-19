#!/usr/bin/env bash

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../lib/common.sh"

init_logging
setup_error_trap

ENV_FILE="${1:-${SCRIPT_DIR}/../config/server.env}"
if [[ -f "${ENV_FILE}" ]]; then
    load_env_file "${ENV_FILE}"
else
    log_error "Missing env file for monitoring proxy: ${ENV_FILE}"
    exit 1
fi

MONITORING_SERVER_NAME="${MONITORING_SERVER_NAME:-${MONITORING_DOMAIN:-}}"
MONITORING_BASIC_AUTH_USER="${MONITORING_BASIC_AUTH_USER:-ops}"
MONITORING_BASIC_AUTH_PASSWORD_HASH="${MONITORING_BASIC_AUTH_PASSWORD_HASH:-}"
NETDATA_UPSTREAM="${NETDATA_UPSTREAM:-127.0.0.1:19999}"
UPTIME_KUMA_UPSTREAM="${UPTIME_KUMA_UPSTREAM:-127.0.0.1:3001}"

require_monitoring_env() {
    require_env MONITORING_SERVER_NAME
    require_env MONITORING_BASIC_AUTH_PASSWORD_HASH

    if [[ "${MONITORING_BASIC_AUTH_PASSWORD_HASH}" != '$2a$'* && "${MONITORING_BASIC_AUTH_PASSWORD_HASH}" != '$2y$'* && "${MONITORING_BASIC_AUTH_PASSWORD_HASH}" != '$argon2'* ]]; then
        log_warn "Password hash format is uncommon. Expected output of 'caddy hash-password'."
    fi
}

ensure_caddy_import() {
    local caddyfile="/etc/caddy/Caddyfile"
    local import_line="import /etc/caddy/conf.d/*.caddy"

    if grep -Fq "${import_line}" "${caddyfile}"; then
        log_info "Caddy import already configured."
        return 0
    fi

    backup_file_once "${caddyfile}"
    printf '\n%s\n' "${import_line}" >> "${caddyfile}"
    log_info "Added Caddy import for conf.d snippets."
}

write_monitoring_snippet() {
    local snippet_target="/etc/caddy/conf.d/saas-monitoring.caddy"
    local temp_file

    mkdir -p /etc/caddy/conf.d
    temp_file="$(mktemp)"

    cat > "${temp_file}" <<CFG
# Managed by ops/server/configure_monitoring_proxy.sh
# WHY (FR): Monitoring non exposé publiquement sans authentification.
# WHY (EN): Monitoring endpoints must stay protected behind auth.
https://${MONITORING_SERVER_NAME} {
    @netdata path /netdata /netdata*
    handle @netdata {
        basicauth {
            ${MONITORING_BASIC_AUTH_USER} ${MONITORING_BASIC_AUTH_PASSWORD_HASH}
        }
        uri strip_prefix /netdata
        reverse_proxy ${NETDATA_UPSTREAM}
    }

    @uptime path /uptime /uptime*
    handle @uptime {
        basicauth {
            ${MONITORING_BASIC_AUTH_USER} ${MONITORING_BASIC_AUTH_PASSWORD_HASH}
        }
        uri strip_prefix /uptime
        reverse_proxy ${UPTIME_KUMA_UPSTREAM}
    }

    respond "Not Found" 404
}
CFG

    write_file_if_changed "${snippet_target}" "${temp_file}" "0644"
    rm -f "${temp_file}"
}

reload_caddy() {
    run_cmd caddy validate --config /etc/caddy/Caddyfile
    run_cmd systemctl reload caddy
}

main() {
    require_root
    require_command caddy
    require_command systemctl

    require_monitoring_env
    ensure_caddy_import
    write_monitoring_snippet
    reload_caddy

    log_info "Monitoring reverse proxy configured successfully."
}

main "$@"
