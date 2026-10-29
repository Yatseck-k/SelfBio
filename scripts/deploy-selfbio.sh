#!/bin/bash
# Распаковывает /root/selfbio.tar.gz в /opt/selfbio и поднимает проект: nginx + php-fpm + бот, PostgreSQL, Redis.
# Повторный запуск = обновление кода; .env, БД и storage сохраняются. Перед миграциями делается дамп БД.
#
# Работающий сайт не трогается, пока всё долгое (образ, composer, сборка фронтенда) не готово
# в отдельном каталоге: иначе он минутами отдаёт новый код со старыми vendor и кэшем (500).
# Затем код переносится rsync'ом за секунды, на это время сайт в режиме обслуживания (503).
set -euo pipefail

ARCHIVE="${1:-/root/selfbio.tar.gz}"
DIR=/opt/selfbio
BACKUPS=/root/backups
PORT="${APP_PORT:-8081}"
HOST_IP="${APP_HOST:-$(curl -s -m 5 https://api.ipify.org || hostname -I | awk '{print $1}')}"
IMAGE=selfbio/php:prod
NODE_IMAGE=node:24-alpine
PROJECT="${COMPOSE_PROJECT_NAME:-selfbio}"

[ -f "$ARCHIVE" ] || { echo "Нет архива $ARCHIVE"; exit 1; }
command -v docker >/dev/null || { echo "Docker не установлен"; exit 1; }
command -v rsync >/dev/null || { echo "rsync не установлен"; exit 1; }

mkdir -p "$DIR" "$BACKUPS"
STAGE=$(mktemp -d /opt/selfbio-stage.XXXXXX)
trap 'rm -rf "$STAGE"' EXIT
# mktemp создаёт каталог с правами 700, а rsync -a переносит их на $DIR: системный nginx перестал бы видеть public/.
chmod 755 "$STAGE"

echo "==> Распаковка во временный каталог"
tar xzf "$ARCHIVE" --strip-components=1 -C "$STAGE"
mkdir -p "$STAGE"/storage/app/private "$STAGE"/storage/framework/{cache,sessions,views,testing} "$STAGE"/storage/logs "$STAGE"/bootstrap/cache

if [ ! -f "$DIR/.env" ]; then
    echo "==> Создание .env"
    rnd() { head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24; }
    cp "$STAGE/.env.example" "$DIR/.env"
    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=production|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=http://${HOST_IP}:${PORT}|" \
        -e "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(rnd)|" \
        -e "s|^MAIL_MAILER=.*|MAIL_MAILER=resend|" \
        "$DIR/.env"
    chown 1000:1000 "$DIR/.env"
    chmod 640 "$DIR/.env"
fi

# Настройки Docker для прода. В .env, оставшемся от Sail, их нет: дописываются один раз, остальное не трогается.
set_env() {
    if grep -q "^$1=" "$DIR/.env"; then
        sed -i "s|^$1=.*|$1=$2|" "$DIR/.env"
    else
        printf '%s=%s\n' "$1" "$2" >> "$DIR/.env"
    fi
}
set_env COMPOSE_FILE docker-compose.yml
set_env PHP_TARGET prod
set_env PUID 1000
set_env PGID 1000
set_env APP_BIND 127.0.0.1
set_env APP_PORT "$PORT"

# Vite вшивает VITE_* в сборку, а package:discover загружает приложение: обоим нужен настоящий .env.
cp -p "$DIR/.env" "$STAGE/.env"

# Первый запуск = ключ приложения ещё не сгенерирован
FIRST_RUN=0
grep -q '^APP_KEY=.\+' "$DIR/.env" || FIRST_RUN=1

echo "==> Образы"
# Docker Hub ограничивает анонимные скачивания (429): при отказе образ берётся из зеркала Google и получает обычное имя.
fetch_image() {
    local image=$1 mirror
    case "$image" in */*) mirror="mirror.gcr.io/$image" ;; *) mirror="mirror.gcr.io/library/$image" ;; esac
    docker pull -q "$image" >/dev/null 2>&1 && return 0
    docker image inspect "$image" >/dev/null 2>&1 && { echo "  $image: Docker Hub недоступен, остаётся локальная копия"; return 0; }
    echo "  $image: Docker Hub недоступен, беру из $mirror"
    docker pull -q "$mirror" >/dev/null && docker tag "$mirror" "$image"
}
PHP_BASE=$(sed -n 's/^FROM \([^ ]*\) AS base$/\1/p' "$STAGE/docker/php-fpm/Dockerfile")
for image in "$PHP_BASE" "$NODE_IMAGE" $(sed -n "s/^ *image: '\{0,1\}\([^'\$]*\)'\{0,1\}$/\1/p" "$STAGE/docker-compose.yml" | grep -v '^selfbio/'); do
    fetch_image "$image"
done

echo "==> Сборка образа PHP"
# Версия PHP закреплена в Dockerfile (php:8.5.X-fpm): патч-релиз = правка тега там. Базовый образ уже обновлён выше.
docker build --target prod --build-arg PUID=1000 --build-arg PGID=1000 -t "$IMAGE" "$STAGE/docker/php-fpm"

echo "==> Зависимости и фронтенд во временном каталоге"
# Прежний vendor ускоряет composer.
[ -d "$DIR/vendor" ] && rsync -a --ignore-existing "$DIR/vendor/" "$STAGE/vendor/"
chown -R 1000:1000 "$STAGE"
# Сеть работающего стека (новая или оставшаяся от Sail): package:discover может обратиться к БД и Redis.
NET_OPT=()
for net in "${PROJECT}_backend" "${PROJECT}_sail"; do
    if docker network inspect "$net" >/dev/null 2>&1; then
        NET_OPT=(--network "$net")
        break
    fi
done
# Каталог монтируется по тому же пути, что и в контейнере приложения: пути в bootstrap/cache остаются верными.
docker run --rm -v "$STAGE:/var/www/html" "${NET_OPT[@]}" "$IMAGE" \
    composer install --no-dev --optimize-autoloader --no-interaction
# Ассеты админки (public/vendor/orchid) не хранятся в git: копируются из пакета под установленную версию.
docker run --rm -v "$STAGE:/var/www/html" "${NET_OPT[@]}" "$IMAGE" php artisan orchid:publish
docker run --rm -u 1000:1000 -e HOME=/tmp -v "$STAGE:/var/www/html" -w /var/www/html "$NODE_IMAGE" \
    sh -c 'npm ci && npm run build'

cd "$DIR"

# Служба приложения в работающем стеке: php (новая схема) или laravel.test (Sail, до первого перехода).
APP_SVC=""
for svc in php laravel.test; do
    if [ -n "$(docker ps -q --filter "label=com.docker.compose.project=${PROJECT}" --filter "label=com.docker.compose.service=${svc}")" ]; then
        APP_SVC=$svc
        break
    fi
done

if [ -n "$APP_SVC" ]; then
    echo "==> Дамп БД"
    docker compose exec -T pgsql sh -c 'pg_dump -U "$POSTGRES_USER" -Fc "$POSTGRES_DB"' > "$BACKUPS/selfbio-$(date +%Y%m%d%H%M%S)-predeploy.dump"

    echo "==> Режим обслуживания"
    # Если скрипт упадёт дальше, сайт останется в нём: после исправления — make up / php artisan up.
    # The maintenance page is copied before the rest of the release, so it is available even on this first update.
    mkdir -p "$DIR/resources/views/maintenance"
    cp "$STAGE/resources/views/maintenance/game-update.blade.php" "$DIR/resources/views/maintenance/game-update.blade.php"
    docker compose exec -T -u 1000 "$APP_SVC" php artisan down --render=maintenance.game-update --retry=15 || true
fi

# Бот входа читает код с диска: пока меняется код, он не должен перезапускаться с половиной файлов.
docker compose stop telegram-bot 2>/dev/null || true

if [ "$APP_SVC" = "laravel.test" ]; then
    echo "==> Переход с Sail: старый контейнер приложения освобождает порт ${PORT}"
    docker compose rm -sf laravel.test
fi

echo "==> Перенос кода"
# .env и storage живут только на сервере; node_modules для работы не нужны (фронтенд уже собран).
# --delete убирает удалённые из проекта файлы, устаревшие bootstrap/cache/*.php и прежний override от Sail.
rsync -a --delete \
    --exclude=/.env --exclude=/storage/ --exclude=/node_modules/ --exclude=/public/storage \
    "$STAGE/" "$DIR/"
mkdir -p storage/app/private storage/app/public storage/framework/{cache,sessions,views,testing} storage/logs
chown -R 1000:1000 storage

echo "==> Запуск контейнеров"
# Пересоздаёт php и nginx на новом образе и конфигах; сервисы, которых больше нет (Sail, RabbitMQ, Soketi), удаляются.
# Бот остаётся выключенным до конца миграций и optimize.
docker compose up -d --remove-orphans --scale telegram-bot=0

APP="docker compose exec -T php"

echo "==> Ожидание PHP"
for i in $(seq 1 60); do
    [ "$(docker inspect -f '{{.State.Health.Status}}' "$(docker compose ps -q php)" 2>/dev/null)" = healthy ] && break
    [ "$i" = 60 ] && { echo "php не стал healthy"; docker compose logs --tail 50 php; exit 1; }
    sleep 2
done

if [ "$FIRST_RUN" = 1 ]; then
    $APP php artisan key:generate --force
fi

echo "==> Миграции"
$APP php artisan migrate --force
if [ "$FIRST_RUN" = 1 ]; then
    $APP php artisan db:seed --force
    $APP php artisan db:seed --class='Whodoku\Database\Seeders\DatabaseSeeder' --force
    $APP php artisan puzzles:release starter
fi

$APP php artisan storage:link 2>/dev/null || true
$APP php artisan optimize:clear
$APP php artisan optimize
# opcache на проде не проверяет файлы: новый код и кэши php-fpm увидит только после перезапуска.
docker compose restart php
for i in $(seq 1 30); do
    [ "$(docker inspect -f '{{.State.Health.Status}}' "$(docker compose ps -q php)" 2>/dev/null)" = healthy ] && break
    sleep 2
done
$APP php artisan up

# Бот входа: создаётся на новом образе, когда код, схема и кэши уже готовы.
docker compose up -d telegram-bot

# Сюжет «Путь детектива»: создаются только недостающие главы, повторный запуск безопасен.
# Идёт после открытия сайта: генерация долгая, а обслуживать запросы она не мешает.
nice -n 10 $APP php artisan puzzles:story

echo "==> Готово: http://${HOST_IP}:${PORT}"
docker compose ps
