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

DOCKER_ADMIN_USER="${DOCKER_ADMIN_USER:-${SUDO_USER:-ubuntu}}"
DOCKER_PACKAGES="${DOCKER_PACKAGES:-docker.io docker-compose-v2}"

install_docker_packages() {
    local -a docker_packages=()

    IFS=' ' read -r -a docker_packages <<< "${DOCKER_PACKAGES}"

    run_cmd apt-get update -y
    run_cmd apt-get install -y --no-install-recommends "${docker_packages[@]}"
}

enable_docker_service() {
    run_cmd systemctl enable --now docker
}

grant_docker_group_access() {
    local admin_user="$1"

    if ! id -u "${admin_user}" >/dev/null 2>&1; then
        log_warn "Skipping docker group grant: user not found (${admin_user})."
        return 0
    fi

    run_cmd usermod -aG docker "${admin_user}"
    log_info "Added ${admin_user} to docker group. A new login session is required for interactive docker usage."
}

main() {
    require_root
    require_command apt-get
    require_command systemctl

    log_info "Installing Docker baseline."
    install_docker_packages
    enable_docker_service
    grant_docker_group_access "${DOCKER_ADMIN_USER}"
    log_info "Docker baseline installed successfully."
}

main "$@"
