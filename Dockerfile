# Production image for Render (or any Docker host).
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP packages first so this layer is cached between deploys.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
                storage/logs storage/app/private/uploads bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Serve from /public.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf "upload_max_filesize=10M\npost_max_size=12M\nmemory_limit=256M\nmax_execution_time=120\nexpose_php=Off\n" \
       > /usr/local/etc/php/conf.d/snipo.ini

COPY docker/start.sh /usr/local/bin/start.sh
CMD ["bash", "/usr/local/bin/start.sh"]
