FROM php:8.2-cli-bookworm
LABEL org.opencontainers.image.source="https://github.com/seanldnet/Auth"
RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates curl unzip libcurl4-openssl-dev libonig-dev default-mysql-client \
 && docker-php-ext-install -j"$(nproc)" curl mbstring pdo_mysql \
 && pecl install redis && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress
COPY . .
COPY docker/entrypoint.sh /usr/local/bin/auth-entrypoint
COPY docker/migrate.sh /usr/local/bin/auth-migrate
RUN chmod +x /usr/local/bin/auth-entrypoint /usr/local/bin/auth-migrate && mkdir -p runtime/cache runtime/log runtime/temp && chown -R www-data:www-data /app/runtime
ENV APP_DEBUG=false APP_TRACE=false
EXPOSE 8000
ENTRYPOINT ["auth-entrypoint"]
