# ---- stage composer ----
FROM composer:2 AS composer_bin

# ---- app image ----
FROM dunglas/frankenphp:1.2-php8.3

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
COPY --link . /app

# Runtime Symfony pour FrankenPHP
ENV APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime

# Caddyfile externe
COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 8080
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
