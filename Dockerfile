FROM php:8.1-apache

RUN a2enmod rewrite

# Copy files into subfolder
COPY . /var/www/html/vuba_custom_shipping

# Change Apache’s root directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/vuba_custom_shipping

# Update Apache config to use new DocumentRoot
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN chown -R www-data:www-data /var/www/html/vuba_custom_shipping \
    && chmod -R 755 /var/www/html/vuba_custom_shipping

EXPOSE 80
