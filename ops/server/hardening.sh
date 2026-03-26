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

SSH_PORT="${SSH_PORT:-22}"
ALLOW_ROOT_LOGIN="${ALLOW_ROOT_LOGIN:-true}"
SSH_PASSWORD_AUTHENTICATION="${SSH_PASSWORD_AUTHENTICATION:-false}"
SSH_KEY_USER="${SSH_KEY_USER:-${SUDO_USER:-ubuntu}}"
FAIL2BAN_MAXRETRY="${FAIL2BAN_MAXRETRY:-3}"
FAIL2BAN_BANTIME="${FAIL2BAN_BANTIME:-1h}"
LOG_RETENTION_DAYS="${LOG_RETENTION_DAYS:-30}"

configure_apt_baseline() {
    # FR: Installer la baseline ici garantit des scripts prévisibles sur toutes les VPS neuves.
    # EN: Installing a baseline avoids environment drift between fresh VPS nodes.
    run_cmd apt-get update -y
    run_cmd apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        fail2ban \
        jq \
        logrotate \
        openssh-server \
        rsync \
        ufw \
        unattended-upgrades
}

ensure_ssh_key_before_disabling_password_auth() {
    if [[ "${SSH_PASSWORD_AUTHENTICATION}" == "true" ]]; then
        return 0
    fi

    local key_user="${SSH_KEY_USER}"
    local key_home

    key_home="$(getent passwd "${key_user}" | cut -d: -f6 || true)"
    if [[ -z "${key_home}" ]]; then
        log_error "Unable to resolve home directory for SSH_KEY_USER=${key_user}."
        exit 1
    fi

    if [[ ! -s "${key_home}/.ssh/authorized_keys" ]]; then
        log_error "Refusing to disable SSH password auth without a non-empty ${key_home}/.ssh/authorized_keys."
        exit 1
    fi
}

configure_ssh() {
    local ssh_dropin="/etc/ssh/sshd_config.d/00-saas-hardening.conf"
    local legacy_ssh_dropin="/etc/ssh/sshd_config.d/60-saas-hardening.conf"
    local root_login_mode="no"
    local password_auth_mode="no"

    if [[ "${ALLOW_ROOT_LOGIN}" == "true" ]]; then
        root_login_mode="prohibit-password"
    fi

    if [[ "${SSH_PASSWORD_AUTHENTICATION}" == "true" ]]; then
        password_auth_mode="yes"
    fi

    ensure_ssh_key_before_disabling_password_auth

    local temp_file
    temp_file="$(mktemp)"

    cat > "${temp_file}" <<CFG
# Managed by ops/server/hardening.sh
# WHY (FR): clé SSH only + anti-bruteforce réduisent la surface d'attaque.
# WHY (EN): key-only auth + low auth retries reduce brute-force risk.
Port ${SSH_PORT}
PubkeyAuthentication yes
PasswordAuthentication ${password_auth_mode}
KbdInteractiveAuthentication no
PermitRootLogin ${root_login_mode}
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
CFG

    write_file_if_changed "${ssh_dropin}" "${temp_file}" "0644"
    rm -f "${temp_file}"

    if [[ -f "${legacy_ssh_dropin}" ]]; then
        rm -f "${legacy_ssh_dropin}"
        log_info "Removed legacy SSH drop-in: ${legacy_ssh_dropin}"
    fi

    run_cmd sshd -t

    if systemctl list-unit-files | grep -q '^ssh\.service'; then
        run_cmd systemctl restart ssh
    else
        run_cmd systemctl restart sshd
    fi
}

ensure_ufw_rule() {
    local rule="$1"

    if ufw status | grep -Fq "${rule}"; then
        log_info "UFW rule already present: ${rule}"
        return 0
    fi

    run_cmd ufw allow "${rule}"
}

configure_firewall() {
    # FR: On garde uniquement les ports utiles à l'app et à son exposition web.
    # EN: Keep only ports required for app operations and web exposure.
    run_cmd ufw default deny incoming
    run_cmd ufw default allow outgoing

    ensure_ufw_rule "${SSH_PORT}/tcp"
    ensure_ufw_rule "80/tcp"
    ensure_ufw_rule "443/tcp"

    run_cmd ufw --force enable
}

configure_fail2ban() {
    local jail_file="/etc/fail2ban/jail.d/sshd-saas.local"
    local temp_file
    temp_file="$(mktemp)"

    cat > "${temp_file}" <<CFG
# Managed by ops/server/hardening.sh
[sshd]
enabled = true
port = ${SSH_PORT}
maxretry = ${FAIL2BAN_MAXRETRY}
bantime = ${FAIL2BAN_BANTIME}
findtime = 10m
CFG

    write_file_if_changed "${jail_file}" "${temp_file}" "0644"
    rm -f "${temp_file}"

    run_cmd systemctl enable --now fail2ban
    run_cmd systemctl restart fail2ban
}

configure_unattended_upgrades() {
    local auto_upgrades_file="/etc/apt/apt.conf.d/20auto-upgrades"
    local temp_file
    temp_file="$(mktemp)"

    cat > "${temp_file}" <<CFG
// Managed by ops/server/hardening.sh
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
CFG

    write_file_if_changed "${auto_upgrades_file}" "${temp_file}" "0644"
    rm -f "${temp_file}"

    run_cmd systemctl enable --now unattended-upgrades
}

configure_logrotate() {
    local rotate_file="/etc/logrotate.d/saas-stack"
    local temp_file
    temp_file="$(mktemp)"

    mkdir -p /var/log/saas

    cat > "${temp_file}" <<CFG
# Managed by ops/server/hardening.sh
/var/log/saas/*.log {
    daily
    rotate ${LOG_RETENTION_DAYS}
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
}
CFG

    write_file_if_changed "${rotate_file}" "${temp_file}" "0644"
    rm -f "${temp_file}"

    run_cmd logrotate -f "${rotate_file}"
}

main() {
    require_root
    require_command apt-get
    require_command getent
    require_command systemctl

    log_info "Starting server hardening baseline."
    configure_apt_baseline
    configure_ssh
    configure_firewall
    configure_fail2ban
    configure_unattended_upgrades
    configure_logrotate
    log_info "Server hardening baseline completed successfully."
}

main "$@"
