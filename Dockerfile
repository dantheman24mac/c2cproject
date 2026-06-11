FROM php:8.2-apache

# Install PDO MySQL extension needed by your app
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite (useful if your app uses routing/clean URLs)
RUN a2enmod rewrite

# Update permissions for the uploads directory
RUN chown -R www-data:www-data /var/www/html
