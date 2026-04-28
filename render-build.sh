composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache