# Base image with Apache + PHP
FROM php:8.2-apache

# Enable Apache rewrite module (for cleaner URLs if needed)
RUN a2enmod rewrite

# Make directory inside the container
RUN mkdir -p /var/www/html/vuba_custom_shipping

# Copy your app's code to that directory
COPY . /var/www/html/vuba_custom_shipping

# Set permissions (important for some servers)
RUN chown -R www-data:www-data /var/www/html/vuba_custom_shipping

# Set working directory so Apache serves it properly
WORKDIR /var/www/html/vuba_custom_shipping
