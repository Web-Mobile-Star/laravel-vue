FROM docker.io/bitnami/nginx:1.19-debian-10 AS development
USER root

# For running nginx behind a reverse proxy
ENV SERVER_NAME localhost
ENV SERVER_PORT 3000
ENV HTTP_HOST localhost:3000
ENV REQUEST_SCHEME http

COPY docker/nginx/envsubst-entrypoint.sh /opt/bitnami/scripts/nginx/envsubst-entrypoint.sh

# Same as in the Bitnami Dockerfile
USER 1001

# Allow us to override server name and port using env vars
ENTRYPOINT [ "/opt/bitnami/scripts/nginx/envsubst-entrypoint.sh"]
CMD [ "/opt/bitnami/scripts/nginx/run.sh" ]

## PRODUCTION ##################################################################

# NOTE: should use same image here and in app
FROM node:14-alpine AS node
COPY ./webapp /app
WORKDIR /app
RUN npm install && npm run production

FROM development AS production
USER root

COPY docker/nginx/templates /opt/bitnami/nginx/conf/server_block_templates
COPY --from=node /app/public /app/public

# Same as in the Bitnami Dockerfile
USER 1001
