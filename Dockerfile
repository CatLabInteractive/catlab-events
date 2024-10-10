ARG PHP_EXTENSIONS="mysqli pdo_mysql bcmath zip intl gd"
ARG NODE_VERSION=16
ARG LOCALES="nl_BE.utf8"

FROM thecodingmachine/php:8.0-v4-slim-apache
ENV APACHE_DOCUMENT_ROOT=public/

# Configure locales
USER root
RUN for l in ${LOCALES}; \
        do sed -i -e "s/# \(${l} .*\)/\1/" /etc/locale.gen; \
    done
RUN dpkg-reconfigure -f noninteractive locales

USER docker

# Copy the source code in /www into the container at /var/www/html
COPY --chown=docker:docker . /var/www/html

WORKDIR /var/www/html

RUN composer install

RUN npm install
RUN npm run prod
