#!/bin/bash

START_SECS=$(date +'%s')
MINIMUM_SECS=10

########################
# Wait for php-fpm to be ready
# Globals:
#   DATABASE_HOST
#   DB_PORT
# Arguments: none
# Returns: none
#########################
wait_for_app() {
    local app_host="${APP_HOST:-app}"
    local app_port="${APP_PORT:-9000}"
    local app_address=$(getent hosts "$app_host" | awk '{ print $1 }')
    counter=0
    echo "Connecting to app php-fpm at $app_address"
    while ! nc -z "$app_address" "$app_port" >/dev/null; do
        counter=$((counter+1))
        if [ $counter == 30 ]; then
            echo "Error: Couldn't connect to app."
            exit 1
        fi
        echo "Trying to connect to app at $app_address. Attempt $counter."
        sleep 5
    done
}

set -e

if test "$1" = php -a "$2" = artisan -a "$3" = queue:work; then
    if test -n "$APP_DEBUG"; then
        echo "Worker loop starting with command '$@'"
    fi

    wait_for_app
    "$@"

    # If doing a oneshot job, make sure we don't run into the backoff
    # policy in Docker Compose.
    if test "$4" = --once -o "$4" = "--max-jobs=1"; then
        let ELAPSED_SECS=$(date +'%s')-$START_SECS
        if test $ELAPSED_SECS -lt $MINIMUM_SECS; then
            let WAIT_SECS=$MINIMUM_SECS-$ELAPSED_SECS+1
            if test -n "$APP_DEBUG"; then
                echo "Job was too short, waiting ${WAIT_SECS}s"
            fi
            sleep "$WAIT_SECS"s
        fi
    fi
else
    if test "$1" = -w; then
        wait_for_app
        shift
    fi
    exec "$@"
fi
