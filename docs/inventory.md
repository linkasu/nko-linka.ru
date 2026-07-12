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
- Active revision with donation form mobile UX fix, YooKassa receipt sending off, thank-you return URL, recurring donation groundwork, self-service recurring cancellation, and recurring runtime disabled after YooKassa rejection: `bbau0c7fe7p14lr1v4p1`, image digest `sha256:c753b7ed99cc9fae09bcb480864db665f33126862cf8b5bdcbaa9baf7f2329bd`, execution timeout `300s`.
- Previous recurring-disabled donation UX revision: `bbaolpgu74iar2u89o7l`.
- Previous recurring-disabled revision: `bbakhuhut5jqv8j0gir5`.
- Previous attempted recurring-enabled revision: `bba2nq4aj5eprsnj5is6`.
- Previous recurring-groundwork revision: `bbainuqse0d3thjkta9r`.
- Previous thank-you return URL revision: `bbauqmvqv5grsaocecct`.
- Previous receipt-off revision: `bba024ip5isf0e4bcvqr`.
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
- Lockbox secret: `nko-linka-yookassa`, id `e6q8l62gpq6o2hgserti`, current version `e6q1mtuhbkvj05fgs8ch`.

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
- Apache rewrite fallback and `/wp-admin` canonical redirect are baked into active revision `bbau0c7fe7p14lr1v4p1`; no startup-command hotfix is used in the active revision.
- WordPress admin updater prerequisites are baked into active revision `bbau0c7fe7p14lr1v4p1`: runtime-created writable `/tmp/wordpress`, `FS_METHOD=direct`, `WP_TEMP_DIR=/tmp/wordpress`, PHP `sys_temp_dir=/tmp/wordpress`, PHP `upload_tmp_dir=/tmp/wordpress`, PHP `max_execution_time=300`, container `execution_timeout=300s`.
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
- WordPress Postbox SMTP env vars are bound to active Serverless Container revision `bbau0c7fe7p14lr1v4p1`.

## Donations

- Page `https://nkolinka.ru/donate/` is published.
- Page `https://nkolinka.ru/donation-offer/` is published.
- Page `https://nkolinka.ru/donation-thanks/` is published as the YooKassa return page.
- Page `https://nkolinka.ru/donation-subscription/` is published for protected-link monthly donation management.
- Page `https://nkolinka.ru/privacy-policy/` is updated for voluntary donation payment processing and YooKassa data transfer.
- Page `https://nkolinka.ru/requisites/` states that payment requisites are not published on the site.
- Home page has a `Пожертвовать` CTA.
- Main menu has a `Пожертвовать` item.
- Donation page contains the active `[linka_donation_form]` shortcode.
- Donation form mobile UX fix on 2026-07-12: when recurring runtime is disabled, monthly donations are shown as a non-clickable status instead of a disabled radio button; consent is displayed as a separate highlighted checkbox block.
- The form creates YooKassa payments server-side without a `receipt` object and redirects the donor to the YooKassa confirmation URL.
- New donation payments are written into custom WordPress tables `wp_linka_donation_payments` and, for monthly donations, `wp_linka_donation_subscriptions`; YooKassa remains the payment source of truth.
- One-time donations explicitly set `save_payment_method=false` so all regular YooKassa methods enabled for the shop remain available without saving the payment method.
- YooKassa added T-Pay and SBP on 2026-07-12. The contract email states SBP commission is `0.4%` with a cap of `1500 RUB`; T-Pay commission is `3%`; changes take effect the next day. Confirmation emails `31811` and `31812` state SBP and T-Pay were connected for shop `1403902`.
- YooKassa `/v3/me` on 2026-07-12 returns `payment_methods=["yoo_money","bank_card","sberbank","sbp","tinkoff_bank"]` and `fiscalization_enabled=false`.
- Monthly donation code is prepared behind `YOOKASSA_RECURRING_ENABLED`; it uses `save_payment_method=true` on the first payment, stores `payment_method.id` after YooKassa confirmation, and charges subsequent donations through a protected recurring runner endpoint.
- Self-service management page at `/donation-subscription/` is deployed for protected-link cancellation and local saved payment method removal. Screenshots were captured on 2026-07-12 for YooKassa autopayment approval.
- WordPress page `/donation-subscription/` was created in production with page id `49` and contains `[linka_donation_subscription]`.
- YooKassa approval screenshots were captured at `/var/folders/l4/r3plyjwj7pz_nq7zz50x04880000gn/T/opencode/nko-yookassa-screenshots/donation-subscription-active.png` and `/var/folders/l4/r3plyjwj7pz_nq7zz50x04880000gn/T/opencode/nko-yookassa-screenshots/donation-subscription-cancelled.png`.
- YooKassa webhook endpoint is `https://nkolinka.ru/wp-admin/admin-post.php?action=linka_nko_yookassa_webhook`.
- Recurring runner endpoint is `https://nkolinka.ru/wp-admin/admin-post.php?action=linka_nko_run_recurring_donations&token=...`; it requires `LINKA_NKO_RECURRING_TOKEN`.
- Runtime YooKassa env vars are bound to active Serverless Container revision `bbau0c7fe7p14lr1v4p1` from Lockbox secret `nko-linka-yookassa`.
- Lockbox version `e6q1mtuhbkvj05fgs8ch` sets `YOOKASSA_SEND_RECEIPT=false`, `YOOKASSA_RETURN_URL=https://nkolinka.ru/donation-thanks/`, `YOOKASSA_RECURRING_ENABLED=false`, and a protected recurring runner token.
- Production recurring runtime was tested and then disabled because YooKassa has not enabled autopayments for this store.
- Direct YooKassa production capability test on 2026-07-12 with `save_payment_method=true` returned `403 forbidden`, `This store can't make recurring payments. Contact the YooMoney manager to learn more`.
- YooKassa webhook API check on 2026-07-12 with Basic Auth returned `401 invalid_credentials`, `Authentication type is not allowed`; for this integration, webhooks must be configured in the YooKassa Merchant Profile.
- Production deploy on 2026-07-12 created the donation tables and verified one-time test payment creation: local payment id `1`, YooKassa payment `31e5449b-000f-5001-9000-135353b43777`, status `pending`, unpaid, `frequency=one_time`, `payment_method_saved=0`.
- Forced monthly POST on 2026-07-12 returned `503` before creating a payment or subscription because `YOOKASSA_RECURRING_ENABLED` is unset.
- Attempted recurring-enabled monthly POST on 2026-07-12 returned `502` after YooKassa rejected `save_payment_method=true`; local test payment id `3` was marked `failed` and local subscription id `1` should be treated as failed test data.
- Initial read-only DB check on 2026-07-12 found no local WordPress payment record for the earlier 500 RUB test payment because that payment happened before custom donation tables were deployed.
- YooKassa API check on 2026-07-12 found a paid `500.00 RUB` payment with status `succeeded`.
- YooKassa email on 2026-07-12 confirmed that automatic receipts were disabled for contract `НЭК.451387.01`; the support reply confirmed that no special payment scenario or description is required for voluntary donations.
- YooKassa email `31813` on 2026-07-12 confirmed that payment methods were added and asked to reply when the site is ready to connect autopayments.
- YooKassa autopayment approval email with self-service cancellation screenshots was sent on 2026-07-12 to `ecommerce@yoomoney.ru` and `b2b_support@yoomoney.ru`; response is pending.
- YooKassa replied on 2026-07-12 that autopayment approval requires full-screen screenshots from the site with the site address visible.
- Full-screen screenshots with the browser address bar visible were captured on 2026-07-12 at `/var/folders/l4/r3plyjwj7pz_nq7zz50x04880000gn/T/opencode/nko-yookassa-screenshots/donation-subscription-active-fullscreen.png` and `/var/folders/l4/r3plyjwj7pz_nq7zz50x04880000gn/T/opencode/nko-yookassa-screenshots/donation-subscription-cancelled-fullscreen.png`.
- Updated full-screen screenshots were sent to YooKassa on 2026-07-12; response is pending.
- Donation page states that a donation is not payment for goods, services, courses, consultations, software or digital services.
- Public verification on 2026-07-12: `/`, `/donate/`, and `/healthz.php` returned `200`; `/donate/` rendered the donation form and submit button.
- Test POST verification on 2026-07-12: WordPress donation handler returned `303` to `https://yoomoney.ru/checkout/payments/v2/contract` without sending `receipt`; no payment was completed.
- Self-service cancellation verification on 2026-07-12 used test subscription id `2`: before cancellation it was `active` with a fake `payment_method_id`; after public POST cancellation it became `canceled`, `payment_method_id=NULL`, `next_charge_at=NULL`, `canceled_at` set, and `cancellation_email_sent_at` set. No recurring runtime was enabled.

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
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260711T221645Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260712T061206Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260712T062016Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260712T080753Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260712T125023Z.sql.gz
/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20260712T133012Z.sql.gz
```

Current Docker named volumes list is empty from `docker volume ls` output; MariaDB uses bind mounts in `/home/aacidov/nko-linka-db`.

## Gaps

- Improve visual/content completeness: home page sections, linked cards, uploaded legal PDFs, and full article texts/images.
- Upload public PDF documents to Object Storage/media and replace placeholder document links.
