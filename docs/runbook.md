# Runbook

## 1. Local State

```bash
git status --short
```

## 2. YC Read-Only Inventory

Use active `yc` profile only after confirming it points to the intended cloud/folder.

```bash
yc config list
yc resource-manager folder get b1gn4stour811vgtjude
yc serverless container list --folder-id b1gn4stour811vgtjude
yc container registry list --folder-id b1gn4stour811vgtjude
yc certificate-manager certificate list --folder-id b1gn4stour811vgtjude
yc storage bucket list --folder-id b1gn4stour811vgtjude
yc lockbox secret list --folder-id b1gn4stour811vgtjude
```

## 3. Server Read-Only Inventory

SSH alias exists, but must be discovered safely.

```bash
ssh -G <alias>
ssh <alias> 'hostname && docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"'
ssh <alias> 'docker volume ls'
```

Do not stop, remove or recreate anything without explicit confirmation.

## 4. Before Production Change

1. Record current YC container revision.
2. Back up MariaDB.
3. Back up Object Storage/uploads if present.
4. Dry-run migration/deploy.
5. Apply only intended changes.

## 5. Verification

```bash
curl -I https://nkolinka.ru
curl -I https://nkolinka.ru/wp-login.php
```

Also verify:

- WordPress home URL and site URL.
- Admin login.
- Admin updater prerequisites: `FS_METHOD=direct`, writable temp directory `/tmp/wordpress`, PHP/container timeouts long enough for update requests.
- Media upload and public media URL.
- If donation pages are enabled: no goods, services, prices, tariffs or payment secrets in repository; active YooKassa form is allowed only after approval and only through Lockbox/runtime env bindings.
- Documents page links to PDFs.
- Mobile rendering.
- The direct Serverless Container URL returns `403` without IAM, while the public domain works through API Gateway.
- Public requests to `/internal`, `/internal/run-recurring-donations`, and `/internal/*` return the Gateway's static `404`.
- YC timer `nko-linka-recurring-donations` is active and invokes `/internal/run-recurring-donations` directly with the runtime service account every 15 minutes.
- YC timer `nko-linka-donation-total-sync` is active and invokes `/internal/sync-donations` hourly at minute 7; the response must report `payments_synced=true`.
- The recurring runner returns HTTP `200`; before a manual invocation, confirm there are no unexpected due or `charging` subscriptions.

## 6. Secrets

- Runtime WordPress secrets: Lockbox `nko-linka-wordpress-runtime`.
- Postbox SMTP/API secrets: Lockbox `nko-linka-postbox`.
- YooKassa payment secrets: Lockbox `nko-linka-yookassa`; bind only required `YOOKASSA_*` runtime variables.
- Initial WordPress user passwords: Lockbox `nko-linka-wordpress-users`.
- Do not print Lockbox payloads in logs or commit exported payload files.
