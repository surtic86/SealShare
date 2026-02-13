FROM dunglas/frankenphp:php8.5-alpine

# Install required PHP extensions
RUN install-php-extensions \
    intl \
    pcntl

# Install Node.js for Vite / frontend asset building
RUN apk add --no-cache nodejs npm

WORKDIR /app

COPY docker/dev-entrypoint.sh /usr/local/bin/dev-entrypoint.sh
RUN chmod +x /usr/local/bin/dev-entrypoint.sh

ENTRYPOINT ["dev-entrypoint.sh"]
