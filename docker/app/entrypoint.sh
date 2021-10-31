#!/bin/sh

# From 'Tips and Tricks of the Docker Captains' (Docker channel, YouTube)
# https://youtu.be/woBI466WMR8

if [ "$(id -u)" = "0" ]; then
    # running on a dev machine: fix perms then run as bitnami
    fix-perms -r -u www-data -g tty /app
    chgrp tty /dev/pts/0
    exec su-exec www-data:tty /sbin/tini -- "$@"
else
    # run as usual
    exec /sbin/tini -- "$@"
fi
