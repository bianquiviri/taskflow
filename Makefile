.DEFAULT_GOAL := help

DOMAIN ?= taskflow.josebianco.local

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

up: ## Start the full stack (build + detached)
	docker compose up -d --build

start: ## Start existing containers
	docker compose start

stop: ## Stop containers (keep data)
	docker compose stop

down: ## Stop and remove containers (keep data volumes)
	docker compose down

destroy: ## Stop and remove containers + volumes (WARNING: deletes DB data)
	docker compose down -v

restart: ## Restart the stack
	docker compose restart

logs: ## Tail logs from all services
	docker compose logs -f --tail=100

shell: ## Open a shell inside the app container
	docker compose exec app bash

mysql: ## Open MySQL client
	docker compose exec mysql mysql -u taskflow -ptaskflow taskflow

certs: ## Generate mkcert wildcard certificate for HTTPS
	mkdir -p docker/traefik
	mkcert -install
	mkcert -cert-file docker/traefik/cert.pem -key-file docker/traefik/key.pem "$(DOMAIN)"

test: ## Run backend test suite (Pest)
	docker compose exec app php artisan test --ci

test-fe: ## Run frontend unit tests (Vitest)
	docker compose exec node npm run test

e2e: ## Run Playwright end-to-end tests
	npx playwright test

lint: ## Run Pint + ESLint
	docker compose exec app ./vendor/bin/pint --test
	docker compose exec node npm run lint

fix: ## Apply Pint fixes
	docker compose exec app ./vendor/bin/pint

migrate: ## Run database migrations
	docker compose exec app php artisan migrate

seed: ## Run database seeders
	docker compose exec app php artisan db:seed

routes: ## List all application routes
	docker compose exec app php artisan route:list

tinker: ## Laravel tinker REPL
	docker compose exec app php artisan tinker