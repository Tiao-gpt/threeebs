#!/bin/sh

set -eu

if [ "${THREEEBS_PREPARE_PROJECTS:-false}" = "true" ]; then
    mkdir -p /var/www/projects
    chgrp www-data /var/www/projects
    chmod 2775 /var/www/projects

    if ! su -s /bin/sh -c 'test -w /var/www/projects' www-data; then
        echo "Threeebs: /var/www/projects não permite escrita pelo Apache." >&2
        echo "Threeebs: confira a montagem e as permissões de storage/projects." >&2
        exit 1
    fi
fi

exec docker-php-entrypoint "$@"
