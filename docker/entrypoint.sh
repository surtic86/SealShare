#!/bin/sh
set -e

cd /app

# Auto-generate APP_KEY if not provided
if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] No APP_KEY set, generating one..."
    APP_KEY=$(php artisan key:generate --show)
    export APP_KEY
    echo "[entrypoint] Generated APP_KEY: $APP_KEY"
    echo "[entrypoint] WARNING: Set this APP_KEY in your docker-compose.yml to persist across restarts!"
fi

# Generate PHP ini from environment variables (with defaults)
echo "[entrypoint] Configuring PHP settings..."
cat > /usr/local/etc/php/conf.d/99-uploads.ini <<EOF
upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE:-4G}
post_max_size = ${PHP_POST_MAX_SIZE:-4G}
max_execution_time = ${PHP_MAX_EXECUTION_TIME:-300}
max_input_time = ${PHP_MAX_INPUT_TIME:-300}
memory_limit = ${PHP_MEMORY_LIMIT:-512M}
EOF

echo "[entrypoint] Running database migrations..."
php artisan migrate --force

echo "[entrypoint] Creating storage link..."
php artisan storage:link --force

echo "[entrypoint] Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Starting Octane (FrankenPHP)..."
exec php artisan octane:frankenphp --host=0.0.0.0 --port=80
