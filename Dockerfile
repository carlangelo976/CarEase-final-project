
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN mkdir -p /var/www/html/runtime/session \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 775 /var/www/html/runtime /var/www/html/runtime/session

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -i "s|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g" /etc/apache2/sites-available/000-default.conf

# Serve Vue frontend from the website root
RUN cp -a /var/www/html/public/vue/. /var/www/html/public/