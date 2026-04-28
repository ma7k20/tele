FROM php:8.2-apache

# تثبيت الإضافات اللازمة لـ Laravel
RUN apt-get update && apt-get install -l libpng-dev libonig-dev libxml2-dev zip unzip git
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# تفعيل خاصية الـ Rewrite في Apache
RUN a2enmod rewrite

# تغيير الـ DocumentRoot ليؤشر على مجلد public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# نسخ ملفات المشروع
COPY . /var/www/html

# تثبيت Composer والاعتمادات
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# إعطاء الصلاحيات للمجلدات المطلوبة
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80