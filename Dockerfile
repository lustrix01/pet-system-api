FROM php:8.2.12-apache-bookworm

# Install required PHP extensions
RUN docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql \
    && a2enmod rewrite

WORKDIR /var/www/html

# Copy application code
COPY src/ /var/www/html/

# Set proper permissions for Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
