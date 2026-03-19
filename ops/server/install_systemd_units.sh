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
    log_warn "No env file provided. Using script defaults."
fi

APP_ROOT="${APP_ROOT:-/srv/saas/app}"
STACK_REQUIRED_SERVICES="${STACK_REQUIRED_SERVICES:-app db}"
STACK_HEALTHCHECK_URL="${STACK_HEALTHCHECK_URL:-http://127.0.0.1/healthz}"
WEEKLY_REBOOT_ONCALENDAR="${WEEKLY_REBOOT_ONCALENDAR:-Sun *-*-* 04:30:00}"

TEMPLATE_DIR="${SCRIPT_DIR}/../systemd"

render_template() {
    local template_file="$1"
    local output_file="$2"

    local escaped_app_root
    escaped_app_root="$(printf '%s' "${APP_ROOT}" | sed 's/[\\/&]/\\&/g')"

    local escaped_reboot_calendar
    escaped_reboot_calendar="$(printf '%s' "${WEEKLY_REBOOT_ONCALENDAR}" | sed 's/[\\/&]/\\&/g')"

    sed \
        -e "s|__APP_ROOT__|${escaped_app_root}|g" \
        -e "s|__WEEKLY_REBOOT_ONCALENDAR__|${escaped_reboot_calendar}|g" \
        "${template_file}" > "${output_file}"
}

install_unit_from_template() {
    local template_name="$1"
    local destination_name="$2"
    local temp_file

    temp_file="$(mktemp)"
    render_template "${TEMPLATE_DIR}/${template_name}" "${temp_file}"
    write_file_if_changed "/etc/systemd/system/${destination_name}" "${temp_file}" "0644"
    rm -f "${temp_file}"
}

install_healthcheck_environment() {
    local env_target="/etc/default/saas-stack-healthcheck"
    local temp_file

    temp_file="$(mktemp)"
    cat > "${temp_file}" <<CFG
# Managed by ops/server/install_systemd_units.sh
APP_ROOT="${APP_ROOT}"
STACK_REQUIRED_SERVICES="${STACK_REQUIRED_SERVICES}"
STACK_HEALTHCHECK_URL="${STACK_HEALTHCHECK_URL}"
COMPOSE_ENV_FILE="${APP_ROOT}/.env.runtime"
CFG

    write_file_if_changed "${env_target}" "${temp_file}" "0644"
    rm -f "${temp_file}"
}

install_healthcheck_script() {
    local script_target="/usr/local/bin/saas-stack-healthcheck.sh"
    local temp_file

    temp_file="$(mktemp)"
    cat > "${temp_file}" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

if [[ -f /etc/default/saas-stack-healthcheck ]]; then
    # shellcheck disable=SC1091
    source /etc/default/saas-stack-healthcheck
fi

APP_ROOT="${APP_ROOT:-/srv/saas/app}"
STACK_REQUIRED_SERVICES="${STACK_REQUIRED_SERVICES:-app db}"
STACK_HEALTHCHECK_URL="${STACK_HEALTHCHECK_URL:-http://127.0.0.1/healthz}"
COMPOSE_ENV_FILE="${COMPOSE_ENV_FILE:-${APP_ROOT}/.env.runtime}"
LOG_FILE="/var/log/saas/saas-stack-healthcheck.log"

log() {
    printf '%s [INFO] [saas-stack-healthcheck] %s\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "$*" | tee -a "${LOG_FILE}" >&2
}

mkdir -p /var/log/saas

if [[ ! -d "${APP_ROOT}" ]]; then
    log "App root not found: ${APP_ROOT}"
    exit 1
fi

cd "${APP_ROOT}"

compose_args=()
if [[ -f "${COMPOSE_ENV_FILE}" ]]; then
    compose_args+=(--env-file "${COMPOSE_ENV_FILE}")
fi

running_services="$(docker compose "${compose_args[@]}" ps --status running --services || true)"
missing_count=0

for required_service in ${STACK_REQUIRED_SERVICES}; do
    if ! grep -qx "${required_service}" <<< "${running_services}"; then
        log "Missing service detected: ${required_service}"
        missing_count=$((missing_count + 1))
    fi
done

if [[ "${missing_count}" -gt 0 ]]; then
    log "Repairing stack via docker compose up -d --remove-orphans"
    docker compose "${compose_args[@]}" up -d --remove-orphans
fi

if ! curl -fsS --max-time 10 "${STACK_HEALTHCHECK_URL}" >/dev/null; then
    log "Health endpoint failed: ${STACK_HEALTHCHECK_URL}. Restarting stack."
    docker compose "${compose_args[@]}" down
    docker compose "${compose_args[@]}" up -d --remove-orphans
fi

log "Healthcheck completed"
SCRIPT

    write_file_if_changed "${script_target}" "${temp_file}" "0755"
    rm -f "${temp_file}"
}

enable_units() {
    run_cmd systemctl daemon-reload
    run_cmd systemctl enable saas-stack.service
    run_cmd systemctl enable --now saas-stack-healthcheck.timer
    run_cmd systemctl enable saas-weekly-reboot.timer
}

main() {
    require_root
    require_command systemctl
    require_command docker

    log_info "Installing SaaS systemd units."
    install_unit_from_template "saas-stack.service.tpl" "saas-stack.service"
    install_unit_from_template "saas-stack-healthcheck.service.tpl" "saas-stack-healthcheck.service"
    install_unit_from_template "saas-stack-healthcheck.timer.tpl" "saas-stack-healthcheck.timer"
    install_unit_from_template "saas-weekly-reboot.service.tpl" "saas-weekly-reboot.service"
    install_unit_from_template "saas-weekly-reboot.timer.tpl" "saas-weekly-reboot.timer"
    install_healthcheck_environment
    install_healthcheck_script
    enable_units
    log_info "SaaS systemd units installed successfully."
}

main "$@"
