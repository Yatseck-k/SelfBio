# SelfBio

## Стек

- **Laravel 13**, PHP 8.5
- **Orchid** (единая админка SelfBio и Whodoku)
- **PostgreSQL**, **Redis**, **RabbitMQ**, **Mailpit** (Docker/Sail)
- **Vite + Vue 3** (SPA, компоненты)
- **Livewire** v4, **Inertia**, сессионный вход Laravel и **Sanctum**, **Pusher/Soketi** (Websockets)

## Быстрый старт

### 1. Клонируй репозиторий

```bash
git clone https://github.com/Yatseck-k/SelfBio.git
cd SelfBio
```

### 2. Запусти dev-инфраструктуру через Sail

```bash
cp .env.example .env
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/app" -w /app composer:2 install --ignore-platform-req=php+ --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-sockets
npm ci

./vendor/bin/sail up -d 
```

При другом UID/GID пользователя укажите свои `WWWUSER` и `WWWGROUP` в `.env` до сборки образа.

### 3. Прогрей миграции и линкни сторидж

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan storage:link
./vendor/bin/sail artisan db:seed
```

### 4. Запусти vite для фронтенда

```bash
./vendor/bin/sail npm run dev
```

### 5. Создай пользователя для админки

```bash
./vendor/bin/sail artisan orchid:admin
```

Открой http://localhost:8080/admin — единую админку SelfBio и Whodoku на Orchid. Если переходишь с MoonShine, выполни `selfbio:import-admins --apply` после создания резервной копии БД.

## Полезные команды

```bash
# Запуск всех контейнеров (в фоне)
./vendor/bin/sail up -d

# Остановить контейнеры
./vendor/bin/sail down

# Перезапуск приложения
./vendor/bin/sail restart

# Проверить логи Laravel
./vendor/bin/sail artisan pail

# PHP Artisan команду выполнить
./vendor/bin/sail artisan route:list

# Открыть bash внутри контейнера
./vendor/bin/sail shell

# Тесты
./vendor/bin/sail artisan test

# Запустить vite/frontend-сервер
./vendor/bin/sail npm run dev

# Проверить состояние очередей (Horizon)
./vendor/bin/sail artisan horizon

# Обновить зависимости
./vendor/bin/sail composer update
./vendor/bin/sail npm update
```

## Игра и материалы ремонта

- Whodoku работает внутри SelfBio: PHP-код — в `app/Whodoku/`, интерфейс — в `resources/whodoku/`, маршруты — в `routes/whodoku/`, миграции — в `database/whodoku/`. Каталог `/puzzles`, API `/api/puzzles` и админка `/admin` используют одно приложение, PostgreSQL и Redis. Игровые таблицы находятся в схеме PostgreSQL `whodoku`, контент SelfBio — в `public`.

Администраторы обеих прежних панелей объединяются командой `php artisan selfbio:import-admins --apply` после резервного копирования БД. Без `--apply` команда показывает предварительный итог. При совпадении email сохраняется учётная запись и пароль Orchid; роль SelfBio добавляется ей. Старые таблицы `moonshine_users` и `moonshine_user_roles` сохраняются для отката. Вход в раздел ремонта `/login` остаётся отдельным от административного.
- Единственный `npm ci` и `npm run build` выполняются в корне SelfBio: общий Vite собирает сайт, игру и редактор карты в `public/build/`. Отдельного игрового приложения и контейнеров нет.
- На новой базе после миграций выполните `./vendor/bin/sail artisan db:seed --class='Whodoku\Database\Seeders\DatabaseSeeder'` и `./vendor/bin/sail artisan puzzles:release starter`, чтобы создать игровой каталог. Для разработки на `localhost` можно включить `TELEGRAM_DEV_LOGIN=true` в `.env`; в production тестовый вход отключён.
- Каталог ремонта и исходный экспорт находятся в закрытом `storage/app/private/repair-data/{repair,archive}`. SelfBio читает их через `REPAIR_ROOT` и `REPAIR_ARCHIVE_ROOT` (пути **внутри контейнера**). Интерфейс и действия с каталогом обслуживает Laravel после входа. Для импорта ZIP или папки можно использовать `python3 scripts/import-repair.py export|parsed /путь/к/данным` из корня; ZIP до 100 МБ можно загрузить через интерфейс. При переносе старой установки обновите пути `REPAIR_ROOT` и `REPAIR_ARCHIVE_ROOT` в `.env`.
- Создайте аккаунт SelfBio командой `./vendor/bin/sail artisan user:create user@example.com "Имя"`. Пароль вводится скрыто в терминале. Открытой регистрации нет. Вход — `/login`, каталог — `/repair`. Страницы, API, вложения и действия с каталогом требуют сессию SelfBio.
- После получения изменений фронтенда SelfBio выполните `npm ci && npm run build`, чтобы обновить файлы Vite в `public/build`.
- При развёртывании не публикуйте `storage/app/private/` как статические файлы. Игра использует собственную авторизацию через Telegram; её аккаунты отдельны от SelfBio. Старые исходники `game/` и `dataworker/` сохранены локально в игнорируемом `work/backups/` и не нужны для запуска.

## Лицензия
MIT
