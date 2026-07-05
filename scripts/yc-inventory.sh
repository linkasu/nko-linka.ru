#!/usr/bin/env sh
set -eu

FOLDER_ID="${YC_FOLDER_ID:-b1gn4stour811vgtjude}"

printf '== yc config ==\n'
yc config profile list || true
printf 'cloud-id: '
yc config get cloud-id || true
printf 'folder-id: '
yc config get folder-id || true

printf '\n== folder ==\n'
yc resource-manager folder get "$FOLDER_ID"

printf '\n== serverless containers ==\n'
yc serverless container list --folder-id "$FOLDER_ID"

printf '\n== container registries ==\n'
yc container registry list --folder-id "$FOLDER_ID"

printf '\n== certificates ==\n'
yc certificate-manager certificate list --folder-id "$FOLDER_ID"

printf '\n== storage buckets ==\n'
yc storage bucket list --folder-id "$FOLDER_ID"
