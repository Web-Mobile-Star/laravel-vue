#!/bin/sh

########################
# Wait for Redis to be ready
# Globals:
#   REDIS_HOST
#   REDIS_PORT
# Arguments: none
# Returns: none
#########################
wait_for_redis() {
    local redis_host="${REDIS_HOST:-redis}"
    local redis_port="${REDIS_PORT:-6379}"
    local redis_address=$(getent hosts "$redis_host" | awk '{ print $1 }')
    counter=0
    echo "Connecting to Redis at $redis_address"
    while ! nc -z "$redis_address" "$redis_port" >/dev/null; do
        counter=$((counter+1))
        if [ $counter == 30 ]; then
            echo "Error: Couldn't connect to app."
            exit 1
        fi
        echo "Trying to connect to app at $redis_address. Attempt $counter."
        sleep 5
    done
}

generate_configuration() {
    cat > laravel-echo-server.json <<EOF
{
	"authHost": "$AUTH_HOST",
	"authEndpoint": "/broadcasting/auth",
	"clients": [
		{
			"appId": "$APP_ID",
			"key": "$APP_KEY"
		}
	],
	"database": "redis",
	"databaseConfig": {
		"redis": {
      "port": "$REDIS_PORT",
      "host": "$REDIS_HOST",
      "password": "$REDIS_PASSWORD",
      "keyPrefix": "$REDIS_PREFIX"
    },
		"sqlite": {}
	},
	"devMode": $DEV_MODE,
	"host": null,
	"port": "$SERVER_PORT",
	"protocol": "$PROTOCOL",
	"socketio": {},
	"secureOptions": 67108864,
	"sslCertPath": "",
	"sslKeyPath": "",
	"sslCertChainPath": "",
	"sslPassphrase": "",
	"subscribers": {
		"http": false,
		"redis": true
	},
	"apiOriginAllow": {
		"allowCors": false,
		"allowOrigin": "",
		"allowMethods": "",
		"allowHeaders": ""
	}
}
EOF
}

generate_configuration

if test "$1" = "laravel-echo-server" -a "$2" = "start"; then
    wait_for_redis
fi

# Run the server
exec "$@"
