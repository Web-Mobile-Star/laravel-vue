#!/bin/sh

SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

"$SCRIPT_DIR/dev-compose.sh" run --rm app -w php artisan "$@"
