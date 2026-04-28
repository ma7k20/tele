FROM php:8.2-apache

# 1. تثبيت الإضافات الضرورية لـ Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 2. تفعيل مود الـ Rewrite في Apache (ضروري جداً للروابط)
RUN a2enmod rewrite

# 3. توجيه Apache لمجلد public بدلاً من المجلد الرئيسي
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 4. نسخ ملفات المشروع للسيرفر
COPY . /var/www/html

# 5. تثبيت Composer والاعتمادات
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 6. إعطاء الصلاحيات الصحيحة للمجلدات
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80