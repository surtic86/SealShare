# ============================================
# Stage 1: Build frontend assets
# ============================================
FROM node:24-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --prefer-offline

COPY vite.config.js ./
COPY resources/ ./resources/

RUN npm run build

# ============================================
# Stage 2: Install PHP dependencies
# ============================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-autoloader \
    --no-scripts \
    --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev

# ============================================
# Stage 3: Production image (FrankenPHP/Octane)
# ============================================
FROM dunglas/frankenphp:php8.5-alpine AS production

LABEL maintainer="surtic86"
LABEL org.opencontainers.image.source="https://github.com/surtic86/SealShare"
LABEL org.opencontainers.image.description="Self-hosted encrypted file sharing"

# Install required PHP extensions
RUN install-php-extensions \
    intl \
    pcntl

# Laravel environment defaults
ENV APP_NAME="SealShare" \
    APP_ENV="production" \
    APP_DEBUG="false" \
    APP_URL="http://localhost" \
    LOG_CHANNEL="stderr" \
    LOG_LEVEL="warning" \
    DB_CONNECTION="sqlite" \
    SESSION_DRIVER="database" \
    QUEUE_CONNECTION="database" \
    CACHE_STORE="database" \
    FILESYSTEM_DISK="local" \
    BROADCAST_CONNECTION="log" \
    BCRYPT_ROUNDS="12" \
    OCTANE_SERVER="frankenphp"

WORKDIR /app

# Copy Caddyfile
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Copy PHP ini for upload limits
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini

# Copy application code
COPY . .

# Copy vendor dependencies from composer stage
COPY --from=vendor /app/vendor ./vendor

# Copy built frontend assets from node stage
COPY --from=assets /app/public/build ./public/build

# Remove dev/build files not needed in production
RUN rm -rf node_modules tests .github docker/dev.Dockerfile docker/dev-entrypoint.sh .env .env.example \
    && mkdir -p storage/app/shares storage/app/public storage/framework/cache \
    storage/framework/sessions storage/framework/testing storage/framework/views \
    storage/logs database \
    && chmod -R 777 storage database bootstrap/cache

# Create SQLite database file if it doesn't exist
RUN touch database/database.sqlite \
    && chmod 666 database/database.sqlite

# Make entrypoint executable
RUN chmod +x docker/entrypoint.sh

EXPOSE 80 443 443/udp

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl --silent --fail http://localhost/up || exit 1

ENTRYPOINT ["docker/entrypoint.sh"]
