# Infrastructure

## Target

```text
User -> nkolinka.ru -> YC API Gateway -> YC Serverless Container -> WordPress
                                      |-> MariaDB on 37.230.192.57
                                      |-> Yandex Object Storage for uploads
                                      |-> YC Certificate Manager for TLS
                                      |-> YC Postbox for outbound email
                                      |-> YooKassa redirect checkout for voluntary donations
```

Current production path:

```text
User -> nkolinka.ru -> API Gateway d5dmjh8ur6ogqs55jbqn -> Serverless Container bba644mi7027h56etnsd -> WordPress
```

## Yandex Cloud

- Folder ID: `b1gn4stour811vgtjude`.
- Container runtime: Yandex Cloud Serverless Containers.
- Serverless Container: `nko-linka-wordpress`, id `bba644mi7027h56etnsd`.
- API Gateway: `nko-linka-wordpress`, id `d5dmjh8ur6ogqs55jbqn`.
- Container image source: GitHub CI builds image and pushes it to Yandex Container Registry.
- TLS: managed certificate in YC Certificate Manager, certificate id `fpqfb4bbj47ppclem208`, status `ISSUED`.
- Outbound email: Yandex Cloud Postbox, domain identity `nkolinka.ru`, DKIM selector `pb20260705`.
- Donations: YooKassa redirect checkout, enabled only when runtime `YOOKASSA_*` secrets are bound.
- Active production revision: `bba024ip5isf0e4bcvqr`, image digest `sha256:ca8fe299de133eabd65d37f910060b5208afb4fdf0026f126cedb8cbc3035e79`.

## WordPress Runtime Requirements

WordPress recommends:

- PHP 8.3+.
- MariaDB 10.6+ or MySQL 8.0+.
- HTTPS.

## Database

- MySQL/MariaDB in Docker on `37.230.192.57`.
- Access currently exposed on `37.230.192.57:3306` for YC Serverless connectivity.
- Credentials must be runtime secrets, never files in this repo.
- Backups are mandatory before content migration and production updates.

## Uploads

Serverless containers do not provide stable local persistent storage. WordPress uploads must use Yandex Object Storage through an S3-compatible WordPress plugin or custom configuration.

## Outbound Email

- Yandex Cloud Postbox identity `nkolinka.ru` is verified for sending.
- Sender address for WordPress service mail: `no-reply@nkolinka.ru`.
- SMTP credentials are stored in Lockbox secret `nko-linka-postbox`.
- WordPress SMTP configuration is implemented in `wp-content/mu-plugins/linka-nko-safety.php` and requires a CI-built image deploy plus runtime env bindings.

## Donations

- Voluntary donation form is implemented in `wp-content/mu-plugins/linka-nko-donations.php`.
- The form collects donation amount, donor full name, donor email, and consent with the donation offer and personal data policy.
- Payment is created server-side through YooKassa API and redirects the donor to YooKassa confirmation URL.
- Required runtime secrets: `YOOKASSA_SHOP_ID`, `YOOKASSA_SECRET_KEY`.
- Optional runtime configuration: `YOOKASSA_RETURN_URL`, `YOOKASSA_SEND_RECEIPT`, `YOOKASSA_VAT_CODE`, `YOOKASSA_PAYMENT_SUBJECT`, `YOOKASSA_TAX_SYSTEM_CODE`.
- YooKassa secrets must be stored in Lockbox and bound to the Serverless Container environment; never commit them.
- Production YooKassa secret: Lockbox `nko-linka-yookassa`, id `e6q8l62gpq6o2hgserti`, version `e6qqa7iot110s39g9n58`.
- `YOOKASSA_SEND_RECEIPT=false` is bound in the active revision; YooKassa confirmed that payments can be accepted without `receipt` after disabling YooKassa receipts for this shop.

## CI/CD

- Do not build Docker image locally or on the server.
- GitHub Actions builds and publishes image.
- Deployment updates YC Serverless Container revision.
- Secrets live in GitHub Actions and YC runtime secrets/Lockbox.
- Current production revision is a CI-built image with Apache canonical redirect fixes, WordPress Postbox SMTP configuration, and the YooKassa donation form baked in.
- WordPress admin-managed updates use direct filesystem writes, a runtime-created writable temp directory `/tmp/wordpress`, and longer container/PHP timeouts than public page requests.

## Open Technical Tasks

- Upload registration PDF files to the public uploads bucket/media path and replace placeholders on the documents page.
