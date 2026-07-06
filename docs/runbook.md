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
- No donation links in menu or pages.
- Documents page links to PDFs.
- Mobile rendering.

## 6. Secrets

- Runtime WordPress secrets: Lockbox `nko-linka-wordpress-runtime`.
- Postbox SMTP/API secrets: Lockbox `nko-linka-postbox`.
- Initial WordPress user passwords: Lockbox `nko-linka-wordpress-users`.
- Do not print Lockbox payloads in logs or commit exported payload files.
