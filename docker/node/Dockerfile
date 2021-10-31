# Node, tweaked for a development environment
FROM node:14-alpine
COPY --from=sudobmitch/base:scratch / /
RUN apk add --no-cache su-exec shadow tini
RUN adduser -g '' -D user
COPY entrypoint.sh /usr/bin/entrypoint.sh

USER user
WORKDIR /app
ENTRYPOINT ["/usr/bin/entrypoint.sh"]
CMD ["-i", "/usr/local/bin/npm", "run", "watch"]
