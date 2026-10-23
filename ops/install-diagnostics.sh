#!/usr/bin/env bash
set -euo pipefail

if (( EUID != 0 )); then
    echo 'Run as root on the production server.' >&2
    exit 1
fi

source_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
site=/etc/nginx/sites-available/yatseck.ru
backup=/root/backups/nginx-yatseck.ru.before-diagnostics.$(date -u +%Y%m%d%H%M%S)

install -d -m 0700 /root/backups /var/log/yatseck-packets
cp -a -- "$site" "$backup"
install -m 0644 "$source_dir/nginx/yatseck-diagnostic-log.conf" /etc/nginx/conf.d/yatseck-diagnostic-log.conf

python3 - "$site" <<'PY'
from pathlib import Path
import sys

site = Path(sys.argv[1])
config = site.read_text()
diagnostic = 'access_log /var/log/nginx/yatseck.diagnostic.log yatseck_diagnostic;'

if diagnostic not in config:
    access = 'access_log /var/log/nginx/yatseck.access.log timing;'
    error = 'error_log  /var/log/nginx/yatseck.error.log warn;'
    if config.count(access) != 1 or config.count(error) != 1 or config.count('access_log off;') != 4:
        raise SystemExit('Unexpected nginx site config; no changes applied.')
    config = config.replace(access, access + '\n    ' + diagnostic, 1)
    config = config.replace(error, 'error_log  /var/log/nginx/yatseck.error.log info;', 1)
    config = config.replace('access_log off;', diagnostic)
    site.write_text(config)
elif 'error_log  /var/log/nginx/yatseck.error.log info;' not in config:
    raise SystemExit('Partial diagnostic config detected; inspect manually.')
PY

touch /var/log/nginx/yatseck.diagnostic.log
chown www-data:adm /var/log/nginx/yatseck.diagnostic.log
chmod 0640 /var/log/nginx/yatseck.diagnostic.log

if ! nginx -t; then
    cp -a -- "$backup" "$site"
    nginx -t
    exit 1
fi

install -m 0644 "$source_dir/systemd/yatseck-tls-capture.service" /etc/systemd/system/yatseck-tls-capture.service
systemctl daemon-reload
systemctl enable --now yatseck-tls-capture.service
systemctl restart yatseck-tls-capture.service
systemctl reload nginx

sleep 2
systemctl is-active --quiet nginx
systemctl is-active --quiet yatseck-tls-capture.service
echo "Diagnostics installed. Nginx backup: $backup"
