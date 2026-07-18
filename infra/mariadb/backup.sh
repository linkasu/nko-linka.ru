#!/usr/bin/env sh
set -eu

cd "$(dirname "$0")"

if [ ! -f .env ]; then
  printf '.env not found\n' >&2
  exit 1
fi

TS="$(date -u +%Y%m%dT%H%M%SZ)"
OUT="backups/nko-linka-wordpress-${TS}.sql.gz"

docker compose exec -T mariadb sh -c \
  'exec mariadb-dump --single-transaction --quick --default-character-set=utf8mb4 -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
  | gzip -9 > "${OUT}"

chmod 600 "${OUT}"
printf '%s\n' "${OUT}"
