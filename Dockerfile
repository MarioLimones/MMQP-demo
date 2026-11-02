FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY mmqp_hub/package*.json ./
RUN npm ci
COPY mmqp_hub/ ./
RUN npm run build

FROM php:8.2-fpm-bookworm

RUN php -r 'if (PHP_VERSION_ID < 80235 || PHP_VERSION_ID >= 80300) { fwrite(STDERR, "PHP 8.2.35 or newer in the 8.2 branch is required.\n"); exit(1); }'

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor python3 python3-pip python3-venv \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev \
    libonig-dev libsqlite3-dev unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_sqlite mbstring gd zip bcmath opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY mmqp_hub ./mmqp_hub
COPY algoritmo ./algoritmo
COPY --from=frontend-builder /app/public/build ./mmqp_hub/public/build

WORKDIR /var/www/html/mmqp_hub
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

WORKDIR /var/www/html/algoritmo
RUN python3 -m venv /opt/venv \
    && /opt/venv/bin/pip install --no-cache-dir -r requirements.txt \
    && /opt/venv/bin/pip install --no-cache-dir -r /var/www/html/mmqp_hub/requirements-consumos.txt

ENV DEMO_MODE=true APP_DEBUG=false DB_CONNECTION=sqlite \
    PYTHON_PATH=/opt/venv/bin/python CONSUMO_PDF_PYTHON=/opt/venv/bin/python

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/mmqp-uploads.ini
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
