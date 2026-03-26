#!/usr/bin/env bash

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../lib/common.sh"

init_logging
setup_error_trap

ENV_FILE="${1:-${SCRIPT_DIR}/../config/server.env}"
if [[ ! -f "${ENV_FILE}" ]]; then
    log_error "Env file is required: ${ENV_FILE}"
    exit 1
fi

load_env_file "${ENV_FILE}"
ENABLE_MONITORING_PROXY="${ENABLE_MONITORING_PROXY:-false}"

main() {
    require_root

    log_info "Starting phase 1 server setup pipeline."
    run_cmd "${SCRIPT_DIR}/hardening.sh" "${ENV_FILE}"
    run_cmd "${SCRIPT_DIR}/install_docker.sh" "${ENV_FILE}"
    run_cmd "${SCRIPT_DIR}/install_systemd_units.sh" "${ENV_FILE}"

    if [[ "${ENABLE_MONITORING_PROXY}" == "true" ]]; then
        run_cmd "${SCRIPT_DIR}/configure_monitoring_proxy.sh" "${ENV_FILE}"
    else
        log_info "Monitoring proxy step skipped (ENABLE_MONITORING_PROXY=false)."
    fi

    log_info "Phase 1 server setup pipeline completed."
}

main "$@"
