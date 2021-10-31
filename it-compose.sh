#!/bin/sh

if test -z "$TEST_TAG"; then
    export TEST_TAG=production
fi
if test -z "$DOCKER_REPO"; then
    export DOCKER_REPO=autofeedback
fi

# Bring up the integration test environment
docker-compose \
     -f docker-compose.yml \
     -f docker-compose.vols.yml \
     -f docker-compose.itenvs.yml \
     -f docker-compose.itimgs.yml \
     -f docker-compose.itnet.yml \
     -f docker-compose.ldap.yml \
     -p autofeedback-it \
     "$@"
