# Makefile

.PHONY: up down restart build-images ps logs php tinker shell test pint pint-test rector rector-fix \
	composer npm build dev migrate seed cache-clear cache-rebuild

PHP  = docker compose exec php
NODE = docker compose run --rm node

# Запуск контейнеров (набор compose-файлов задаёт COMPOSE_FILE в .env)
up:
	docker compose up -d

# Остановить контейнеры
down:
	docker compose down --remove-orphans

restart: down up

# Пересобрать образ PHP (после правок docker/php-fpm или чтобы подтянуть патч PHP)
build-images:
	docker compose build --pull php

ps:
	docker compose ps

# Просмотр логов
logs:
	docker compose logs -f

# Bash в контейнере PHP
shell php:
	$(PHP) bash

# Artisan tinker
tinker:
	$(PHP) php artisan tinker

# Запуск тестов Laravel
test:
	$(PHP) php artisan test

# Laravel Pint (code style): только изменённые файлы; pint-test — проверка без правок
pint:
	$(PHP) vendor/bin/pint --dirty

pint-test:
	$(PHP) vendor/bin/pint --test

# Rector (рефакторинг) — dry-run (только проверка)
rector:
	$(PHP) vendor/bin/rector process --ansi --dry-run

# Rector (автоматический FIX)
rector-fix:
	$(PHP) vendor/bin/rector process --ansi

# make composer CMD="require foo/bar"
composer:
	$(PHP) composer $(CMD)

# make npm CMD="install foo"
npm:
	$(NODE) npm $(CMD)

# Сборка front-end (Vite)
build:
	$(NODE) npm run build

# Vite в режиме разработки (http://localhost:5173)
dev:
	docker compose run --rm --service-ports node npm run dev

# Миграции и сиды
migrate:
	$(PHP) php artisan migrate --seed --force

seed:
	$(PHP) php artisan db:seed

# Очистить кеши
cache-clear:
	$(PHP) php artisan optimize:clear

# Очистить и пересобрать кеши
cache-rebuild:
	$(PHP) php artisan optimize
