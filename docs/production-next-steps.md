# Production Next Steps

Этот документ фиксирует порядок следующих действий. Он не означает разрешение на destructive-команды.

## 1. GitHub

1. Проверить доступ к организации `linkasu`.
2. Создать репозиторий `linkasu/nko-linka.ru`, если его еще нет.
3. Добавить remote `origin`.
4. Перед первым push проверить `git status`, `git diff` и отсутствие секретов.

## 2. MariaDB Server

1. Подключиться к `37.230.192.57` read-only.
2. Зафиксировать существующие контейнеры и сети.
3. Согласовать имя compose project, например `nko-linka-db`.
4. Создать MariaDB Docker Compose только после явного подтверждения, потому что это production-изменение на сервере.
5. Настроить backup script и проверить restore path без destructive restore.

## 3. Object Storage

1. Bucket `nko-linka-ru-uploads` создан.
2. WordPress uploads подключены как RW Object Storage mount к `/var/www/html/wp-content/uploads`.
3. WordPress S3 plugin не используется: mount проще и не требует хранения S3 credentials в WordPress.

## 4. WordPress Runtime Secrets

Required runtime secrets:

- `WORDPRESS_DB_HOST`.
- `WORDPRESS_DB_NAME`.
- `WORDPRESS_DB_USER`.
- `WORDPRESS_DB_PASSWORD`.
- `WORDPRESS_AUTH_KEY`.
- `WORDPRESS_SECURE_AUTH_KEY`.
- `WORDPRESS_LOGGED_IN_KEY`.
- `WORDPRESS_NONCE_KEY`.
- `WORDPRESS_AUTH_SALT`.
- `WORDPRESS_SECURE_AUTH_SALT`.
- `WORDPRESS_LOGGED_IN_SALT`.
- `WORDPRESS_NONCE_SALT`.
- Object Storage access key and secret key.
- WordPress admin password for initial setup/import.

## 5. YC Serverless Container

1. Runtime service account `nko-linka-runtime` создан.
2. Serverless Container `nko-linka-wordpress` создан и использует image `cr.yandex/crpu3icktgossftl7l2r/nko-linka-wordpress:main`.
3. Runtime secrets подключены из Lockbox secret `nko-linka-wordpress-runtime`.
4. API Gateway `nko-linka-wordpress` создан как публичный proxy к контейнеру.
5. Domain `nkolinka.ru` is attached to API Gateway with certificate `fpqfb4bbj47ppclem208`.
6. Verified `/healthz.php`, `/`, `/programs/`, `/reports/`, and `/wp-login.php` on generated container/gateway URLs.
7. Public `https://nkolinka.ru`, `/programs/`, `/wp-login.php`, and `/healthz.php` verified after DNS/API Gateway propagation.
8. Runtime hotfix revision `bbais7f2cudasnda2pit` fixes Apache `/wp-admin` redirect from leaking internal port `8080`.
9. Runtime hotfix revision `bba9gv4igtssask5na1g` enables Apache rewrite fallback and WordPress pretty permalinks.
10. Revision `bbauqmvqv5grsaocecct` is active and includes Apache fixes, Postbox SMTP configuration, writable admin updater temp directory, CSS cache version `0.1.1`, YooKassa receipt sending disabled, and the thank-you return URL.

## 6. Content Import

1. WordPress installed.
2. `Linka NKO` theme activated.
3. Base pages from `content/pages/` created in WordPress.
4. Primary menu created without book link and with a voluntary donation page link.
5. Primary menu assignment restored after `theme_mods_linka-nko` was overwritten by WordPress.
6. Legal PDF documents still need to be uploaded and linked on the documents page.
7. Full article migration still needs source text/images from the old site.
8. Public donation pages are published with the active YooKassa donation form.

## 7. Donations

1. Page `https://nkolinka.ru/donate/` is published.
2. Page `https://nkolinka.ru/donation-offer/` is published.
3. Page `https://nkolinka.ru/donation-thanks/` is published as the YooKassa return page.
4. Privacy policy is updated for donation payment processing and YooKassa data transfer.
5. Home page contains a `Пожертвовать` CTA.
6. The donation page has active YooKassa redirect integration through runtime secrets.
7. The donation page states that donations are not payment for goods, services, courses, consultations or software.
8. Active Lockbox version `e6qqa7iot110s39g9n58` sets `YOOKASSA_SEND_RECEIPT=false` and `YOOKASSA_RETURN_URL=https://nkolinka.ru/donation-thanks/`.
9. YooKassa receipts are disabled for contract `НЭК.451387.01`; test payment creation without `receipt` returned a YooMoney checkout redirect.
10. Completed donations are not currently written into custom WordPress database tables; YooKassa remains the payment source of truth.
11. Recurring donation code is prepared behind `YOOKASSA_RECURRING_ENABLED=false`; production activation still requires YooKassa autopayments to be enabled for the shop.

## 8. WordPress Users

1. `ivan` / `ivan@aacidov.ru` is an administrator.
2. `darya.garbuzova` / `daria300103@gmail.com` is an editor.
3. `ekaterina.karpova` / `karpova260102@gmail.com` is an editor.
4. Initial passwords are stored in Lockbox secret `nko-linka-wordpress-users`.
5. Password reset/access emails were sent through WordPress/Postbox on 2026-07-05.

## 9. Postbox

1. Domain identity `nkolinka.ru` is verified for sending.
2. DKIM selector is `pb20260705`.
3. SPF and DMARC records are present in YC DNS.
4. SMTP/API secrets are stored in Lockbox secret `nko-linka-postbox`.
5. Temporary local Postbox key files were removed.

## 10. Final Verification

1. `curl -I https://nkolinka.ru` returns `200` or expected redirects.
2. `https://nkolinka.ru/wp-login.php` is reachable.
3. Uploaded media opens from Object Storage.
4. Mobile layout is usable.
5. Documents and reports pages are present.
6. No analytics scripts are present in v1.
7. Donation pages contain only voluntary donation content, no goods/services payment, and no prices for services.
8. `https://nkolinka.ru/wp-admin` redirects to `https://nkolinka.ru/wp-admin/` and then to login without `:8080`.
