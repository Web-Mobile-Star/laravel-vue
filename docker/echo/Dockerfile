FROM node:14-alpine

# The host of the server that authenticates private and presence channels
ENV AUTH_HOST=http://nginx:8080

# 16 hex digits, generate randomly
ENV APP_ID=0000000000000000

# 32 hex digits, generate randomly
ENV APP_KEY=00000000000000000000000000000000

# Development mode? (true/false)
ENV DEV_MODE=false

# Port to run from
ENV SERVER_PORT=6001

# Protocol to use
ENV PROTOCOL=http

# Redis connection details
ENV REDIS_PORT=6379
ENV REDIS_HOST=redis
ENV REDIS_PASSWORD=password

# Should match the redis.options.prefix entry in the Laravel config/database.php
ENV REDIS_PREFIX=autofeedback_database_

RUN apk --update add tini
RUN npm install -g laravel-echo-server

USER node
WORKDIR /home/node
COPY run-echo.sh /usr/local/bin/run-echo.sh

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/run-echo.sh"]
CMD ["laravel-echo-server", "start"]
