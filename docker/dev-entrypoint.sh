#!/bin/sh
set -e

cd /app

# Generate PHP ini from environment variables (with defaults)
echo "[dev] Configuring PHP settings..."
cat > /usr/local/etc/php/conf.d/99-uploads.ini <<EOF
upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE:-4G}
post_max_size = ${PHP_POST_MAX_SIZE:-4G}
max_execution_time = ${PHP_MAX_EXECUTION_TIME:-300}
max_input_time = ${PHP_MAX_INPUT_TIME:-300}
memory_limit = ${PHP_MEMORY_LIMIT:-512M}
EOF

echo "[dev] Installing Node dependencies..."
npm install 2>&1

echo "[dev] Building frontend assets..."
npm run build 2>&1

echo "[dev] Running database migrations..."
php artisan migrate --force

echo "[dev] Creating storage link..."
php artisan storage:link --force

echo "[dev] Starting Octane (FrankenPHP) with --watch..."
exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --watch --workers=1 --max-requests=1
