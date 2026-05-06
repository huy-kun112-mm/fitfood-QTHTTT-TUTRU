FROM php:8.2-apache

# Copy source code vào container
COPY src/ /var/www/html/

# Mở port 80
EXPOSE 80