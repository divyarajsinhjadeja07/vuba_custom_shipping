FROM php:8.1-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project files into Apache's web root
COPY . /var/www/html/vuba_custom_shipping/

# Set correct permissions AFTER copying
RUN chown -R www-data:www-data /var/www/html/vuba_custom_shipping \
    && chmod -R 755 /var/www/html/vuba_custom_shipping

# Tell Apache to use this folder as root
ENV APACHE_DOCUMENT_ROOT /var/www/html/vuba_custom_shipping

# Update Apache config to use new DocumentRoot
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
