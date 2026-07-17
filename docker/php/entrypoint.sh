#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Garante o arquivo .env
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    fi
fi

# Gera APP_KEY se ainda não existir
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force || true
fi

# Aguarda o banco de dados ficar disponível
if [ -n "${DB_HOST:-}" ]; then
    echo "Aguardando o banco de dados em ${DB_HOST}:${DB_PORT:-3306}..."
    ATTEMPTS=0
    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)(getenv('DB_PORT') ?: 3306)) ? 0 : 1);" 2>/dev/null; do
        ATTEMPTS=$((ATTEMPTS + 1))
        if [ "$ATTEMPTS" -ge 30 ]; then
            echo "Banco de dados indisponível após várias tentativas." >&2
            break
        fi
        sleep 2
    done
fi

# Executa migrações e caches apenas no serviço principal (php-fpm)
if [ "${1:-}" = "php-fpm" ]; then
    php artisan migrate --force || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link || true
fi

exec "$@"
