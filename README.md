# SaaS Core Starter (Symfony 7.3, Postgres, Docker, FrankenPHP)

This bundle contains starter files to overlay on top of a fresh Symfony 7.3 WebApp.
Use it as a base for multi-tenant projects with DB-per-tenant.

## Quick start
1) Create the Symfony app and install deps:
   ```bash
   symfony new --version=7.3 --webapp saas && cd saas
   composer require symfony/orm-pack symfony/monolog-bundle symfony/notifier symfony/rate-limiter doctrine/doctrine-migrations-bundle
   composer require --dev phpunit/phpunit doctrine/doctrine-fixtures-bundle phpstan/phpstan phpstan/phpstan-symfony friendsofphp/php-cs-fixer symfony/browser-kit symfony/css-selector
   ```
2) Drop these files into the repo root (`saas/`), then set local ports to avoid WSL conflicts:
   ```bash
   echo -e "\nAPP_HTTP_PORT=8088\nPOSTGRES_PORT=5442" >> .env.local
   ```
3) Start and migrate the main DB:
   ```bash
   make up
   bin/console doctrine:database:create --if-not-exists
   bin/console doctrine:migrations:migrate --no-interaction
   ```
4) Run tests:
   ```bash
   composer test
   ```
