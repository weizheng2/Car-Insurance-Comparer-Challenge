# Use official PHP image with Apache
FROM php:8.4-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libxml2-dev \
    && docker-php-ext-install zip xml \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache for Symfony
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Enable Apache AllowOverride for .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
    /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (better caching)
COPY composer.json composer.lock* ./

# Install dependencies (no scripts, no autoload yet)
RUN composer install \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

# Copy application source
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload \
    --optimize \
    --classmap-authoritative

# Fix permissions
RUN chown -R www-data:www-data var/

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
