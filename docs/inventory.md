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

Current folder resources:

- Serverless Containers: none.
- Container Registry: none.
- Certificate Manager certificates: none.
- Object Storage buckets: none.

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
```

Observed ports:

- `1080/tcp` and `20000-20255/udp` exposed by `socks5-service-a`.
- `5432/tcp` exposed by `proxybot-postgres`.

Current Docker volumes list is empty from `docker volume ls` output.

## Gaps

- MariaDB/MySQL for WordPress is not present yet.
- YC Serverless Container is not created yet.
- Object Storage bucket for uploads is not created yet.
- TLS certificate for `nko-linka.ru` is not created yet.
- Registry target is not created in YC; current scaffold uses GHCR.
