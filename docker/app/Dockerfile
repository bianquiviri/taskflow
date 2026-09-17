FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        icu-dev \
        libzip-dev \
        unzip \
        git \
        curl \
        bash \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql intl zip bcmath opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/app/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/app/supervisord.conf /etc/supervisord.conf
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-taskflow.ini

WORKDIR /var/www/html

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]