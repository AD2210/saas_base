#!/usr/bin/env bash

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../lib/common.sh"

init_logging
setup_error_trap

ENV_FILE="${1:-${SCRIPT_DIR}/../config/server.env}"
ARTIFACT_PATH="${2:-}"

if [[ -z "${ARTIFACT_PATH}" ]]; then
    log_error "Usage: deploy_artifact.sh <server_env_file> <artifact_tar_gz>"
    exit 1
fi

if [[ ! -f "${ARTIFACT_PATH}" ]]; then
    log_error "Artifact file not found: ${ARTIFACT_PATH}"
    exit 1
fi

WORK_DIR="$(mktemp -d)"
cleanup() {
    rm -rf "${WORK_DIR}"
}
trap cleanup EXIT

extract_artifact() {
    log_info "Extracting artifact ${ARTIFACT_PATH}"
    tar -xzf "${ARTIFACT_PATH}" -C "${WORK_DIR}"
}

main() {
    require_command tar

    extract_artifact

    if [[ ! -x "${WORK_DIR}/ops/server/deploy_release.sh" ]]; then
        chmod +x "${WORK_DIR}/ops/lib/common.sh" "${WORK_DIR}/ops/server/"*.sh
    fi

    log_info "Delegating deployment to deploy_release.sh"
    "${WORK_DIR}/ops/server/deploy_release.sh" "${ENV_FILE}" "${WORK_DIR}"
}

main "$@"
