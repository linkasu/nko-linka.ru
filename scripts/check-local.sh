#!/usr/bin/env sh
set -eu

if command -v php >/dev/null 2>&1; then
  php -l wp-content/mu-plugins/linka-nko-donations.php
  php -l wp-content/mu-plugins/linka-nko-recurring-state.php
  php -l wp-content/mu-plugins/linka-nko-retention.php
  php -l wp-content/mu-plugins/linka-nko-registries.php
  php -l wp-content/mu-plugins/linka-nko-safety.php
  php -l wp-content/themes/linka-nko/functions.php
  php -l wp-content/themes/linka-nko/header.php
  php -l wp-content/themes/linka-nko/footer.php
  php -l wp-content/themes/linka-nko/front-page.php
  php -l wp-content/themes/linka-nko/page.php
  php -l wp-content/themes/linka-nko/index.php
  php -l infra/wordpress/healthz.php
  php -l infra/wordpress/readyz.php
  php -l infra/private-network/validate-config.php
  php -l infra/private-network/render-config.php
  php -l infra/private-network/config-value.php
  php -l infra/private-network/secure-files.php
  php -l infra/yandex/blue-green/render-release.php
  php -r 'json_decode(file_get_contents("infra/yandex/blue-green/release-manifest.example.json"), true, 512, JSON_THROW_ON_ERROR);'
  php infra/private-network/validate-config.php --check-template infra/private-network/network.env.example infra/mariadb/.env.example
  php tests/readiness.php
  php tests/source-policy.php
  php tests/recurring-state.php
  php tests/donations-security.php
  php tests/retention.php
  php tests/blue-green.php
  php tests/private-network-config.php
  php tests/registries.php
else
  printf 'skip: php is not installed, PHP syntax checks were not run\n' >&2
fi

if command -v ruby >/dev/null 2>&1; then
  ruby -e "require 'yaml'; YAML.load_file('.github/workflows/container.yml')"
  ruby -e "require 'yaml'; YAML.load_file('infra/yandex/api-gateway.yaml')"
  ruby -e "require 'yaml'; YAML.load_file('infra/mariadb/docker-compose.yml')"
else
  printf 'skip: ruby is not installed, YAML syntax check was not run\n' >&2
fi

if command -v docker >/dev/null 2>&1; then
  MARIADB_BIND_ADDRESS=10.90.0.2 \
    MARIADB_ROOT_PASSWORD=synthetic-test-value \
    WORDPRESS_DB_NAME=wordpress \
    WORDPRESS_DB_USER=wordpress \
    WORDPRESS_DB_PASSWORD=synthetic-test-value \
    docker compose --env-file infra/mariadb/.env.example -f infra/mariadb/docker-compose.yml config --quiet
else
  printf 'skip: docker is not installed, Compose config was not validated\n' >&2
fi

sh -n scripts/yc-inventory.sh
sh -n scripts/server-inventory.sh
sh -n scripts/check-local.sh
sh -n infra/mariadb/backup.sh
