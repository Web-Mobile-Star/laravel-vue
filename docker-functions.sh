#!/bin/bash

# Bash functions for managing Docker images, to be reused from
# Gitlab CI and local scripts.
#
# Uses these environment variables:
# - DOCKER (command to use to run Docker, "docker" by default)
# - DOCKER_REPO (namespace for the images, "autofeedback" by default)


# Default values for local runs
if test -z "$DOCKER"; then
    export DOCKER="docker"
fi
if test -z "$DOCKER_REPO"; then
    # Running in our dev computer
    export DOCKER_REPO=autofeedback
fi
export DOCKER_BUILDKIT=1


build_test_image() {
    if test "$#" != 2; then
        echo "Usage: build_test_image (app|worker|nginx) tag"
        return 1
    fi
    SERVICE="$1"
    TAG="$2"

    case "$SERVICE" in
        app)
            $DOCKER build --pull \
                    --network host \
                    --target production \
                    --file docker/app/Dockerfile \
                    -t $DOCKER_REPO/$SERVICE:$TAG \
                    .
            ;;
        worker)
            $DOCKER build --pull \
                    --network host \
                    --target production \
                    --file docker/worker/Dockerfile \
                    -t $DOCKER_REPO/$SERVICE:$TAG \
                    .
            ;;
        nginx)
            $DOCKER build --pull \
                    --network host \
                    --target production \
                    --file docker/nginx/Dockerfile \
                    -t $DOCKER_REPO/$SERVICE:$TAG \
                    .
            ;;
        echo)
            $DOCKER build --pull \
                    --network host \
                    -t $DOCKER_REPO/$SERVICE:$TAG \
                    docker/echo
            ;;
        *)
            echo "Unknown image: cannot build"
            ;;
    esac
}


release_test_image() {
    if test "$#" != 3; then
        echo "Usage: release_test_image (app|worker|nginx|echo) test_tag release_tag"
        return 1
    fi
    SERVICE="$1"
    TEST_TAG="$2"
    RELEASE_TAG="$3"

    $DOCKER tag $DOCKER_REPO/$SERVICE:$TEST_TAG $DOCKER_REPO/$SERVICE:$RELEASE_TAG
}

pull_image() {
    if test "$#" != 2; then
        echo "Usage: pull_image (app|worker|nginx|echo) tag"
        return 1
    fi
    SERVICE="$1"
    TAG="$2"

    $DOCKER pull $DOCKER_REPO/$SERVICE:$TAG
}

push_image() {
    if test "$#" != 2; then
        echo "Usage: push_image (app|worker|nginx|echo) tag"
        return 1
    fi
    SERVICE="$1"
    TAG="$2"

    $DOCKER push $DOCKER_REPO/$SERVICE:$TAG
}
