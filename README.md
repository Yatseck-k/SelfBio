# SelfBio

## Стек

- **Laravel 13**, PHP 8.5 (php-fpm)
- **Orchid** (единая админка SelfBio и Whodoku)
- **PostgreSQL**, **Redis** — в Docker, **Mailpit** — локально
- **Vite + Vue 3** (SPA, компоненты)
- **Livewire** v4, **Inertia**, сессионный вход Laravel и **Sanctum**

## Docker

| Сервис | Что делает |
|---|---|
| `nginx` | отдаёт `public/` (сборку Vite, `storage`) и передаёт запросы в php-fpm; порт `APP_BIND:APP_PORT` (по умолчанию `127.0.0.1:8080`) |
| `php` | php-fpm с приложением (`docker/php-fpm`, цели `local` с xdebug и `prod`) |
| `telegram-bot` | тот же образ, `php artisan telegram:poll` — вход через бота |
| `pgsql`, `redis` | БД, кэш, сессии, очередь |
| `mailpit` | локально: ловушка писем, http://localhost:8025 |
| `node` | по требованию: `make build`, `make dev`, `make npm CMD=…` |

Какие compose-файлы собираются, задаёт `COMPOSE_FILE` в `.env`: локально `docker-compose.yml:docker-compose.local.yml`, на проде только `docker-compose.yml` и `PHP_TARGET=prod`.
Локально бот не запускается сам (с тем же токеном он отбирал бы обновления у прода): `docker compose --profile bot up -d telegram-bot` с токеном тестового бота.

## Быстрый старт

### 1. Клонируй репозиторий

```bash
git clone https://github.com/Yatseck-k/SelfBio.git
cd SelfBio
```

### 2. Подними контейнеры

```bash
cp .env.example .env        # при другом UID/GID поправь PUID и PGID (id -u, id -g)
make up                     # первый раз соберёт образ PHP
make composer CMD=install
make npm CMD=ci
```

### 3. Прогрей миграции и линкни сторидж

```bash
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
docker compose exec php php artisan storage:link
docker compose exec php php artisan db:seed
```

### 4. Запусти vite для фронтенда

```bash
make dev
```

### 5. Создай пользователя для админки

```bash
docker compose exec php php artisan orchid:admin
```

Открой http://localhost:8080/admin — единую админку SelfBio и Whodoku на Orchid. Если переходишь с MoonShine, выполни `selfbio:import-admins --apply` после создания резервной копии БД.

### 6. Название сайта и время публикации

Название сайта (шапки, заголовки вкладок, админка, каталог ремонта) меняется в админке: **Настройки сайта** (`/admin/selfbio/settings`, права `platform.selfbio.settings.view/edit`). Оно сохраняется в базе и действует сразу на всех страницах, пересборка не нужна; кнопка «Вернуть название по умолчанию» возвращает `APP_NAME` из окружения. То же умеет команда: `php artisan site:rename "Моё название"` (`--show`, `--reset`).

Время публикации записей блога в админке вводится по часовому поясу сайта (`APP_DISPLAY_TIMEZONE`, по умолчанию `Europe/Moscow`), в базе хранится UTC.

## Полезные команды

```bash
make up / make down / make restart   # контейнеры
make ps / make logs                  # состояние и логи
make shell                           # bash в контейнере PHP
make test                            # PHPUnit
make pint / make pint-test           # стиль кода (только изменённые файлы / проверка)
make rector / make rector-fix
make build / make dev                # фронтенд: сборка / Vite
make composer CMD="update"
make npm CMD="update"
make build-images                    # пересобрать образ PHP (после правок docker/php-fpm)
docker compose exec php php artisan pail   # живой лог Laravel
```

## Лицензия
MIT
