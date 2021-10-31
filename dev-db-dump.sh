#!/bin/bash

if [[ "$#" != 1 ]]; then
  echo "Usage: $0 dump.sql"
  exit 1
fi

./dev-compose.sh exec mariadb mysqldump \
	--add-drop-database \
	-u root -piamroot \
	--databases my_database > "$1"
