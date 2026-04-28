FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql
COPY . /var/www/html
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN a2enmod rewrite
EXPOSE 80