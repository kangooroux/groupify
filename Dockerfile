FROM php:8.3-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions @composer zip intl pdo_pgsql opcache

RUN apk add --no-cache nginx supervisor gettext

RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/custom.ini \
    && printf "opcache.enable=1\nopcache.memory_consumption=128\nopcache.max_accelerated_files=10000\n" \
       >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod +x docker/entrypoint.sh \
    && cp docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf \
    && cp docker/nginx/prod.conf.template /etc/nginx/http.d/default.conf.template \
    && cp docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8080

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
