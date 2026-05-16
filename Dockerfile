FROM php:8.3-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite (berguna jika ada routing)
RUN a2enmod rewrite

# Copy seluruh file project ke dalam folder web server container
COPY . /var/www/html/

# Set permission agar web server bisa membaca dan menulis file (penting untuk folder uploads)
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 755 /var/www/html/

# Expose port 80
EXPOSE 80
