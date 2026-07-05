# Infrastructure

## Target

```text
User -> nko-linka.ru -> YC Serverless Container -> WordPress
                                      |-> MariaDB on 37.230.192.57
                                      |-> Yandex Object Storage for uploads
                                      |-> YC Certificate Manager for TLS
```

## Yandex Cloud

- Folder ID: `b1gn4stour811vgtjude`.
- Container runtime: Yandex Cloud Serverless Containers.
- Container image source: GitHub CI build artifact in registry.
- TLS: managed certificate in YC Certificate Manager.

## WordPress Runtime Requirements

WordPress recommends:

- PHP 8.3+.
- MariaDB 10.6+ or MySQL 8.0+.
- HTTPS.

## Database

- MySQL/MariaDB in Docker on `37.230.192.57`.
- Access over private or locked-down public network only.
- Credentials must be runtime secrets, never files in this repo.
- Backups are mandatory before content migration and production updates.

## Uploads

Serverless containers do not provide stable local persistent storage. WordPress uploads must use Yandex Object Storage through an S3-compatible WordPress plugin or custom configuration.

## CI/CD

- Do not build Docker image locally or on the server.
- GitHub Actions builds and publishes image.
- Deployment updates YC Serverless Container revision.
- Secrets live in GitHub Actions and YC runtime secrets/Lockbox.

## Open Technical Tasks

- Use GHCR image path `ghcr.io/linkasu/nko-linka.ru` for CI-built WordPress images.
- Confirm existing SSH alias for `37.230.192.57`.
- Confirm current DNS records for `nko-linka.ru`.
- Create Object Storage bucket and access key policy.
- Decide WordPress S3 plugin after compatibility check.
- Create MariaDB container and backup policy on `37.230.192.57`.
