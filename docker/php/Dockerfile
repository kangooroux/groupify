FROM php:8.3-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions @composer zip intl pdo_pgsql opcache

RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html
