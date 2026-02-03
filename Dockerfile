FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libicu-dev libzip-dev libgmp-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip gmp opcache bcmath \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN mkdir -p var/cache var/log
RUN chown -R www-data:www-data var && chmod -R 775 var && chown -R www-data:www-data /var/www/html

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && echo '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>' >> /etc/apache2/apache2.conf

# IMPORTANT: DO NOT cache warmup at build time - let it happen at runtime
RUN echo '#!/bin/bash\nset -e\nrm -rf var/cache/*\nphp bin/console cache:clear --env=prod\nphp bin/console cache:warmup --env=prod\necho "DEBUG ROUTES:" >&2\nphp bin/console debug:router --env=prod 2>&1 >&2\nphp bin/console doctrine:migrations:migrate --no-interaction 2>&1 || true\nexec apache2-foreground' > /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
