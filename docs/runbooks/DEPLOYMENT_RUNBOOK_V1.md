# Runbook Deploy V1 (Dev/Beta/Prod)

Date: 2026-03-03  
Portée: base opérationnelle pour serveur Ubuntu LTS (OVH VPS)

## 1) Objectif

FR: Fournir une trame exécutable, idempotente et auditables pour hardening, déploiement, backup et rollback.  
EN: Provide an executable, idempotent and auditable baseline for hardening, deployment, backup and rollback.

## 2) Arborescence Ops

- `ops/lib/common.sh`: logs horodatés + garde-fous shell
- `ops/server/hardening.sh`: SSH/fail2ban/UFW/apt/logrotate
- `ops/server/install_docker.sh`: installation Docker + compose plugin
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
2. Adapter `server.env` pour l'environnement déployé.
   - `DEPLOY_PROFILE=prod`
   - `SERVER_NAME=demo.dsn-dev.com`
   - `BASE_URI=https://demo.dsn-dev.com`
   - `CHILD_APP_VAULT_API_URL=https://secret-vault.dsn-dev.com`
   - `CHILD_APP_VAULT_LOGIN_URL=https://secret-vault.dsn-dev.com/login`
   - `CHILD_APP_VAULT_API_TOKEN=<même token que CHILD_APP_PROVISIONING_TOKEN côté vault>`
3. Convention de répertoire:
   - app mère: `/srv/saas/app`
   - releases internes: `/srv/saas/releases`
   - le choix de la version à déployer reste côté GitHub Actions, `current/app` ne sert qu'au switch atomique côté serveur
4. Créer la clé de chiffrement backup:
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
   Si le hardening SSH/fail2ban/UFW est déjà géré hors repo, cette étape peut être ignorée.
2. Installation Docker + compose:
   ```bash
   sudo ops/server/install_docker.sh ops/config/server.env
   ```
3. Services systemd stack (auto restart + healthcheck + reboot hebdo):
   ```bash
   sudo ops/server/install_systemd_units.sh ops/config/server.env
   ```
4. Monitoring proxy (optionnel mais recommandé):
   ```bash
   sudo ops/server/configure_monitoring_proxy.sh ops/config/server.env
   ```
   Variables publiques de l'admin mère:
   - `NETDATA_PUBLIC_URL=https://monitor.dsn-dev.com/netdata`
   - `UPTIME_KUMA_PUBLIC_URL=https://monitor.dsn-dev.com/uptime`
5. Backup automatique:
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

## 5bis) Mapping domaines

- app mère: `demo.dsn-dev.com`
- app vault: `secret-vault.dsn-dev.com`

Pour les prochaines apps filles, garder le même modèle:
1. un dépôt dédié,
2. un sous-domaine dédié,
3. un `APP_BASE_DIR` dédié sur le serveur,
4. la même convention de secrets GitHub `PROD_*` repo par repo,
5. un triplet de variables dans `server.env` de la mère: `CHILD_APP_<KEY>_API_URL`, `CHILD_APP_<KEY>_LOGIN_URL`, `CHILD_APP_<KEY>_API_TOKEN`,
6. une entrée correspondante dans `config/packages/child_apps.yaml`,
7. un `SERVER_NAME` et un `BASE_URI` dédiés par app.

Convention recommandée:
1. clé applicative en minuscules, stable, sans espaces: `vault`, `ops`, `crm`
2. sous-domaine miroir: `secret-vault.dsn-dev.com`, `ops.dsn-dev.com`, `crm.dsn-dev.com`
3. le `LOGIN_URL` reste public, l'`API_URL` peut rester public ou devenir privé si les apps partagent un réseau interne.
4. le répertoire applicatif publié reste `/srv/<app>/app`, même si les releases physiques sont stockées dans `/srv/<app>/releases`.

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

## 8bis) Monitoring

Sous-domaine dédié recommandé si:
1. tu veux accéder au monitoring depuis l'extérieur,
2. tu veux isoler proprement l'authentification,
3. tu veux éviter de mélanger app métier et observabilité.

Configuration retenue:
1. sous-domaine: `monitor.dsn-dev.com`
2. Netdata: `https://monitor.dsn-dev.com/netdata`
3. Uptime Kuma: `https://monitor.dsn-dev.com/uptime`

Pas obligatoire si:
1. le monitoring reste privé via VPN/Tailscale,
2. ou accessible seulement en loopback/reverse proxy d'admin.

## 9) Notes de sécurité

1. `PermitRootLogin` peut rester `prohibit-password` en phase 1 (clé only).
2. `SSH_PASSWORD_AUTHENTICATION=false` coupe les mots de passe seulement si `SSH_KEY_USER` a déjà une clé autorisée.
3. Avant prod finale: appliquer hardening final (port SSH custom, revue accès root, audit règles firewall).
4. Ne jamais committer `ops/config/server.env` et `ops/config/backup.env` avec secrets réels.
