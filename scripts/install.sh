#!/usr/bin/env bash

set -Eeuo pipefail

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

fail() {
    printf 'Threeebs: %s\n' "$1" >&2
    exit 1
}

command -v docker >/dev/null 2>&1 || fail 'Docker não foi encontrado.'
docker compose version >/dev/null 2>&1 || fail 'Docker Compose v2 não foi encontrado.'

if [[ ! -f .env ]]; then
    cp .env.example .env
    chmod 600 .env
    fail 'o arquivo .env foi criado. Configure-o e execute novamente.'
fi

chmod 600 .env
if grep -nE '=(TROQUE|CHANGE_ME|COLE_AQUI)' .env; then
    fail 'substitua todos os valores padrão exibidos no .env.'
fi

required_variables=(
    PROJECT_SLUG BASE_DOMAIN PORTAL_URL ADMIN_URL SANDBOX_URL HOST_URL
    MYSQL_ROOT_PASSWORD THREEEBS_ADMIN_DB_USER THREEEBS_ADMIN_DB_PASSWORD
    THREEEBS_PORTAL_DB_USER THREEEBS_PORTAL_DB_PASSWORD
    THREEEBS_SANDBOX_DB_USER THREEEBS_SANDBOX_DB_PASSWORD
    THREEEBS_HOST_DB_USER THREEEBS_HOST_DB_PASSWORD
    THREEEBS_MIGRATOR_DB_USER THREEEBS_MIGRATOR_DB_PASSWORD
    REDIS_PASSWORD THREEEBS_SETUP_KEY
)

for variable_name in "${required_variables[@]}"; do
    grep -qE "^${variable_name}=.+" .env || fail "a variável ${variable_name} está ausente ou vazia no .env."
done

docker compose config --quiet || fail 'a configuração do Docker Compose é inválida.'

install -d -m 0775 \
    storage/projects \
    storage/logs/admin \
    storage/logs/portal \
    storage/logs/sandbox \
    storage/logs/host \
    storage/backups/database \
    storage/backups/projects

printf '%s\n' 'Threeebs: construindo e iniciando os serviços...'
docker compose up -d --build

docker compose exec -T admin sh -lc 'test -w /var/www/projects' \
    || fail 'o Admin não possui escrita em storage/projects.'
docker compose exec -T sandbox sh -lc 'test -w /var/www/projects' \
    || fail 'o Sandbox não possui escrita em storage/projects.'
docker compose exec -T host sh -lc \
    'if su -s /bin/sh -c "touch /var/www/projects/.threeebs-host-write-test" www-data 2>/dev/null; then rm -f /var/www/projects/.threeebs-host-write-test; exit 1; fi' \
    || fail 'o Host possui escrita indevida em storage/projects.'

printf '%s\n' 'Threeebs: instalação iniciada com sucesso.'
docker compose ps
