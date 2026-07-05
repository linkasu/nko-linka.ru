#!/usr/bin/env sh
set -eu

if [ "$#" -ne 1 ]; then
  printf 'Usage: %s <ssh-alias>\n' "$0" >&2
  exit 2
fi

SSH_ALIAS="$1"

printf '== ssh target ==\n'
ssh -G "$SSH_ALIAS" | awk '/^(hostname|user|port|identityfile) / { print }'

printf '\n== host ==\n'
ssh "$SSH_ALIAS" 'hostname && uname -a'

printf '\n== docker containers ==\n'
ssh "$SSH_ALIAS" 'docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"'

printf '\n== docker volumes ==\n'
ssh "$SSH_ALIAS" 'docker volume ls'

printf '\n== docker networks ==\n'
ssh "$SSH_ALIAS" 'docker network ls'
