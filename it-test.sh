#!/bin/sh

SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

TEST_RESULT_FOLDER="$SCRIPT_DIR/it-test"
rm -rf "$TEST_RESULT_FOLDER"
mkdir "$TEST_RESULT_FOLDER"
chmod a+rw "$TEST_RESULT_FOLDER"

DOCKER_TEST_RESULT_FOLDER="/tmp/it-test"

"$SCRIPT_DIR/it-compose.sh" \
    run --rm -v "$TEST_RESULT_FOLDER:$DOCKER_TEST_RESULT_FOLDER" \
    default-worker \
    -w /bin/sh -c "composer install && php artisan config:clear && cp .env.example .env && php artisan key:generate && vendor/phpunit/phpunit/phpunit --log-junit $DOCKER_TEST_RESULT_FOLDER/junit.xml"
