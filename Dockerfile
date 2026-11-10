ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Install PHP cURL extension for PayMongo and external APIs
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev ca-certificates \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite
RUN a2enmod rewrite

# Allow .htaccess rules
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy project files
COPY . /var/www/html/

# Configure PHP session storage
RUN mkdir -p /var/www/html/runtime/session \
    && chown -R www-data:www-data /var/www/html/runtime \
    && chmod 755 /var/www/html/runtime \
    && chmod 770 /var/www/html/runtime/session \
    && printf 'session.save_handler=files\nsession.save_path="/var/www/html/runtime/session"\n' \
       > /usr/local/etc/php/conf.d/carease-session.ini

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -i "s|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g" /etc/apache2/sites-available/000-default.conf

# Serve Vue frontend
RUN cp -a /var/www/html/public/vue/. /var/www/html/public/