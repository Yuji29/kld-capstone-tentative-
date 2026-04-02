FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all files
COPY . /var/www/html/

# Create uploads directory if it doesn't exist
RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/uploads/papers \
    && mkdir -p /var/www/html/uploads/avatars \
    && mkdir -p /var/www/html/uploads/temp \
    && mkdir -p /var/www/html/database-backup

# Set proper permissions (only on directories that exist)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/database-backup

EXPOSE 80