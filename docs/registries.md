# YooKassa Registries

## Purpose

Daily YooKassa payment and refund registries are accounting documents. They may contain payer personal data and must not be publicly accessible.

## Delivery

- YooKassa sends CSV registries to `reestry@nkolinka.ru`.
- The mailbox is hosted by Yandex 360 and read over TLS POP3.
- Runtime mailbox credentials are stored in Lockbox secret `nko-linka-registries`, id `e6qvu8m0hngdansabasu`.
- Timer `nko-linka-yookassa-registry-import`, id `a1sre1nrhlth26uo9fv9`, invokes `/internal/import-registries` daily at `02:30 UTC` (`05:30 MSK`) with three retries.
- The direct Serverless Container endpoint requires IAM; API Gateway blocks public `/internal/*` requests.

The importer accepts only messages from `reports@yoomoney.ru` whose first Yandex `Authentication-Results` trace reports successful SPF and DKIM for `yoomoney.ru`. Processed POP3 UIDLs are retained in a WordPress option so messages are not repeatedly downloaded.

## Storage

- Private versioned Object Storage bucket: `nko-linka-ru-registries`, resource id `e3es183fhvt77tearfm5`.
- Object prefix: `yookassa/YYYY/MM/{payments|refunds}/`.
- Public read and public list access are disabled.
- S3 access credentials are stored in Lockbox secret `nko-linka-registries` and bound to the Serverless Container revision.
- WordPress table `wp_linka_yookassa_registries` stores dates, types, totals, object keys, SHA-256 hashes, and storage state.
- Downloads verify the object SHA-256 before returning data.
- Only one registry per date and type is accepted. A different file for the same date requires manual accounting review rather than automatic replacement.

## WordPress Admin

Administrators and users with the `Бухгалтер` role can use `Реестры` in WordPress admin to:

- review stored daily payment and refund registries;
- download an original YooKassa CSV;
- download one consolidated transaction-level CSV for a selected month.

Only administrators can upload a missing original CSV manually or trigger a mailbox check. The accountant role has only `read` and `view_linka_registries`; it cannot manage WordPress, users, content, plugins, settings, storage, or mail import. The page and download handlers require the dedicated capability and WordPress nonces. There are no public registry URLs or presigned links.

## Verification

1. Confirm the public bucket URL returns `403` without credentials.
2. Confirm public `POST /internal/import-registries` returns API Gateway `404`.
3. Invoke the direct container path with IAM and confirm HTTP `200`.
4. Confirm a new YooKassa registry is listed in WordPress admin and stored in Object Storage.
5. Download the original CSV and compare its SHA-256 with the attachment.
6. Download the monthly CSV and verify the operation count and totals.
7. Check Cloud Logging for importer, S3, PHP, or database errors.

Do not print mailbox, S3, or YooKassa secrets. Do not place registry CSV files in the repository or the public WordPress uploads bucket.
