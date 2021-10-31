ARG XDEBUG_VERSION=2.9.6

FROM php:7.4-cli AS base

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Need JAVA_HOME for the 'mvn site'
ENV DEBIAN_FRONTEND=noninteractive

# mkdir needed to work around issue in Buster Slim:
# https://bugs.debian.org/cgi-bin/bugreport.cgi?bug=863199
#
# libpango1.0-dev needed for JavaFX headless tests
RUN mkdir -p /usr/share/man/man1 \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
         maven openjdk-11-jdk-headless libpango1.0-dev tini libzip-dev libldap2-dev netcat \
    && rm -rf /var/lib/apt/lists/*

# apk --update add --no-cache maven openjdk11-jdk tini libzip-dev openldap-dev shadow

# Install required PHP extensions
RUN docker-php-ext-install bcmath pdo_mysql zip fileinfo ldap pcntl

# Change home directory for www-data and match uid/gid for Alpine (82)
RUN mkdir -p /home/www-data \
    && chown www-data: /home/www-data \
    && usermod -d /home/www-data www-data \
    && usermod -u 82 www-data \
    && groupmod -g 82 www-data \
    && usermod -g 82 www-data

# Copy the Maven settings file
ENV M2_SETTINGS=/etc/m2-settings.xml
COPY docker/worker/m2-settings.xml ${M2_SETTINGS}

# Copy the wait loop script (to avoid unnecessary restarts)
COPY docker/worker/run-worker.sh /usr/bin/run-worker.sh

# Copy the Java security policy intended for Surefire runs
ENV SUREFIRE_POLICY=/etc/surefire.policy
COPY docker/worker/java.policy ${SUREFIRE_POLICY}

# Create the mountpoint for the m2 volume, and the directory for the OpenJFX cache
USER www-data
RUN mkdir -p /home/www-data/.m2 /home/www-data/.openjfx/cache/11
USER root

# Environment variables so they know which app host to wait for
ENV APP_HOST=app
# Port for php-fpm
ENV APP_PORT=9000

# Development: fix perms, disable caching, use development .ini ################

FROM base AS development
ARG XDEBUG_VERSION

# Copy over the fix-perms script
COPY --from=sudobmitch/base:scratch / /

# gosu is needed for running as a specific user
RUN apt-get update \
    && apt-get install -y --no-install-recommends gosu \
    && rm -rf /var/lib/apt/lists/*

# Install the XDebug PHP extension
RUN pecl install xdebug-${XDEBUG_VERSION} \
    && docker-php-ext-enable xdebug

WORKDIR /usr/local/etc/php
RUN mv php.ini-development php.ini && sed -ri '/opcache.enable/s/;?opcache.enable=1/opcache.enable=0/' php.ini
COPY docker/worker/entrypoint.sh /usr/bin/entrypoint.sh

USER www-data
WORKDIR /app
ENTRYPOINT ["/usr/bin/entrypoint.sh", "/usr/bin/run-worker.sh"]
CMD ["php", "artisan", "queue:work", "redis"]

# Production: embed code but keep it non-writeable to the unprivileged user. ###

# We need the Mix manifest to run integration tests.
# NOTE: should use same image here and in nginx
FROM node:14-alpine AS node
COPY ./webapp /app
WORKDIR /app
RUN npm install && npm run production

# Storage will be a tmpfs (to be lost between restarts).
# Use production .ini, run command directly.
FROM base AS production
WORKDIR /usr/local/etc/php
RUN mv php.ini-production php.ini
COPY --chown=www-data ./webapp /app
COPY --from=node --chown=www-data /app/public /app/public

USER www-data
WORKDIR /app
RUN composer install --no-dev --no-interaction --optimize-autoloader

ENTRYPOINT ["/usr/bin/tini", "--", "/usr/bin/run-worker.sh"]
CMD ["php", "artisan", "queue:work", "redis"]
