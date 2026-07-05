FROM wordpress:6.8.3-php8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html \
    PORT=8080

RUN sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

COPY infra/wordpress/php.ini /usr/local/etc/php/conf.d/linka-nko.ini
COPY infra/wordpress/healthz.php /usr/src/wordpress/healthz.php
COPY wp-content/ /usr/src/wordpress/wp-content/

RUN chown -R www-data:www-data /usr/src/wordpress/wp-content /usr/src/wordpress/healthz.php

EXPOSE 8080
