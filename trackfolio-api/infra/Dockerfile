FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install system dependencies (linux-headers needed to compile sockets)
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    mysql-client \
    supervisor \
    linux-headers

# Install PHP extensions (sockets required by php-amqplib / RabbitMQ)
RUN docker-php-ext-install pdo_mysql pdo mbstring exif pcntl bcmath gd opcache sockets

# Install Redis extension
RUN apk add --no-cache pcre-dev $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del pcre-dev $PHPIZE_DEPS

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Supervisor config (used by queue_worker service)
COPY infra/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY infra/supervisor/conf.d/ /etc/supervisor/conf.d/
RUN mkdir -p /var/log/supervisor

# Copy entrypoint script
COPY infra/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 9000 and start php-fpm server
EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
