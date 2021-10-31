ARG XDEBUG_VERSION=2.9.6

FROM php:7.4-fpm-alpine3.13 AS base

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add tini
RUN apk --update add --no-cache tini libzip-dev openldap-dev shadow

# Install required PHP extensions
RUN docker-php-ext-install bcmath pdo_mysql zip fileinfo ldap

# Copy over the app script
COPY docker/app/run-app.sh /usr/bin/run-app.sh

# Development #################################################################

FROM base AS development
ARG XDEBUG_VERSION

# Install the XDebug PHP extension
RUN apk --update add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug-${XDEBUG_VERSION} \
    && docker-php-ext-enable xdebug

# Copy over the fix-perms script
COPY --from=sudobmitch/base:scratch / /
RUN addgroup www-data tty && apk add --no-cache su-exec bash

# Tweak the PHP settings
WORKDIR /usr/local/etc/php
RUN mv php.ini-development php.ini \
  && sed -ri '/opcache.enable/s/;?opcache.enable=1/opcache.enable=0/' php.ini \
  && sed -i '/upload_max_filesize/s/=.*/= 20M/' php.ini \
  && sed -i '/post_max_size/s/=.*/= 20M/' php.ini \
  && sed -i '/expose_php/s/=.*/= off/' php.ini \
  && echo "xdebug.remote_enable=1" >> php.ini \
  && echo "xdebug.remote_host=host.docker.internal" >> php.ini

# Copy over the entrypoint script
COPY docker/app/entrypoint.sh /usr/bin/entrypoint.sh
USER www-data
WORKDIR /app
ENTRYPOINT ["/usr/bin/entrypoint.sh", "/usr/bin/run-app.sh"]
CMD ["php-fpm"]

# Production: embed code, use production .ini, run command directly ###########

# NOTE: should use same image here and in app
FROM node:14-alpine AS node
COPY ./webapp /app
WORKDIR /app
RUN npm install && npm run production

FROM base AS production
WORKDIR /usr/local/etc/php
RUN mv php.ini-production php.ini \
  && sed -i '/upload_max_filesize/s/=.*/= 20M/' php.ini \
  && sed -i '/post_max_size/s/=.*/= 20M/' php.ini \
  && sed -i '/expose_php/s/=.*/= off/' php.ini

COPY --chown=www-data ./webapp /app
COPY --from=node /app/public/mix-manifest.json /app/public/mix-manifest.json

USER www-data
WORKDIR /app
RUN composer install --no-dev --no-interaction --optimize-autoloader

ENTRYPOINT ["/sbin/tini", "--", "/usr/bin/run-app.sh"]
CMD ["php-fpm"]
