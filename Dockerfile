# syntax=docker/dockerfile:1
FROM php:8.1.32-apache
# TODO: look into updating to more recent PHP
WORKDIR /var/www/html/

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY ./src .

USER www-data

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080"]