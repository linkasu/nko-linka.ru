# Safety

## Secrets

Never commit or print secrets:

- WordPress admin password.
- DB host credentials.
- Object Storage access keys.
- YC service account key.
- TLS private keys.
- `wp-config.php` secrets.
- SQL dumps.

## Required Confirmation

Ask the user before:

- Removing or recreating containers, volumes, buckets, certificates or databases.
- Running DB import/reset/drop/truncate.
- Running broad `wp search-replace`.
- Deleting remote files.
- Changing DNS records.
- Enabling active payment forms, payment links, payment requisites or analytics.

## Backups

Before production content migration or update:

- Dump MariaDB.
- Export WordPress content.
- Snapshot uploaded media or Object Storage bucket state.
- Record current container revision.

## Data Protection

- No contact form in v1.
- If forms are added later, update policy, consent text, data retention and spam protection first.
- If analytics is added later, update cookie/privacy notices first.

## Donations

Voluntary donations for statutory nonprofit activity are allowed. Active payment forms, payment links and payment requisites require separate confirmation after YooKassa approval.

Do not add goods, services, courses, consultations, software, prices, tariffs, carts or commercial payment flows without a separate decision.
