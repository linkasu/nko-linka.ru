#!/usr/bin/env python3
"""Install a newly issued YC access key into a root-only rclone config.

The JSON payload arrives on stdin over SSH. Never print or persist it elsewhere.
"""

import json
import os
import re
import stat
import sys
import tempfile


TARGET = "/etc/linka/site-uploads.rclone.conf"


def main() -> None:
    if os.geteuid() != 0:
        raise SystemExit("root is required")
    payload = json.load(sys.stdin)
    access = payload.get("access_key", {}).get("key_id", "")
    secret = payload.get("secret", "")
    if not re.fullmatch(r"[A-Za-z0-9_-]{8,128}", access) or not re.fullmatch(
        r"[A-Za-z0-9/_+=-]{20,256}", secret
    ):
        raise SystemExit("invalid access key response")

    parent = os.path.dirname(TARGET)
    info = os.stat(parent, follow_symlinks=False)
    if not stat.S_ISDIR(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022:
        raise SystemExit("unsafe credential directory")

    fd, temporary = tempfile.mkstemp(prefix=".site-s3-", dir=parent)
    try:
        os.fchmod(fd, 0o600)
        with os.fdopen(fd, "w", encoding="utf-8") as output:
            output.write(
                "[site-uploads]\n"
                "type = s3\nprovider = Other\nenv_auth = false\n"
                f"access_key_id = {access}\nsecret_access_key = {secret}\n"
                "endpoint = https://storage.yandexcloud.net\nregion = ru-central1\n"
            )
            output.flush()
            os.fsync(output.fileno())
        os.replace(temporary, TARGET)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)
    print("uploads S3 key stored")


if __name__ == "__main__":
    main()
