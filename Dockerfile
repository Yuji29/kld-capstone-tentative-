FROM php:8.2-apache

# Install PostgreSQL driver and other extensions
RUN apt-get update && apt-get install -y libpq-dev && \
    docker-php-ext-install pdo_pgsql pgsql pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy all files
COPY . /var/www/html/

# Set ownership and permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Create uploads directory
RUN mkdir -p /var/www/html/uploads /var/www/html/database-backup && \
    chmod 777 /var/www/html/uploads /var/www/html/database-backup

EXPOSE 80