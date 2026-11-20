# Herbama Fashions — Production / Demo Dockerfile for Render
# PHP 8.3 Apache base image with pdo_mysql & mod_rewrite

FROM php:8.3-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    ca-certificates \
    curl \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable required Apache modules
RUN a2enmod rewrite headers

# Configure Apache DocumentRoot to /public (pointing to /public_html via symlink)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure directory permissions and .htaccess override in Apache configuration
RUN { \
    echo '<Directory /var/www/html>'; \
    echo '    Options Indexes FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/public>'; \
    echo '    Options Indexes FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/public_html>'; \
    echo '    Options Indexes FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
} > /etc/apache2/conf-available/herbama.conf && a2enconf herbama

# Set working directory
WORKDIR /var/www/html

# Copy project files into container
COPY . /var/www/html/

# Create symlink /var/www/html/public -> /var/www/html/public_html
RUN ln -s /var/www/html/public_html /var/www/html/public

# Setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

# Set ownership and permissions for runtime directories
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/logs /var/www/html/public_html/uploads \
    && chown -R www-data:www-data /var/www/html/logs /var/www/html/public_html/uploads \
    && chmod -R 775 /var/www/html/logs /var/www/html/public_html/uploads

# Default port (Render overrides this dynamically via $PORT)
EXPOSE 80

# Run entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
