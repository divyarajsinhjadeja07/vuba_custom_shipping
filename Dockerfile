FROM php:8.1-apache

# Enable mod_rewrite (Shopify apps may need it)
RUN a2enmod rewrite

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html/vuba_custom_shipping \
    && chmod -R 755 /var/www/html/vuba_custom_shipping

# Copy project files into Apache root
COPY . /var/www/html/vuba_custom_shipping/

# Expose port
EXPOSE 80
