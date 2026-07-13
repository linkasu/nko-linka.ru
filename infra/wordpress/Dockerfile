FROM wordpress:6.8.3-php8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html \
    PORT=8080 \
    WORDPRESS_TMP_DIR=/tmp/wordpress

RUN sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

COPY infra/wordpress/php.ini /usr/local/etc/php/conf.d/linka-nko.ini
COPY infra/wordpress/apache-server-name.conf /etc/apache2/conf-available/linka-nko-server-name.conf
COPY infra/wordpress/entrypoint.sh /usr/local/bin/linka-nko-entrypoint.sh
COPY infra/wordpress/healthz.php /usr/src/wordpress/healthz.php
COPY infra/wordpress/yandex_49728deba6030ada.html /usr/src/wordpress/yandex_49728deba6030ada.html
COPY wp-content/ /usr/src/wordpress/wp-content/

RUN a2enmod rewrite \
    && a2enconf linka-nko-server-name \
    && chmod +x /usr/local/bin/linka-nko-entrypoint.sh \
    && chown -R www-data:www-data /usr/src/wordpress/wp-content /usr/src/wordpress/healthz.php /usr/src/wordpress/yandex_49728deba6030ada.html

ENTRYPOINT ["linka-nko-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 8080
