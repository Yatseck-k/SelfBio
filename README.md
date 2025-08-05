# SelfBio

**Современная платформа для персонального сайта с блогом и страницей контактов. Админка — на Moonshine (Laravel), фронтенд — на Vue 3.**

## Стек

- **Laravel 12.19.3**, PHP 8.4.10
- **Moonshine** (админка)
- **PostgreSQL**, **Redis**, **RabbitMQ**, **Mailpit** (Docker/Sail)
- **Vite + Vue 3** (SPA, компоненты)
- Поддержка **Livewire** v3, **Inertia**, **Jetstream/Sanctum** (auth), **Pusher/Soketi** (Websockets)

## Быстрый старт

### 1. Клонируй репозиторий

```bash
git clone https://github.com/
cd selfbio
```

### 2. Запусти dev-инфраструктуру через Sail

```bash
cp .env.example .env
sail composer install
sail npm install

./vendor/bin/sail up -d 
```

### 3. Прогрей миграции и линкни сторидж

```bash
sail artisan migrate
sail artisan storage:link
sail artisan db:seed
```

### 4. Запусти vite для фронтенда

```bash
sail npm run dev
```

### 5. (При необходимости) Создай пользователя для админки

- Открой http://localhost/admin (или myapp.local/admin)
- Зарегистрируйся — первый пользователь автоматически становится __админом Moonshine__.

## Полезные команды

```bash
# Запуск всех контейнеров (в фоне)
sail up -d

# Остановить контейнеры
sail down

# Перезапуск приложения
sail restart

# Проверить логи Laravel
sail artisan pail

# PHP Artisan команду выполнить
sail artisan 

# Открыть bash внутри контейнера
sail shell

# Тесты
sail artisan test

# Запустить vite/frontend-сервер
sail npm run dev

# Проверить состояние очередей (Horizon)
sail artisan horizon

# Обновить зависимости
sail composer update
sail npm update
```

## Особенности

- Контактная страница и блог редактируются только из админки Moonshine
- REST API только на чтение
- Все сервисы запускаются docker-compose + Sail
- Встроенная поддержка очередей, почты, Websockets, rich-text

## Лицензия
MIT
