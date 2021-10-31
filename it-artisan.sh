#!/bin/sh

SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

"$SCRIPT_DIR/it-compose.sh" run --rm app -w php artisan "$@"
