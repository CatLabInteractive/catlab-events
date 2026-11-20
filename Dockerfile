ARG NODE_VERSION=18
ARG PHP_EXTENSIONS="mysqli pdo_mysql bcmath zip intl gd"

FROM thecodingmachine/php:8.5-v5-slim-apache
ENV APACHE_DOCUMENT_ROOT=public/
# Cache-Control headers for static files (public/.htaccess) need mod_headers.
ENV APACHE_EXTENSION_HEADERS=1
# Uploads (photos, CMS assets) up to 64 MB. The image default is 2 MB per file.
# The dokku nginx proxy has its own limit: dokku nginx:set catlab-events client-max-body-size 64m
ENV PHP_INI_UPLOAD_MAX_FILESIZE=64M \
    PHP_INI_POST_MAX_SIZE=64M

# Cache config, routes and compiled views when the container starts (the
# environment is only known then). Saves reading every config file and
# registering every route on each request.
ENV STARTUP_COMMAND_1="php artisan config:cache" \
    STARTUP_COMMAND_2="php artisan route:cache" \
    STARTUP_COMMAND_3="php artisan view:cache"

# Configure locales
USER root
RUN apt-get update && apt-get install -y language-pack-nl && apt-get clean;
USER docker

# Copy the source code in /www into the container at /var/www/html
COPY --chown=docker:docker . /var/www/html

COPY docker/apache/mpm-prefork.conf /etc/apache2/mods-available/mpm_prefork.conf

WORKDIR /var/www/html

RUN composer install

ENV NODE_OPTIONS="--openssl-legacy-provider"
RUN npm install
RUN npm run prod
