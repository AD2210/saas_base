# Runbook Deploy V1 (Dev/Beta/Prod)

Date: 2026-03-03  
Portée: base opérationnelle pour serveur Ubuntu LTS (OVH VPS)

## 1) Objectif

FR: Fournir une trame exécutable, idempotente et auditables pour hardening, déploiement, backup et rollback.  
EN: Provide an executable, idempotent and auditable baseline for hardening, deployment, backup and rollback.

## 2) Arborescence Ops

- `ops/lib/common.sh`: logs horodatés + garde-fous shell
- `ops/server/hardening.sh`: SSH/fail2ban/UFW/apt/logrotate
- `ops/server/install_systemd_units.sh`: auto-restart stack + healthcheck + reboot hebdo
- `ops/server/configure_monitoring_proxy.sh`: proxy Caddy + auth Netdata/Uptime Kuma
- `ops/server/backup_db.sh`: dump chiffré AES-256 + rotation + rclone
- `ops/server/install_backup_timer.sh`: timer systemd de backup
- `ops/server/deploy_release.sh`: déploiement release avec healthcheck
- `ops/server/rollback_release.sh`: rollback vers release précédente (ou ciblée)
- `ops/server/run_phase1_setup.sh`: orchestration phase 1
- `ops/config/*.env.example`: exemples de configuration

## 3) Préparation des variables

1. Copier les exemples:
   ```bash
   cp ops/config/server.env.example ops/config/server.env
   cp ops/config/backup.env.example ops/config/backup.env
   ```
2. Adapter `server.env` par environnement (`dev`, `beta`, `prod`).
   - `DEPLOY_PROFILE=beta` pour beta/dev
   - `DEPLOY_PROFILE=prod` pour prod (active les exclusions debug/fixtures supplémentaires)
3. Créer la clé de chiffrement backup:
   ```bash
   sudo install -d -m 0700 /etc/saas
   sudo sh -c "openssl rand -hex 32 > /etc/saas/backup.key"
   sudo chmod 0600 /etc/saas/backup.key
   ```

## 4) Bootstrap serveur (ordre recommandé)

1. Hardening + base sécurité:
   ```bash
   sudo ops/server/hardening.sh ops/config/server.env
   ```
2. Services systemd stack (auto restart + healthcheck + reboot hebdo):
   ```bash
   sudo ops/server/install_systemd_units.sh ops/config/server.env
   ```
3. Monitoring proxy (optionnel mais recommandé en beta/prod):
   ```bash
   sudo ops/server/configure_monitoring_proxy.sh ops/config/server.env
   ```
4. Backup automatique:
   ```bash
   sudo ops/server/install_backup_timer.sh ops/config/backup.env
   ```

## 5) Déploiement release

1. Copier l’artefact applicatif vers le serveur.
2. Lancer:
   ```bash
   ops/server/deploy_release.sh ops/config/server.env /path/to/artifact
   ```
3. Contrôler:
   - `curl -f http://127.0.0.1/healthz`
   - `systemctl status saas-stack.service`
   - `docker compose ps` dans `current`

Politique d’artefact:
1. Beta: inclut les routes debug (accès protégé).
2. Prod: exclut `src/Debug`, `config/routes/debug.yaml`, `src/DataFixtures`, `public/test.php`, `tests`.

## 6) Rollback

1. Rollback automatique vers release précédente:
   ```bash
   ops/server/rollback_release.sh ops/config/server.env
   ```
2. Rollback vers release ciblée:
   ```bash
   ops/server/rollback_release.sh ops/config/server.env 20260303T120000Z
   ```

## 7) Logs et diagnostic

- Logs ops: `/var/log/saas/ops.log`
- Logs stack systemd: `/var/log/saas/saas-stack.log`
- Logs healthcheck: `/var/log/saas/saas-stack-healthcheck.log`
- Logs backup: `/var/log/saas/saas-db-backup.log`

## 8) Contrôles post-déploiement (checklist)

1. `healthz` et `ready` OK.
2. Admin `/admin` accessible (auth HTTP Basic).
3. Timer backup actif: `systemctl status saas-db-backup.timer`.
4. Timer reboot actif: `systemctl status saas-weekly-reboot.timer`.
5. Netdata/Uptime Kuma exposés uniquement via proxy + auth.

## 9) Notes de sécurité

1. `PermitRootLogin` peut rester `prohibit-password` en phase 1 (clé only).
2. Avant prod finale: appliquer hardening final (port SSH custom, revue accès root, audit règles firewall).
3. Ne jamais committer `ops/config/server.env` et `ops/config/backup.env` avec secrets réels.
