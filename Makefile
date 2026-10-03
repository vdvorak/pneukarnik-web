# Vývojové příkazy. Vše kromě Playwrightu běží v Dockeru (docker-compose.yml).

export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)
WP_PORT ?= 8080
export WP_PORT
export BASE_URL := http://localhost:$(WP_PORT)

COMPOSE := docker compose
TOOLS   := $(COMPOSE) run --rm tools

.PHONY: help up down reset logs wp test test-php test-e2e lint fix check

help: ## Vypíše příkazy
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  make %-10s %s\n", $$1, $$2}'

up: ## Spustí lokální WordPress se šablonou a pluginem
	$(COMPOSE) up -d --wait wordpress
	$(COMPOSE) run --rm cli sh /scripts/setup-wp.sh

down: ## Zastaví kontejnery (data zůstanou)
	$(COMPOSE) down

reset: ## Zastaví kontejnery a smaže data lokálního webu
	$(COMPOSE) down -v

logs: ## Sleduje logy WordPressu
	$(COMPOSE) logs -f wordpress

wp: ## Spustí WP-CLI, např. make wp ARGS="plugin list"
	$(COMPOSE) run --rm cli wp $(ARGS)

vendor: composer.json composer.lock
	$(TOOLS) composer install
	@touch vendor

node_modules: package.json package-lock.json
	npm ci
	npx playwright install chromium
	@touch node_modules

test-php: vendor ## PHPUnit nad REST API pluginu, např. make test-php ARGS="--filter ClockTest"
	$(TOOLS) vendor/bin/phpunit $(ARGS)

test-e2e: node_modules up ## Playwright kouřové testy nad lokálním webem
	npx playwright test $(ARGS)

test: test-php test-e2e ## Všechny testy

lint: vendor node_modules ## PHPCS, PHPStan a kontrola typů TypeScriptu
	$(TOOLS) composer lint
	npm run --silent typecheck

fix: vendor ## Automaticky opraví formátování (PHPCBF)
	$(TOOLS) composer fix

check: lint test ## Lint a všechny testy
