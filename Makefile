.PHONY: up down restart build bash composer artisan migrate fresh horizon logs test

DOCKER_COMPOSE = docker compose
APP_SERVICE = app

up:
	@test -f .env || cp .env.example .env
	$(DOCKER_COMPOSE) up -d

down:
	$(DOCKER_COMPOSE) down

restart:
	$(DOCKER_COMPOSE) restart

build:
	$(DOCKER_COMPOSE) build --no-cache

bash:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) bash

composer:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) composer $(filter-out $@,$(MAKECMDGOALS))

artisan:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan migrate

fresh:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan migrate:fresh

horizon:
	$(DOCKER_COMPOSE) logs -f horizon

test:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan test

logs:
	$(DOCKER_COMPOSE) logs -f

%:
	@:
