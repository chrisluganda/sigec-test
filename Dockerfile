FROM php:8.4-cli-bookworm AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev libfreetype6-dev libicu-dev libjpeg62-turbo-dev libonig-dev libpng-dev libpq-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl exif gd intl mbstring opcache pdo_pgsql xml zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /var/www/html
ENV VITE_APP_NAME=SIGEC
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && npm ci \
    && npm run build

FROM php:8.4-apache-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev libfreetype6-dev libicu-dev libjpeg62-turbo-dev libonig-dev libpng-dev libpq-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl exif gd intl mbstring opcache pdo_pgsql xml zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
ENV APP_NAME=SIGEC
COPY --from=build /var/www/html /var/www/html
COPY start-render.sh /usr/local/bin/start-render
RUN sed -i 's/\r$//' /usr/local/bin/start-render \
    && chmod 755 /usr/local/bin/start-render \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000
CMD ["sh", "/usr/local/bin/start-render"]


