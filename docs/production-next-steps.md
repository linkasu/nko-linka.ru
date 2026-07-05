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

1. Создать bucket для WordPress uploads.
2. Настроить минимальные access keys для WordPress.
3. Настроить CORS/public read policy по выбранной схеме.
4. Выбрать и проверить WordPress S3 plugin.

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

1. Create runtime service account with minimal permissions.
2. Configure container revision using image from `cr.yandex/crpu3icktgossftl7l2r/nko-linka-wordpress`.
3. Configure runtime environment and secrets.
4. Attach domain and certificate.
5. Verify `/healthz.php`, `/wp-admin/`, media uploads and public pages.

## 6. Content Import

1. Install WordPress.
2. Activate `Linka NKO` theme.
3. Create pages from `content/pages/`.
4. Create menu without donations and without book link.
5. Upload legal PDF documents.
6. Import articles without comments.
7. Check that search/menu/page content contains no donation CTA.

## 7. Final Verification

1. `curl -I https://nko-linka.ru` returns `200` or expected redirects.
2. `https://nko-linka.ru/wp-login.php` is reachable.
3. Uploaded media opens from Object Storage.
4. Mobile layout is usable.
5. Documents and reports pages are present.
6. No analytics scripts are present in v1.
7. No donation pages, links or requisites are present.
