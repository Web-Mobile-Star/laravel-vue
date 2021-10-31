#!/bin/sh

# From 'Tips and Tricks of the Docker Captains' (Docker channel, YouTube)
# https://youtu.be/woBI466WMR8

if [ "$(id -u)" = "0" ]; then
    # running on a dev machine: fix perms then run as bitnami
    fix-perms -r -u user -g user /app

    if test "$1" = "-i"; then
        su-exec user npm install
        shift
    fi
    exec su-exec user /sbin/tini -- "$@"
else
    # run as usual
    if test "$1" = "-i"; then
        npm install
        shift
    fi
    exec /sbin/tini -- "$@"
fi
