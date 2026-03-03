#!/usr/bin/env bash

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../lib/common.sh"

init_logging
setup_error_trap

ENV_FILE="${1:-${SCRIPT_DIR}/../config/backup.env}"
if [[ -f "${ENV_FILE}" ]]; then
    load_env_file "${ENV_FILE}"
else
    log_error "Missing backup env file: ${ENV_FILE}"
    exit 1
fi

DB_CONTAINER="${DB_CONTAINER:-saas-core-db}"
DB_MAIN_NAME="${DB_MAIN_NAME:-main_db}"
TENANT_DB_PATTERN="${TENANT_DB_PATTERN:-db_%}"
BACKUP_DIR="${BACKUP_DIR:-/opt/backups/saas}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-10}"
BACKUP_ENCRYPTION_KEY_FILE="${BACKUP_ENCRYPTION_KEY_FILE:-/etc/saas/backup.key}"
RCLONE_REMOTE="${RCLONE_REMOTE:-}"

BACKUP_TIMESTAMP="$(date -u +"%Y%m%dT%H%M%SZ")"
WORK_DIR="$(mktemp -d)"
ARCHIVE_PREFIX="saas-db-${BACKUP_TIMESTAMP}"

cleanup() {
    rm -rf "${WORK_DIR}"
}
trap cleanup EXIT

assert_prerequisites() {
    require_command docker
    require_command openssl
    require_command tar
    require_command sha256sum

    if [[ -n "${RCLONE_REMOTE}" ]]; then
        require_command rclone
    fi

    if [[ ! -f "${BACKUP_ENCRYPTION_KEY_FILE}" ]]; then
        log_error "Encryption key file not found: ${BACKUP_ENCRYPTION_KEY_FILE}"
        exit 1
    fi

    if ! docker ps --format '{{.Names}}' | grep -qx "${DB_CONTAINER}"; then
        log_error "Database container is not running: ${DB_CONTAINER}"
        exit 1
    fi
}

list_tenant_databases() {
    docker exec -u postgres "${DB_CONTAINER}" psql -d postgres -Atc \
        "SELECT datname FROM pg_database WHERE datistemplate = false AND datname LIKE '${TENANT_DB_PATTERN}' AND datname <> '${DB_MAIN_NAME}' ORDER BY datname;"
}

dump_database() {
    local db_name="$1"
    local output_file="$2"

    log_info "Dumping database: ${db_name}"
    docker exec -u postgres "${DB_CONTAINER}" pg_dump -d "${db_name}" -Fc > "${output_file}"
}

create_manifest() {
    local manifest_file="${WORK_DIR}/manifest.txt"

    {
        echo "timestamp=${BACKUP_TIMESTAMP}"
        echo "main_db=${DB_MAIN_NAME}"
        echo "tenant_db_pattern=${TENANT_DB_PATTERN}"
        echo "source_container=${DB_CONTAINER}"
        echo "files="
        find "${WORK_DIR}" -maxdepth 1 -type f -name '*.dump' -printf '%f\n' | sort
    } > "${manifest_file}"

    (cd "${WORK_DIR}" && sha256sum ./*.dump manifest.txt > checksums.sha256)
}

create_encrypted_archive() {
    local archive_plain="${BACKUP_DIR}/${ARCHIVE_PREFIX}.tar.gz"
    local archive_encrypted="${archive_plain}.enc"

    mkdir -p "${BACKUP_DIR}"

    log_info "Creating compressed archive"
    tar -C "${WORK_DIR}" -czf "${archive_plain}" .

    log_info "Encrypting archive with AES-256"
    openssl enc -aes-256-cbc -pbkdf2 -salt \
        -in "${archive_plain}" \
        -out "${archive_encrypted}" \
        -pass "file:${BACKUP_ENCRYPTION_KEY_FILE}"

    rm -f "${archive_plain}"

    log_info "Encrypted archive created: ${archive_encrypted}"
    printf '%s\n' "${archive_encrypted}"
}

upload_to_remote() {
    local encrypted_archive="$1"

    if [[ -z "${RCLONE_REMOTE}" ]]; then
        log_info "No Rclone remote configured. Skipping external upload."
        return 0
    fi

    log_info "Uploading archive to remote: ${RCLONE_REMOTE}"
    rclone copy "${encrypted_archive}" "${RCLONE_REMOTE}"
}

cleanup_old_backups() {
    log_info "Removing local backups older than ${BACKUP_RETENTION_DAYS} days"
    find "${BACKUP_DIR}" -type f -name 'saas-db-*.tar.gz.enc' -mtime +"${BACKUP_RETENTION_DAYS}" -delete
}

main() {
    assert_prerequisites

    dump_database "${DB_MAIN_NAME}" "${WORK_DIR}/${DB_MAIN_NAME}.dump"

    while IFS= read -r tenant_db; do
        [[ -z "${tenant_db}" ]] && continue
        dump_database "${tenant_db}" "${WORK_DIR}/${tenant_db}.dump"
    done < <(list_tenant_databases)

    create_manifest
    encrypted_archive="$(create_encrypted_archive)"
    upload_to_remote "${encrypted_archive}"
    cleanup_old_backups

    log_info "Database backup completed successfully."
}

main "$@"
