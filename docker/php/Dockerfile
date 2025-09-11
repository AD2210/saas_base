FROM dunglas/frankenphp:1.2-php8.2

# Useful packages & PHP extensions
RUN install-php-extensions             intl pdo_pgsql sodium opcache pcntl gd

WORKDIR /app
COPY --link . /app

# Dev runtime
ENV APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime
# Inline FrankenPHP config (pas de Caddyfile externe nécessaire)
ENV FRANKENPHP_CONFIG="prod=off\nworker /app/public/index.php"

EXPOSE 8080
CMD ["frankenphp", "run", "--http", ":8080"]


# Composer install (best-effort for bootstrap)
RUN composer install --no-interaction --prefer-dist --no-scripts || true

EXPOSE 8080
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
