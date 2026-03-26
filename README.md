# SaaS Core Starter (Symfony 7.4, PHP 8.5, Postgres, Docker, FrankenPHP)

This bundle contains starter files to overlay on top of a fresh Symfony 7.4 WebApp.
Use it as a base for multi-tenant projects with DB-per-tenant.

## Quick start
1) Create the Symfony app and install deps:
   ```bash
   symfony new --version=7.4 --webapp saas && cd saas
   composer require symfony/orm-pack symfony/monolog-bundle symfony/notifier symfony/rate-limiter doctrine/doctrine-migrations-bundle
   composer require --dev phpunit/phpunit doctrine/doctrine-fixtures-bundle phpstan/phpstan phpstan/phpstan-symfony friendsofphp/php-cs-fixer symfony/browser-kit symfony/css-selector
   ```
2) Drop these files into the repo root (`saas/`), then set local ports and DB name:
   ```bash
   echo -e "\nAPP_HTTP_PORT=8088\nPOSTGRES_PORT=5442\nMAIN_DB_NAME=saas_base_main" >> .env.local
   ```
   Use a unique `MAIN_DB_NAME` per project to avoid migration collisions.
3) Start and migrate the main DB:
   ```bash
   make up
   bin/console doctrine:database:create --if-not-exists
   bin/console doctrine:migrations:migrate --no-interaction
   ```
   `make up` also starts a messenger worker (`worker`) so async email is consumed in dev.
4) Run tests:
   ```bash
   composer qa:phpunit
   ```

## Admin back-office
1) URL: `/admin`
2) Auth: HTTP Basic (`super_admin` + `APP_ADMIN_PASSWORD`)
3) Env vars:
   - `APP_ADMIN_PASSWORD`
   - `NETDATA_PUBLIC_URL` (optional)
   - `UPTIME_KUMA_PUBLIC_URL` (optional)

## Monitoring stack (Netdata + Uptime Kuma)
1) Start monitoring services:
   ```bash
   make monitoring-up
   ```
2) Access local dashboards:
   - Netdata: `http://127.0.0.1:19999`
   - Uptime Kuma: `http://127.0.0.1:3001`
3) Configure optional links in admin.
   In production behind the monitoring proxy:
   ```dotenv
   NETDATA_PUBLIC_URL=https://monitor.dsn-dev.com/netdata
   UPTIME_KUMA_PUBLIC_URL=https://monitor.dsn-dev.com/uptime
   ```
   In local dev, you can still use:
   ```dotenv
   NETDATA_PUBLIC_URL=http://127.0.0.1:19999
   UPTIME_KUMA_PUBLIC_URL=http://127.0.0.1:3001
   ```
4) Email alerts:
   - open Uptime Kuma Settings -> Notifications
   - add SMTP OVH configuration
   - bind notifications to critical monitors (`/healthz`, `/ready`, DB port, SSL expiry)

## Admin troubleshooting
If `/admin` returns a `500` mentioning EasyAdmin dashboard route cache:
```bash
php -r "foreach (glob('var/cache/*/easyadmin/routes-dashboard.php') ?: [] as $f) { @unlink($f); }"
php bin/console cache:warmup --env=dev
```

## Demo onboarding flow
1) Landing form posts to `/api/demo-requests`.
2) Backend provisions tenant + demo request, then queues onboarding email through Messenger.
3) Onboarding link points to `/onboarding/set-password?token=...` with one-time token semantics.
4) On password submit, mother app syncs the tenant admin into the vault child app contract `tenant-admin-provisioning:v1`.
5) Child app catalog lives in `config/packages/child_apps.yaml`.
6) Each demo request carries a `child_app_key`, so `/demo/{childAppKey}` can load a dedicated copy/theme and route onboarding to the matching child app.
7) Contract details: `docs/CHILD_APP_CONTRACT_V1.md`
8) Integration runbook (commands + validations): `docs/runbooks/CHILD_APP_INTEGRATION_RUNBOOK_V1.md`

Local Docker note:
- for the local integration path in this repository, `docker compose` can run a `child-app` service from `../client_secret_vault`
- default child app key is `vault`
- expose the child app locally with `CHILD_APP_HTTP_PORT=8090`
- local profile env vars are:
  - `CHILD_APP_VAULT_API_URL=http://child-app:8000`
  - `CHILD_APP_VAULT_LOGIN_URL=http://127.0.0.1:8090/login`
  - `CHILD_APP_VAULT_API_TOKEN=...`
- access the mother app through `http://127.0.0.1:8088`
- do not use `symfony serve` for this stack: it bypasses the Docker DB settings and can fail against `127.0.0.1:5432`
- production server env template: `.env.prod.example` (copy to `.env` on the server deploy path)

## Debug routes policy
1) Debug endpoints use `/debug/*` prefix.
2) Debug routes are loaded only in `dev` and `test` in the current deployment model.
3) Access is restricted with HTTP Basic (`ROLE_SUPER_ADMIN`).
4) Production artifact excludes debug routes/controllers and fixtures.

## Ops automation (phase 1)
The repository now includes operational scripts for server setup and deployment:

- Hardening: `ops/server/hardening.sh`
- Docker baseline: `ops/server/install_docker.sh`
- Systemd units (stack restart, healthcheck, weekly reboot): `ops/server/install_systemd_units.sh`
- Monitoring reverse proxy + auth: `ops/server/configure_monitoring_proxy.sh`
- Monitoring credentials generation: `ops/server/generate_monitoring_credentials.sh`
- Encrypted DB backups + rotation + rclone: `ops/server/backup_db.sh`
- Backup timer install: `ops/server/install_backup_timer.sh`
- Release deployment: `ops/server/deploy_release.sh`
- Rollback: `ops/server/rollback_release.sh`

See full runbook: `docs/runbooks/DEPLOYMENT_RUNBOOK_V1.md`
