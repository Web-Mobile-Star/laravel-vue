#!/bin/bash

export COMPOSE_DOCKER_CLI_BUILD=1
export DOCKER_BUILDKIT=1

# The two environment variables ensure BuildKit is used for faster builds.
# Use the base layer, then the development layer.
docker-compose -f docker-compose.yml \
               -f docker-compose.dev.yml \
               -f docker-compose.ldap.yml \
               "$@"
