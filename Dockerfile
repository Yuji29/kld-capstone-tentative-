FROM ubuntu:22.04

RUN apt-get update && apt-get install -y \
    apache2 \
    php8.2 \
    php8.2-pgsql \
    php8.2-pdo \
    php8.2-pdo-pgsql \
    libapache2-mod-php8.2 \
    && apt-get clean

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    mkdir -p /var/www/html/uploads /var/www/html/database-backup && \
    chmod 777 /var/www/html/uploads /var/www/html/database-backup

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]