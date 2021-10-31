#!/bin/sh

# App execution script. Uses resources from:
#
# * Bitnami Laravel Docker image
#   https://github.com/bitnami/bitnami-docker-laravel/

########################
# Wait for service to be ready
# Globals: none
# Arguments: name host port
# Returns: none
#########################
wait_for_service() {
    local service_name="$1"
    local service_host="$2"
    local service_port="$3"
    local service_address=$(getent hosts "$service_host" | awk '{ print $1 }')
    counter=0
    echo "Connecting to $service_name at $service_address"
    while ! nc -z "$service_address" "$service_port" >/dev/null; do
        counter=$((counter+1))
        if [ $counter == 30 ]; then
            echo "Error: Couldn't connect to mariadb."
            exit 1
        fi
        echo "Trying to connect to $service_name at $service_address. Attempt $counter."
        sleep 5
    done
}

wait_for_services() {
    # Run php-fpm after some preparations
    wait_for_service MariaDB "${DB_HOST:-mariadb}" "${DB_PORT:-3306}"
    if test "$WAIT_FOR_LDAP" = true; then
        wait_for_service OpenLDAP "${LDAP_HOST:-openldap}" "${LDAP_PORT:-389}"
    fi
}

# Set the IP address of the network gateway in an environment variable
export DOCKER_GATEWAY=$(ip route show 0.0.0.0/0 dev eth0 | cut -d\  -f3)

# If no trusted proxy has been set, the Docker gateway will be used
if test -z "$TRUSTED_PROXY"; then
    export TRUSTED_PROXY="$DOCKER_GATEWAY"
fi

if test "$1" = "php-fpm"; then
    wait_for_services

    # Install Composer dependencies if missing (for dev machines)
    if ! test -d vendor; then
        composer install --no-dev --no-interaction
    fi

    # Ensure we have an .env configuration file
    if ! test -f .env; then
        cp .env.example .env

        # If we have not defined an APP_KEY environment variable (e.g.
        # by running 'php artisan key:generate --show' and touching up
        # a local Docker Compose file), generate one here.
        if test "$APP_KEY" = ""; then
            php artisan key:generate
        fi
    fi

    # Switch website to maintenance mode
    php artisan down

    # Run any pending migrations
    php artisan migrate --force

    # Ensure new roles and permissions are added
    php artisan db:seed --force --class=RolesPermissionsSeeder

    # Optimize the application
    if test $APP_ENV = production; then
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
    fi

    # Bring the website out of maintenance mode
    php artisan up

    exec "$@"
else
    if test "$1" = -w; then
        wait_for_services
        shift
    fi
    exec "$@"
fi
