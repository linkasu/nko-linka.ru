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

- Serverless Container: `nko-linka-wordpress`, id `bba644mi7027h56etnsd`, URL `https://bba644mi7027h56etnsd.containers.yandexcloud.net/`.
- Active clean CI-built revision: `bbav32pnasspfrru4k36`, image digest `sha256:5243c6b153db5fa04ee66f534f49c246b852e56246bf6145bb47812bc3939694`, execution timeout `300s`.
- Previous admin updater timeout revision: `bbadcp2actunih3j2e48`.
- Previous clean CI-built revision: `bbaa7rj0s11o5ib7hvrc`.
- Previous runtime hotfix revision: `bba9gv4igtssask5na1g`.
- Previous Apache redirect hotfix revision: `bbais7f2cudasnda2pit`.
- Previous revision before Apache redirect hotfix: `bbafgfirvpqipedn6bli`.
- API Gateway: `nko-linka-wordpress`, id `d5dmjh8ur6ogqs55jbqn`, domain `d5dmjh8ur6ogqs55jbqn.iwzqm34r.apigw.yandexcloud.net`.
- Container Registry: `nko-linka`, id `crpu3icktgossftl7l2r`.
- Certificate Manager certificate: `nko-linka-ru`, id `fpq2ktcburgsm11e4vlt`, status `VALIDATING`.
- Certificate Manager certificate: `nkolinka-ru`, id `fpqfb4bbj47ppclem208`, status `ISSUED`, attached to API Gateway domain `nkolinka.ru`.
- DNS zone: `nkolinka-ru`, id `dns9dgeek3orpu0b4v3n`, zone `nkolinka.ru.`.
- Object Storage bucket: `nko-linka-ru-uploads`.
- Lockbox secret: `nko-linka-wordpress-runtime`, id `e6q3r0sba4cimi3e671g`.
- Lockbox secret: `nko-linka-postbox`, id `e6qavstvb3jaj59ptjus`, current version `e6q4q143o82o60f012dj`.
- Lockbox secret: `nko-linka-wordpress-users`, id `e6q718pinteavidarcs3`, current version `e6qmkkqsip25adtl4jr4`.

Service accounts:

- `nko-linka-ci`, id `ajedkt6io7s4dn8v1cog`, role `container-registry.images.pusher` for CI image publishing.
- `nko-linka-runtime`, id `aje9j1qvtr8csrsr28d7`, used by Serverless Container and API Gateway runtime.
- `nko-linka-postbox`, id `ajemsihnm3h2utdl9lvb`, roles `postbox.admin`, `postbox.editor`, and `postbox.sender` for Postbox setup and SMTP sending.
- `nko-linka-runtime`, id `aje9j1qvtr8csrsr28d7`, has `lockbox.payloadViewer` on Lockbox secret `nko-linka-postbox`.

## Domain

- Correct public domain: `nkolinka.ru`.
- Earlier references to `nko-linka.ru` were a domain name mistake.
- `whois nkolinka.ru` returns `REGISTERED, DELEGATED, VERIFIED`.
- Registrar: `REGRU-RU`.
- Delegation: `ns1.yandexcloud.net.`, `ns2.yandexcloud.net.`.
- DNS zone `nkolinka-ru` was created in Yandex Cloud with deletion protection.
- `nkolinka.ru.` ANAME -> `d5dmjh8ur6ogqs55jbqn.iwzqm34r.apigw.yandexcloud.net.`
- Certificate DNS challenge for `fpqfb4bbj47ppclem208`:
- CNAME `_acme-challenge.nkolinka.ru.` -> `fpqfb4bbj47ppclem208.cm.yandexcloud.net.`
- TXT target resolves through the CNAME to `So0nNyHUjamdO164bZlM2CPONqirb3fJRYAKx7ZQOxs`.
- API Gateway attached domain: `nkolinka.ru`, domain id `d5dfeq2jn5ga76usec4t`, enabled `true`.
- WordPress `home` and `siteurl` are set to `https://nkolinka.ru`.
- Public verification on 2026-07-05: `https://nkolinka.ru/`, `/programs/`, `/wp-login.php`, and `/healthz.php` returned `200` with valid TLS.
- `https://nkolinka.ru/wp-admin` redirects to `https://nkolinka.ru/wp-admin/`, then to the WordPress login page without leaking `:8080`.
- Pretty permalinks are enabled with `/%postname%/`; Apache rewrite fallback is enabled in revision `bba9gv4igtssask5na1g`.
- Apache rewrite fallback and `/wp-admin` canonical redirect are baked into active revision `bbav32pnasspfrru4k36`; no startup-command hotfix is used in the active revision.
- WordPress admin updater prerequisites are baked into active revision `bbav32pnasspfrru4k36`: runtime-created writable `/tmp/wordpress`, `FS_METHOD=direct`, `WP_TEMP_DIR=/tmp/wordpress`, PHP `sys_temp_dir=/tmp/wordpress`, PHP `upload_tmp_dir=/tmp/wordpress`, PHP `max_execution_time=300`, container `execution_timeout=300s`.
- Main menu is assigned to theme location `primary` and renders public pages without donation links.
- Chrome DevTools Protocol check on 2026-07-05: clicking the home CTA `Смотреть программы` navigated to `https://nkolinka.ru/programs/`, page title `Программы – АНО Линка`, `h1` `Программы`, no console exceptions, no 4xx/5xx page resources.
- Public home HTML did not contain donation-like words or generated service URLs.
- Mistaken certificate `fpq2ktcburgsm11e4vlt` for `nko-linka.ru` is not used.

## Postbox

- Domain identity: `nkolinka.ru`.
- Verification status on 2026-07-05: `SUCCESS`.
- DKIM status on 2026-07-05: `SUCCESS`.
- Verified for sending: `true`.
- DKIM selector: `pb20260705`.
- DNS records:
- TXT `pb20260705._domainkey.nkolinka.ru.` -> `v=DKIM1;h=sha256;k=rsa;p=...`.
- TXT `nkolinka.ru.` -> `v=spf1 include:spf.postbox.yandexcloud.net ~all`.
- TXT `_dmarc.nkolinka.ru.` -> `v=DMARC1;p=none`.
- SMTP host: `postbox.cloud.yandex.net`, STARTTLS port `587`, SMTPS port `465`.
- SMTP/API secrets are stored in Lockbox secret `nko-linka-postbox` and removed from the local temp directory.
- WordPress Postbox SMTP env vars are bound to active Serverless Container revision `bbav32pnasspfrru4k36`.

## WordPress Users

- `ivan` / `ivan@aacidov.ru`: administrator.
- `darya.garbuzova` / `daria300103@gmail.com`: editor.
- `ekaterina.karpova` / `karpova260102@gmail.com`: editor.
- Initial passwords are stored in Lockbox secret `nko-linka-wordpress-users`.
- Login check for `ivan` on 2026-07-05 returned a `302` to `/wp-admin/` with a `wordpress_logged_in_*` cookie.
- Password reset/access emails were sent on 2026-07-05 to `ivan@aacidov.ru`, `daria300103@gmail.com`, and `karpova260102@gmail.com` through WordPress/Postbox. Each request returned `302 /wp-login.php?checkemail=confirm`.

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
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260705T145629Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260705T164030Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260705T173656Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260705T181042Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260706T125626Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260706T131415Z.sql.gz
```

Current Docker named volumes list is empty from `docker volume ls` output; MariaDB uses bind mounts in `/home/aacidov/nko-linka-db`.

## Gaps

- Improve visual/content completeness: home page sections, linked cards, uploaded legal PDFs, and full article texts/images.
- Upload public PDF documents to Object Storage/media and replace placeholder document links.
