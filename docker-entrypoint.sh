#!/bin/bash
set -e

# Herbama Fashions — Docker Container Entrypoint
# Dynamically binds Apache to $PORT for Render / Heroku / Cloud Run

PORT="${PORT:-80}"

echo "[INFO] Configuring Apache to listen on port: ${PORT}"

# Replace port in Apache ports.conf and default site vhost
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/*.conf

# Ensure runtime directories exist with appropriate permissions
mkdir -p /var/www/html/logs /var/www/html/public_html/uploads
chown -R www-data:www-data /var/www/html/logs /var/www/html/public_html/uploads
chmod -R 775 /var/www/html/logs /var/www/html/public_html/uploads

# Execute Apache in foreground
exec apache2-foreground
