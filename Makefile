SHELL := /bin/bash

up:        ## Start stack
	docker compose up -d --build

rebuild:   ## Rebuild app image
	docker compose build --no-cache app && docker compose up -d

stop:      ## Stop stack
	docker compose stop

down:      ## Stop & remove
	docker compose down -v

logs:      ## Tail app logs
	docker compose logs -f app

sh:        ## Shell into app
	docker compose exec app bash || docker compose exec app sh

console:   ## Symfony console inside container
	docker compose exec app php bin/console $(cmd)

cc:        ## Cache clear
	docker compose exec app php bin/console cache:clear

composer-run: ## Run composer (one-shot): make composer-run cmd="install -n --prefer-dist"
	docker compose run --rm app composer $(cmd)

install: ## composer install inside container (one-shot)
	docker compose run --rm app composer install --no-interaction --prefer-dist

composer:  ## Run composer inside container: make composer cmd="require vendor/pkg"
	docker compose exec app composer $(cmd)

require-frankenphp: ## Install FrankenPHP runtime for Symfony
	docker compose exec app composer require runtime/frankenphp-symfony:^1 --no-interaction

warm: ## warm var and autoload
	docker compose exec app sh -lc 'mkdir -p var/cache var/log && chmod -R 777 var' && docker compose exec app composer dump-autoload -o -n && docker compose exec app php bin/console cache:clear

ps: ## verifying started container
	docker compose ps
