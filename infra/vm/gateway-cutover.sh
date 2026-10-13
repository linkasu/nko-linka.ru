#!/bin/sh
# Atomic nft reload that removes only the old Serverless DB source.
set -eu

candidate=/tmp/db-gateway-vm-only.nft
live=/etc/linka-private-network/firewall.nft
backup=/etc/linka-private-network/firewall.nft.pre-wordpress-vm-20261013
original_hash=879d5ea0f2422ccc5616cc5cd4a284d4dc58c4cf6693dbcea438f0bd6fef53f2

[ "$(id -u)" -eq 0 ] || { printf 'root required\n' >&2; exit 1; }
[ -f "$candidate" ] && [ -f "$live" ] || exit 1

check() {
  { printf 'flush table inet linka_gateway\n'; /usr/bin/cat "$candidate"; } | /usr/sbin/nft --check --file -
}

case "${1:-}" in
  --check)
    check
    ;;
  --apply)
    [ ! -e "$backup" ] || { printf 'backup already exists, refusing to replace\n' >&2; exit 1; }
    [ "$(/usr/bin/sha256sum "$live" | /usr/bin/cut -d ' ' -f 1)" = "$original_hash" ] || {
      printf 'live firewall differs from reviewed original\n' >&2; exit 1;
    }
    check
    /usr/bin/install -o root -g root -m 0600 "$live" "$backup"
    /usr/bin/install -o root -g root -m 0600 "$candidate" "$live.next"
    /usr/bin/mv "$live.next" "$live"
    if ! /usr/bin/systemctl reload linka-private-firewall.service; then
      /usr/bin/install -o root -g root -m 0600 "$backup" "$live.rollback"
      /usr/bin/mv "$live.rollback" "$live"
      /usr/bin/systemctl reload linka-private-firewall.service
      printf 'firewall reload failed; original restored\n' >&2
      exit 1
    fi
    printf 'old Serverless DB source removed; VM subnet retained\n'
    ;;
  *)
    printf 'Usage: gateway-cutover.sh --check|--apply\n' >&2
    exit 2
    ;;
esac
