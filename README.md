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



## Лицензия
MIT
