# ---- stage composer ----
FROM composer:2 AS composer_bin

# ---- stage vendor ----
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-scripts

# ---- app image ----
FROM dunglas/frankenphp:1-php8.5

# Extensions & outils
RUN install-php-extensions intl pdo_pgsql sodium opcache pcntl gd zip
RUN if command -v apt-get >/dev/null 2>&1; then \
      apt-get update && apt-get install -y --no-install-recommends unzip p7zip-full && rm -rf /var/lib/apt/lists/* ; \
    elif command -v apk >/dev/null 2>&1; then \
      apk add --no-cache unzip p7zip ; \
    fi

# Composer
COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY --from=vendor /app/vendor /app/vendor
COPY --link . /app

# Runtime Symfony pour FrankenPHP
ENV APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime

# Caddyfile externe
COPY docker/php/Caddyfile /etc/caddy/Caddyfile

RUN mkdir -p var/cache var/log \
    && composer dump-autoload --classmap-authoritative --no-dev --no-interaction --no-scripts \
    && APP_ENV=prod APP_DEBUG=0 APP_SECRET=build-secret APP_SECRETBOX_KEY=d13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a APP_ADMIN_PASSWORD=build-admin-password POSTGRES_USER=postgres POSTGRES_PASSWORD=postgres MAIN_DB_NAME=saas_base_main DATABASE_URL='postgresql://postgres:postgres@db:5432/saas_base_main?serverVersion=16&charset=utf8' MESSENGER_TRANSPORT_DSN='doctrine://default?auto_setup=0' MAILER_DSN='null://null' DEFAULT_CHILD_APP_KEY=vault CHILD_APP_VAULT_API_URL='https://vault.invalid/api' CHILD_APP_VAULT_LOGIN_URL='https://vault.invalid/login' CHILD_APP_VAULT_API_TOKEN=build-token CHILD_APP_OPS_API_URL='https://ops.invalid/api' CHILD_APP_OPS_LOGIN_URL='https://ops.invalid/login' CHILD_APP_OPS_API_TOKEN=build-token NETDATA_PUBLIC_URL='' UPTIME_KUMA_PUBLIC_URL='' SERVER_NAME=:80 php bin/console asset-map:compile \
    && APP_ENV=prod APP_DEBUG=0 APP_SECRET=build-secret APP_SECRETBOX_KEY=d13e60724269fb0aeaccf241a81b14387480f2e8b42f3669f10ac3bc2581396a APP_ADMIN_PASSWORD=build-admin-password POSTGRES_USER=postgres POSTGRES_PASSWORD=postgres MAIN_DB_NAME=saas_base_main DATABASE_URL='postgresql://postgres:postgres@db:5432/saas_base_main?serverVersion=16&charset=utf8' MESSENGER_TRANSPORT_DSN='doctrine://default?auto_setup=0' MAILER_DSN='null://null' DEFAULT_CHILD_APP_KEY=vault CHILD_APP_VAULT_API_URL='https://vault.invalid/api' CHILD_APP_VAULT_LOGIN_URL='https://vault.invalid/login' CHILD_APP_VAULT_API_TOKEN=build-token CHILD_APP_OPS_API_URL='https://ops.invalid/api' CHILD_APP_OPS_LOGIN_URL='https://ops.invalid/login' CHILD_APP_OPS_API_TOKEN=build-token NETDATA_PUBLIC_URL='' UPTIME_KUMA_PUBLIC_URL='' SERVER_NAME=:80 php bin/console cache:clear --env=prod --no-debug

EXPOSE 8080
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
