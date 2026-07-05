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
```

Start:

```sh
docker compose up -d
```

Backup:

```sh
./backup.sh
```

Port `3306` is exposed for YC Serverless WordPress. Use strong generated credentials and do not reuse them elsewhere.
