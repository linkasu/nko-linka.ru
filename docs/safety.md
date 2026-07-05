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
- Enabling donations or analytics.

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

Any donation flow is out of scope until a separate legal and accounting decision is made.
