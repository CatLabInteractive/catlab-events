ARG PHP_EXTENSIONS="mysqli pdo_mysql bcmath zip intl gd"
ARG NODE_VERSION=16

FROM thecodingmachine/php:8.0-v4-slim-apache

ENV APACHE_DOCUMENT_ROOT=public/

USER root
RUN apt-get update && apt-get install locales-all && apt-get clean

USER docker

# Copy the source code in /www into the container at /var/www/html
COPY --chown=docker:docker . /var/www/html

WORKDIR /var/www/html

RUN composer install

RUN npm install
RUN npm run prod
