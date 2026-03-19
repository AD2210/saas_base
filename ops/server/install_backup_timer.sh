#!/usr/bin/env bash

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../lib/common.sh"

init_logging
setup_error_trap

ENV_FILE="${1:-${SCRIPT_DIR}/../config/backup.env}"
if [[ ! -f "${ENV_FILE}" ]]; then
    log_error "Backup env file is required: ${ENV_FILE}"
    exit 1
fi

load_env_file "${ENV_FILE}"

APP_ROOT="${APP_ROOT:-/srv/saas/app}"
BACKUP_ONCALENDAR="${BACKUP_ONCALENDAR:-hourly}"
BACKUP_ENV_TARGET="${BACKUP_ENV_TARGET:-/etc/saas/backup.env}"
TEMPLATE_DIR="${SCRIPT_DIR}/../systemd"

install_backup_env() {
    mkdir -p "$(dirname -- "${BACKUP_ENV_TARGET}")"
    if [[ "$(readlink -f "${ENV_FILE}")" == "$(readlink -f "${BACKUP_ENV_TARGET}")" ]]; then
        chmod 0600 "${BACKUP_ENV_TARGET}"
        log_info "Backup env target already in place: ${BACKUP_ENV_TARGET}"
        return 0
    fi
    install -m 0600 "${ENV_FILE}" "${BACKUP_ENV_TARGET}"
    log_info "Installed backup env file to ${BACKUP_ENV_TARGET}"
}

install_backup_wrapper() {
    local wrapper_target="/usr/local/bin/saas-backup-db.sh"
    local temp_file

    temp_file="$(mktemp)"

    cat > "${temp_file}" <<SCRIPT
#!/usr/bin/env bash
set -Eeuo pipefail

exec "${APP_ROOT}/ops/server/backup_db.sh" "${BACKUP_ENV_TARGET}"
SCRIPT

    write_file_if_changed "${wrapper_target}" "${temp_file}" "0755"
    rm -f "${temp_file}"
}

render_backup_template() {
    local template_file="$1"
    local output_file="$2"
    local escaped_calendar

    escaped_calendar="$(printf '%s' "${BACKUP_ONCALENDAR}" | sed 's/[\\/&]/\\&/g')"

    sed -e "s|__BACKUP_ONCALENDAR__|${escaped_calendar}|g" "${template_file}" > "${output_file}"
}

install_unit_from_template() {
    local template_name="$1"
    local destination_name="$2"
    local temp_file

    temp_file="$(mktemp)"
    render_backup_template "${TEMPLATE_DIR}/${template_name}" "${temp_file}"
    write_file_if_changed "/etc/systemd/system/${destination_name}" "${temp_file}" "0644"
    rm -f "${temp_file}"
}

enable_backup_timer() {
    run_cmd systemctl daemon-reload
    run_cmd systemctl enable --now saas-db-backup.timer
}

main() {
    require_root
    require_command systemctl

    install_backup_env
    install_backup_wrapper
    install_unit_from_template "saas-db-backup.service.tpl" "saas-db-backup.service"
    install_unit_from_template "saas-db-backup.timer.tpl" "saas-db-backup.timer"
    enable_backup_timer

    log_info "Backup timer installed successfully."
}

main "$@"
