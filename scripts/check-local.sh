#!/usr/bin/env sh
set -eu

if command -v php >/dev/null 2>&1; then
  php -l wp-content/mu-plugins/linka-nko-safety.php
  php -l wp-content/themes/linka-nko/functions.php
  php -l wp-content/themes/linka-nko/header.php
  php -l wp-content/themes/linka-nko/footer.php
  php -l wp-content/themes/linka-nko/front-page.php
  php -l wp-content/themes/linka-nko/page.php
  php -l wp-content/themes/linka-nko/index.php
  php -l infra/wordpress/healthz.php
else
  printf 'skip: php is not installed, PHP syntax checks were not run\n' >&2
fi

if command -v ruby >/dev/null 2>&1; then
  ruby -e "require 'yaml'; YAML.load_file('.github/workflows/container.yml')"
else
  printf 'skip: ruby is not installed, YAML syntax check was not run\n' >&2
fi

sh -n scripts/yc-inventory.sh
sh -n scripts/server-inventory.sh
sh -n scripts/check-local.sh
