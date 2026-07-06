#!/usr/bin/env bash
set -Eeuo pipefail

wordpress_tmp_dir="${WORDPRESS_TMP_DIR:-/tmp/wordpress}"

mkdir -p "$wordpress_tmp_dir"
chown www-data:www-data "$wordpress_tmp_dir" || true
chmod 1777 "$wordpress_tmp_dir"

exec docker-entrypoint.sh "$@"
