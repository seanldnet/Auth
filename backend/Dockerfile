FROM php:8.2-apache-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client unzip git libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install curl mbstring pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY backend/ ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY deploy/backend-entrypoint.sh /usr/local/bin/auth-entrypoint
COPY deploy/scheduler-entrypoint.sh /usr/local/bin/scheduler-entrypoint
RUN chmod +x /usr/local/bin/auth-entrypoint /usr/local/bin/scheduler-entrypoint \
    && printf '%s\n' '<Directory /var/www/html/public>' '    AllowOverride All' '    Require all granted' '    FallbackResource /index.php' '</Directory>' > /etc/apache2/conf-available/auth-public.conf \
    && a2enconf auth-public \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && mkdir -p runtime/cache runtime/log \
    && chown -R www-data:www-data runtime
EXPOSE 80
ENTRYPOINT ["auth-entrypoint"]
CMD ["apache2-foreground"]
