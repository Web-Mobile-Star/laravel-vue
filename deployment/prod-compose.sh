#!/bin/sh

SCRIPT_DIR="$(dirname $(readlink -f "$0"))"
cd "$SCRIPT_DIR"

/usr/local/bin/docker-compose \
  -f docker-compose.yml \
  -f docker-compose.vols.yml \
  -f docker-compose.local.yml \
  "$@"
