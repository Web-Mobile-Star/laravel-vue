#!/bin/bash

if [[ "$#" != 1 ]]; then
  echo "Usage: $0 dump.sql"
  exit 1
fi

# NOTE: we have to use plain Docker in order to be compatible with redirections
# (e.g. for restoring a database backup)
docker exec -i docker-autofeedback_mariadb_1 mysql -u root -piamroot my_database < "$1"
