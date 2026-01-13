SHELL := /bin/bash

.PHONY: help bootstrap up down restart rebuild logs artisan test migrate seed tinker fresh queue-restart ws

help:
	@echo "Common targets:"
	@echo "  make bootstrap   # scaffold Laravel + Jetstream + websockets and configure .env"
	@echo "  make up          # start all containers"
	@echo "  make down        # stop containers"
	@echo "  make migrate     # run DB migrations"
	@echo "  make test        # run tests"
	@echo "  make logs        # tail logs"
	@echo "  make tinker      # artisan tinker"
	@echo "  make fresh       # migrate:fresh --seed"
	@echo "  make queue-restart  # restart queue workers"

bootstrap:
	bash scripts/bootstrap.sh

up:
	docker compose up -d

down:
	docker compose down

restart: down up

rebuild:
	docker compose build --no-cache

logs:
	docker compose logs -f --tail=200

artisan:
	docker compose exec app php artisan $(cmd)

test:
	docker compose exec app php artisan test -v

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

tinker:
	docker compose exec app php artisan tinker

fresh:
	docker compose exec app php artisan migrate:fresh --seed

queue-restart:
	docker compose exec app php artisan queue:restart