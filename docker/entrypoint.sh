#!/bin/sh
set -eu

cd /var/www/html/mmqp_hub

# This distribution has its own database; it never connects to an external DB.
export DEMO_MODE=true
export DB_CONNECTION=sqlite
export DB_DATABASE=/var/lib/mmqp-demo/database.sqlite
export DB_URL=''
export APP_DEBUG=false
export MAIL_MAILER=array
export QUEUE_CONNECTION=sync
export CACHE_STORE=file
export SESSION_DRIVER=file
export FILESYSTEM_DISK=local
export BROADCAST_CONNECTION=log
export LOG_CHANNEL=single
export LARAVEL_STORAGE_PATH=/var/www/html/mmqp_hub/storage
export VIEW_COMPILED_PATH=/var/www/html/mmqp_hub/storage/framework/views
export APP_CONFIG_CACHE=/var/www/html/mmqp_hub/bootstrap/cache/config.php
export APP_SERVICES_CACHE=/var/www/html/mmqp_hub/bootstrap/cache/services.php
export APP_PACKAGES_CACHE=/var/www/html/mmqp_hub/bootstrap/cache/packages.php
export APP_ROUTES_CACHE=/var/www/html/mmqp_hub/bootstrap/cache/routes-v7.php
export APP_EVENTS_CACHE=/var/www/html/mmqp_hub/bootstrap/cache/events.php
export PYTHON_PATH=/opt/venv/bin/python
export CONSUMO_PDF_PYTHON=/opt/venv/bin/python

mkdir -p /var/lib/mmqp-demo storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache

if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate a different key for every installation and retain it on restarts.
if [ ! -s /var/lib/mmqp-demo/app-key ]; then
    php -r 'echo "base64:".base64_encode(random_bytes(32));' > /var/lib/mmqp-demo/app-key
fi
export APP_KEY="$(cat /var/lib/mmqp-demo/app-key)"

if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi

echo '==> Preparing the isolated MMQP demo'
php artisan config:clear
php artisan migrate --force --no-interaction
# DemoSeeder only populates a new empty installation. A persisted demo keeps edits.
php artisan db:seed --force --no-interaction
php artisan config:cache

chown -R www-data:www-data /var/lib/mmqp-demo storage bootstrap/cache
chmod 770 /var/lib/mmqp-demo
chmod 660 /var/lib/mmqp-demo/app-key "$DB_DATABASE"
chmod -R ug+rwX storage bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisord.conf
