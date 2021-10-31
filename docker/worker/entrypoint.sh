#!/bin/sh

# From 'Tips and Tricks of the Docker Captains' (Docker channel, YouTube)
# https://youtu.be/woBI466WMR8

if [ "$(id -u)" = "0" ]; then
    # running on a dev machine: fix perms then run as non-privileged user
    fix-perms -r -u www-data -g tty /app
    mkdir -p /app/storage/framework/cache/data
    mkdir -p /app/storage/framework/sessions
    mkdir -p /app/storage/framework/views
    mkdir -p /app/storage/logs
    chown -R www-data: /app/storage

    exec gosu www-data:tty /usr/bin/tini -- "$@"
else
    # run as usual (note: production doesn't use this entrypoint)
    exec /usr/bin/tini -- "$@"
fi
