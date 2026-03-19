SHELL := /bin/bash
COMPOSE_DEV := docker compose -f docker-compose.yml -f docker-compose.dev.yml

up:        ## Start stack
	$(COMPOSE_DEV) up -d --build

rebuild:   ## Rebuild app image
	$(COMPOSE_DEV) build --no-cache app && $(COMPOSE_DEV) up -d

stop:      ## Stop stack
	$(COMPOSE_DEV) stop

down:      ## Stop & remove
	$(COMPOSE_DEV) down -v

logs:      ## Tail app logs
	$(COMPOSE_DEV) logs -f app

worker-logs: ## Tail messenger worker logs
	$(COMPOSE_DEV) logs -f worker

sh:        ## Shell into app
	$(COMPOSE_DEV) exec app bash || $(COMPOSE_DEV) exec app sh

console:   ## Symfony console inside container
	$(COMPOSE_DEV) exec app php bin/console $(cmd)

cc:        ## Cache clear
	$(COMPOSE_DEV) exec app php bin/console cache:clear

composer-run: ## Run composer (one-shot): make composer-run cmd="install -n --prefer-dist"
	$(COMPOSE_DEV) run --rm app composer $(cmd)

install: ## composer install inside container (one-shot)
	$(COMPOSE_DEV) run --rm app composer install --no-interaction --prefer-dist

composer:  ## Run composer inside container: make composer cmd="require vendor/pkg"
	$(COMPOSE_DEV) exec app composer $(cmd)

require-frankenphp: ## Install FrankenPHP runtime for Symfony
	$(COMPOSE_DEV) exec app composer require runtime/frankenphp-symfony:^1 --no-interaction

warm: ## warm var and autoload
	$(COMPOSE_DEV) exec app sh -lc 'mkdir -p var/cache var/log && chmod -R 777 var' && $(COMPOSE_DEV) exec app composer dump-autoload -o -n && $(COMPOSE_DEV) exec app php bin/console cache:clear

ps: ## verifying started container
	$(COMPOSE_DEV) ps

monitoring-up: ## Start monitoring stack (Netdata + Uptime Kuma)
	$(COMPOSE_DEV) --profile monitoring up -d netdata uptime-kuma

monitoring-logs: ## Tail monitoring logs
	$(COMPOSE_DEV) --profile monitoring logs -f netdata uptime-kuma

monitoring-down: ## Stop monitoring stack
	$(COMPOSE_DEV) --profile monitoring stop netdata uptime-kuma

ops-lint: ## Bash syntax check for ops scripts
	find ops -type f -name '*.sh' -print0 | xargs -0 -n1 bash -n
