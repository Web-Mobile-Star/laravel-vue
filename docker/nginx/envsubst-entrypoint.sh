#!/bin/bash

# Replace variables in the server block templates
for template in /opt/bitnami/nginx/conf/server_block_templates/*.template; do
    FNAME="$(basename "$template")"
    TARGET="/opt/bitnami/nginx/conf/server_blocks/${FNAME%.template}"
    sed "s#\${SERVER_NAME}#${SERVER_NAME}#;s#\${SERVER_PORT}#${SERVER_PORT}#;s#\${HTTP_HOST}#${HTTP_HOST}#;s#\${REQUEST_SCHEME}#${REQUEST_SCHEME}#" "$template" > "$TARGET"
done

exec /opt/bitnami/scripts/nginx/entrypoint.sh "$@"
