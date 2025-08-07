# Makefile

.PHONY: test pint rector build up down logs tinker shell migrate seed

# Запуск vendor/bin/sail c контейнерами
up:
	vendor/bin/sail up -d

# Остановить контейнеры
down:
	vendor/bin/sail down

# Просмотр логов
logs:
	vendor/bin/sail logs -f

# Artisan tinker
tinker:
	vendor/bin/sail artisan tinker

# Bash в контейнере приложения
shell:
	vendor/bin/sail shell

# Запуск тестов Laravel
test:
	vendor/bin/sail artisan test

# Laravel Pint (code style)
pint:
	vendor/bin/sail pint

# Rector (рефакторинг) — dry-run (только проверка)
rector:
	vendor/bin/sail php vendor/bin/rector process --ansi --dry-run

# Rector (автоматический FIX)
rector-fix:
	vendor/bin/sail php vendor/bin/rector process --ansi

# Сборка front-end (Vite)
build:
	vendor/bin/sail npm run build

# Миграции и сиды
migrate:
	vendor/bin/sail artisan migrate

seed:
	vendor/bin/sail artisan db:seed

# Очистить кеши
cache-clear:
	vendor/bin/sail artisan config:clear
	vendor/bin/sail artisan route:clear
	vendor/bin/sail artisan view:clear

# Очистить и пересобрать кеши
cache-rebuild:
	vendor/bin/sail artisan config:cache
	vendor/bin/sail artisan route:cache
	vendor/bin/sail artisan view:cache
