# Inventory

Дата инвентаря: 2026-07-05.

## Local

- Рабочая папка: `/Volumes/data/linka/nko-linka.ru`.
- Git repository initialized locally.
- `yc` CLI installed: `Yandex Cloud CLI 1.16.0 darwin/arm64`.

## Yandex Cloud

- Active YC profile: `default`.
- Cloud ID: `b1gp709tvcms6rd4cbuq`.
- Folder ID: `b1gn4stour811vgtjude`.
- Folder name: `nko-linka`.
- Folder status: `ACTIVE`.

Current folder resources after initial setup:

- Serverless Containers: none.
- Container Registry: `nko-linka`, id `crpu3icktgossftl7l2r`.
- Certificate Manager certificates: none.
- Object Storage buckets: none.

Service accounts:

- `nko-linka-ci`, id `ajedkt6io7s4dn8v1cog`, role `container-registry.images.pusher` for CI image publishing.

## Domain

- `https://nko-linka.ru` returns `502 Bad Gateway`.
- This indicates DNS/routing exists, but the target application is not healthy or not deployed.

## Server 37.230.192.57

Connection tested as `aacidov@37.230.192.57`.

Host:

```text
hostname: test
kernel: Linux 5.15.0-161-generic x86_64
```

Running containers:

```text
socks5-service-a    ghcr.io/ibakaidov/bakaidov-proxy-bot-service-a:sha-4078cdd   Up 2 months
proxybot-postgres   postgres:16.9-bookworm                                       Up 2 months (healthy)
nko-linka-mariadb   mariadb:11.4                                                 Up, healthy
```

Observed ports:

- `1080/tcp` and `20000-20255/udp` exposed by `socks5-service-a`.
- `5432/tcp` exposed by `proxybot-postgres`.
- `3306/tcp` exposed by `nko-linka-mariadb`.

MariaDB project path:

```text
/home/aacidov/nko-linka-db
```

MariaDB backup check:

```text
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260705T144735Z.sql.gz
```

Current Docker named volumes list is empty from `docker volume ls` output; MariaDB uses bind mounts in `/home/aacidov/nko-linka-db`.

## Gaps

- YC Serverless Container is not created yet.
- Object Storage bucket for uploads is not created yet.
- TLS certificate for `nko-linka.ru` is not created yet.
- Runtime service account is not created yet.
