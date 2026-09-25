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

queue: ## Run the Redis queue worker (attached; processes queued email/notifications)
	docker compose exec app php artisan queue:work redis --tries=3

shell: ## Open a shell inside the app container
	docker compose exec app bash

mysql: ## Open MySQL client
	docker compose exec mysql mysql -u taskflow -ptaskflow taskflow

certs: ## Generate mkcert wildcard certificate for HTTPS (inside Docker)
	docker compose run --rm certs
	@echo ""
	@echo "If your browser does not trust the CA yet, run: make trust-ca"

trust-ca: ## Install the mkcert root CA into the host keychain (one-time, needs admin)
	@test -f docker/traefik/ca/rootCA.pem || (echo "Root CA missing — run 'make certs' first." && exit 1)
	sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain docker/traefik/ca/rootCA.pem
	@echo "Root CA installed. Restart your browser."

hosts: ## Add the app domain to /etc/hosts (one-time, needs admin)
	@if rg -q "$(DOMAIN)" /etc/hosts; then echo "hosts: $(DOMAIN) already present — nothing to do."; \
	else sudo sh -c 'echo "127.0.0.1       $(DOMAIN)" >> /etc/hosts' && echo "hosts: added 127.0.0.1 $(DOMAIN)"; fi

doctor: ## Check prerequisites (docker, .env, hosts, certs)
	@echo "==> Docker daemon"; docker info >/dev/null 2>&1 && echo "  OK  docker is running" || echo "  FAIL docker is not running"
	@echo "==> .env"; test -f .env && echo "  OK  .env present" || echo "  WARN .env missing — 'make up' will create it from .env.example"
	@echo "==> /etc/hosts"; rg -q "$(DOMAIN)" /etc/hosts && echo "  OK  $(DOMAIN) resolves" || echo "  WARN run 'make hosts'"
	@echo "==> TLS certs"; test -f docker/traefik/cert.pem && echo "  OK  cert.pem present" || echo "  WARN run 'make certs'"
	@echo "==> Root CA"; test -f docker/traefik/ca/rootCA.pem && echo "  OK  root CA present" || echo "  WARN run 'make certs'"
	@echo "==> App healthy"; curl -fsS https://$(DOMAIN)/up >/dev/null 2>&1 && echo "  OK  https://$(DOMAIN)/up responds" || echo "  WARN stack not running — run 'make up'"

deps: ## Install backend + frontend dependencies inside the containers
	docker compose exec app composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
	docker compose exec node npm install --silent

test: ## Run backend test suite (Pest)
	docker compose exec app php artisan test --ci --no-coverage

test-fe: ## Run frontend unit tests (Vitest)
	docker compose exec node npm run test

e2e: ## Run Playwright end-to-end tests (inside Docker)
	docker compose --profile e2e run --rm e2e

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