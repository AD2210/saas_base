SHELL := /bin/bash

up: ## Start stack
	docker compose up -d --build

stop: ## Stop stack
	docker compose stop

down: ## Stop & remove
	docker compose down -v

logs: ## Tail app logs
	docker compose logs -f app

sh: ## Shell into app
	docker compose exec app bash || docker compose exec app sh

test: ## Run tests
	docker compose exec -e XDEBUG_MODE=coverage app ./vendor/bin/phpunit

qa: ## Static analysis
	docker compose exec app vendor/bin/phpstan analyse --no-progress
	docker compose exec app vendor/bin/php-cs-fixer fix -v --dry-run
