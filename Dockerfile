FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy all files
COPY . /var/www/html/

# Set ownership
RUN chown -R www-data:www-data /var/www/html

# Set permissions
RUN chmod -R 755 /var/www/html

# Create uploads directory and set permissions
RUN mkdir -p /var/www/html/uploads /var/www/html/uploads/papers /var/www/html/uploads/avatars /var/www/html/uploads/temp /var/www/html/database-backup && \
    chmod -R 777 /var/www/html/uploads /var/www/html/database-backup

EXPOSE 80