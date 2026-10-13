#!/usr/bin/env python3
"""Install pinned WordPress/finance secrets delivered over SSH stdin.

Never print secret values, write them to the source repository, or use shell eval.
"""

import json
import os
import re
import secrets
import stat
import sys
import tempfile


SECRETS = frozenset(
    """WORDPRESS_DB_HOST WORDPRESS_DB_NAME WORDPRESS_DB_USER WORDPRESS_DB_PASSWORD
    WORDPRESS_AUTH_KEY WORDPRESS_SECURE_AUTH_KEY WORDPRESS_LOGGED_IN_KEY WORDPRESS_NONCE_KEY
    WORDPRESS_AUTH_SALT WORDPRESS_SECURE_AUTH_SALT WORDPRESS_LOGGED_IN_SALT WORDPRESS_NONCE_SALT
    POSTBOX_FROM_EMAIL POSTBOX_FROM_NAME POSTBOX_SMTP_HOST POSTBOX_SMTP_PORT
    POSTBOX_SMTP_USERNAME POSTBOX_SMTP_PASSWORD YOOKASSA_SHOP_ID YOOKASSA_SECRET_KEY
    YOOKASSA_RETURN_URL YOOKASSA_SEND_RECEIPT YOOKASSA_RECURRING_ENABLED
    LINKA_NKO_RECURRING_TOKEN REGISTRY_S3_ACCESS_KEY_ID REGISTRY_S3_SECRET_ACCESS_KEY
    REGISTRY_S3_BUCKET REGISTRY_S3_REGION REGISTRY_MAIL_HOST REGISTRY_MAIL_PORT
    REGISTRY_MAIL_USERNAME REGISTRY_MAIL_PASSWORD LINKA_METRICS_DONATION_HMAC_SECRET""".split()
)
ENV_PATH = "/etc/linka/site.env"
TICK_PATH = "/etc/linka/site-internal.curl"


def install(path: str, content: str) -> None:
    fd, temporary = tempfile.mkstemp(prefix=".site-secret-", dir=os.path.dirname(path))
    try:
        os.fchmod(fd, 0o600)
        with os.fdopen(fd, "w", encoding="utf-8") as output:
            output.write(content)
            output.flush()
            os.fsync(output.fileno())
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def main() -> None:
    if os.geteuid() != 0:
        raise SystemExit("root required")
    parent = os.path.dirname(ENV_PATH)
    directory = os.stat(parent, follow_symlinks=False)
    if not stat.S_ISDIR(directory.st_mode) or directory.st_uid != 0 or directory.st_mode & 0o022:
        raise SystemExit("unsafe runtime directory")
    if any(os.path.islink(path) for path in (ENV_PATH, TICK_PATH)):
        raise SystemExit("unsafe runtime file")

    documents = json.load(sys.stdin)
    if not isinstance(documents, list) or len(documents) != 5:
        raise SystemExit("five pinned Lockbox payloads required")
    values: dict[str, str] = {}
    for document in documents:
        for entry in document.get("entries", []):
            key, value = entry.get("key"), entry.get("text_value")
            if key not in SECRETS:
                continue
            if key in values or not isinstance(value, str) or not value or value.strip() != value or any(ord(c) < 32 or ord(c) == 127 for c in value):
                raise SystemExit("invalid pinned Lockbox data")
            values[key] = value
    if set(values) != SECRETS:
        raise SystemExit("incomplete pinned Lockbox payload")
    if values["YOOKASSA_RECURRING_ENABLED"].lower() != "true" or values["WORDPRESS_DB_HOST"] != "10.60.0.10:3306":
        raise SystemExit("billing or private DB configuration differs from active revision")

    key = None
    if os.path.exists(ENV_PATH):
        metadata = os.stat(ENV_PATH, follow_symlinks=False)
        if not stat.S_ISREG(metadata.st_mode) or metadata.st_uid != 0 or stat.S_IMODE(metadata.st_mode) != 0o600:
            raise SystemExit("unsafe previous site environment")
        for line in open(ENV_PATH, encoding="utf-8"):
            if line.startswith("LINKA_NKO_INTERNAL_KEY="):
                key = line.partition("=")[2].strip()
    if key is None:
        key = secrets.token_urlsafe(32)
    if not re.fullmatch(r"[A-Za-z0-9_-]{32,128}", key):
        raise SystemExit("invalid internal runner key")

    values.update(
        LINKA_NKO_READINESS_DB_TIMEOUT_SECONDS="2",
        LINKA_METRICS_FUNDRAISING_URL="https://metrics.nkolinka.ru/internal/fundraising/batches",
        LINKA_METRICS_DONATION_HMAC_KEY_ID="donations-2026-07",
        LINKA_NKO_INTERNAL_AUTH_MODE="bearer",
        LINKA_NKO_INTERNAL_KEY=key,
        LINKA_NKO_FUTURE_ONLY="1",
    )
    install(ENV_PATH, "".join(f"{name}={value}\n" for name, value in sorted(values.items())))
    install(TICK_PATH, f'header = "Authorization: Bearer {key}"\n')
    print("pinned WordPress environment installed")


if __name__ == "__main__":
    main()
