# MariaDB Server

Template for WordPress MariaDB on `37.230.192.57`.

Production path:

```text
/home/aacidov/nko-linka-db
```

Required `.env` on server, never committed:

```sh
MARIADB_ROOT_PASSWORD=...
WORDPRESS_DB_NAME=nko_linka_wordpress
WORDPRESS_DB_USER=nko_linka_wp
WORDPRESS_DB_PASSWORD=...
MARIADB_BIND_ADDRESS=<VPS_WIREGUARD_ADDRESS>
```

Start:

```sh
docker compose up -d
```

Backup:

```sh
./backup.sh
```

Port `3306` must be published only on the VPS WireGuard address. The Compose configuration rejects a missing bind address; do not use `0.0.0.0`, `::` or the VPS public address. Validate the matching private-network configuration before rollout:

```sh
php ../private-network/validate-config.php ../private-network/network.env .env
docker compose config --quiet
```
