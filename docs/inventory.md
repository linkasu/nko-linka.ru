# Inventory

Дата инвентаря: 2026-07-09.

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
- Active revision with YooKassa receipt sending off: `bba024ip5isf0e4bcvqr`, image digest `sha256:ca8fe299de133eabd65d37f910060b5208afb4fdf0026f126cedb8cbc3035e79`, execution timeout `300s`.
- Previous CI-built revision with receipt sending and CSS cache bump: `bba3urejva5ml83m3o6o`.
- Previous receipt-enabled revision: `bbaabt3tls344inv85p1`.
- Previous diagnostics revision: `bbara6sf4jt3ldbu2v5e`.
- Previous YooKassa donation form revision: `bba3vfke42e5f4fqsks2`.
- Previous clean CI-built revision: `bba91c390q8ss4da9vp8`.
- Previous donation temp-dir revision: `bbav32pnasspfrru4k36`.
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
- Lockbox secret: `nko-linka-wordpress-users`, id `e6q718pinteavidarcs3`, current version `e6qnkv6gpslq1qp6q16o`.
- Lockbox secret: `nko-linka-yookassa`, id `e6q8l62gpq6o2hgserti`, current version `e6q9ie2tthsjopg8t0bd`.

Service accounts:

- `nko-linka-ci`, id `ajedkt6io7s4dn8v1cog`, role `container-registry.images.pusher` for CI image publishing.
- `nko-linka-runtime`, id `aje9j1qvtr8csrsr28d7`, used by Serverless Container and API Gateway runtime.
- `nko-linka-postbox`, id `ajemsihnm3h2utdl9lvb`, roles `postbox.admin`, `postbox.editor`, and `postbox.sender` for Postbox setup and SMTP sending.
- `nko-linka-runtime`, id `aje9j1qvtr8csrsr28d7`, has `lockbox.payloadViewer` on Lockbox secrets `nko-linka-postbox` and `nko-linka-yookassa`.

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
- Apache rewrite fallback and `/wp-admin` canonical redirect are baked into active revision `bba024ip5isf0e4bcvqr`; no startup-command hotfix is used in the active revision.
- WordPress admin updater prerequisites are baked into active revision `bba024ip5isf0e4bcvqr`: runtime-created writable `/tmp/wordpress`, `FS_METHOD=direct`, `WP_TEMP_DIR=/tmp/wordpress`, PHP `sys_temp_dir=/tmp/wordpress`, PHP `upload_tmp_dir=/tmp/wordpress`, PHP `max_execution_time=300`, container `execution_timeout=300s`.
- Main menu is assigned to theme location `primary` and includes the public voluntary donation page link.
- Chrome DevTools Protocol check on 2026-07-05: clicking the home CTA `Смотреть программы` navigated to `https://nkolinka.ru/programs/`, page title `Программы – АНО Линка`, `h1` `Программы`, no console exceptions, no 4xx/5xx page resources.
- Public home HTML contains a `Пожертвовать` CTA to `/donate/`.
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
- WordPress Postbox SMTP env vars are bound to active Serverless Container revision `bba024ip5isf0e4bcvqr`.

## Donations

- Page `https://nkolinka.ru/donate/` is published.
- Page `https://nkolinka.ru/donation-offer/` is published.
- Page `https://nkolinka.ru/privacy-policy/` is updated for voluntary donation payment processing and YooKassa data transfer.
- Page `https://nkolinka.ru/requisites/` states that payment requisites are not published on the site.
- Home page has a `Пожертвовать` CTA.
- Main menu has a `Пожертвовать` item.
- Donation page contains the active `[linka_donation_form]` shortcode.
- The form creates YooKassa payments server-side without a `receipt` object and redirects the donor to the YooKassa confirmation URL.
- Runtime YooKassa env vars are bound to active Serverless Container revision `bba024ip5isf0e4bcvqr` from Lockbox secret `nko-linka-yookassa`.
- Lockbox version `e6q9ie2tthsjopg8t0bd` sets `YOOKASSA_SEND_RECEIPT=false`.
- YooKassa email on 2026-07-12 confirmed that automatic receipts were disabled for contract `НЭК.451387.01`; the support reply confirmed that no special payment scenario or description is required for voluntary donations.
- Donation page states that a donation is not payment for goods, services, courses, consultations, software or digital services.
- Public verification on 2026-07-12: `/`, `/donate/`, and `/healthz.php` returned `200`; `/donate/` rendered the donation form and submit button.
- Test POST verification on 2026-07-12: WordPress donation handler returned `303` to `https://yoomoney.ru/checkout/payments/v2/contract` without sending `receipt`; no payment was completed.

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
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260708T133010Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260709T092051Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260709T092950Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260709T095850Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260709T100621Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260709T102653Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260709T104810Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260711T220949Z.sql.gz
```

Current Docker named volumes list is empty from `docker volume ls` output; MariaDB uses bind mounts in `/home/aacidov/nko-linka-db`.

## Gaps

- Improve visual/content completeness: home page sections, linked cards, uploaded legal PDFs, and full article texts/images.
- Upload public PDF documents to Object Storage/media and replace placeholder document links.
