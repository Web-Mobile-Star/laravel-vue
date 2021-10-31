#!/bin/bash

# Builds and tags production-stage Docker containers for local
# integration testing. Check .gitlab-ci.yml for CI commands.

set -e
source docker-functions.sh

for SERVICE in app worker nginx echo; do
    build_test_image $SERVICE production
done
