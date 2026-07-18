# Blue-Green Serverless Release

The final manifest is a complete, non-secret description of one candidate revision and the stable/candidate API Gateway targets. Secret values are never stored; every Lockbox reference includes an exact version ID. The container image must use `cr.yandex/...@sha256:<64 lowercase hex>` and tags are rejected.

1. Copy `release-manifest.example.json` to an ignored `release-manifest.json` with mode `0600`.
2. Populate it from a read-only inventory of the stable revision, including every environment variable, secret reference, mount and resource limit. Secret-like values are rejected from plain environment entries.
3. Set different stable and candidate container IDs. The candidate has no public API Gateway traffic before readiness.
4. Render and inspect the immutable bundle:

If the candidate container does not exist yet, create the empty green target first, record its generated ID in the manifest, and grant only the same reviewed IAM invocation/runtime permissions as the stable target:

```sh
: "${YC_FOLDER_ID:?set folder id}"
: "${CANDIDATE_CONTAINER_NAME:?set candidate name}"
yc serverless container create --folder-id "$YC_FOLDER_ID" --name "$CANDIDATE_CONTAINER_NAME"
yc serverless container get --folder-id "$YC_FOLDER_ID" --name "$CANDIDATE_CONTAINER_NAME"
```

Those are production mutations and remain plan-only until approval.

```sh
chmod 600 infra/yandex/blue-green/release-manifest.json
bundle="$(php infra/yandex/blue-green/render-release.php infra/yandex/blue-green/release-manifest.json /tmp)"
release_sha="$(cut -d ' ' -f 1 "$bundle/manifest.sha256")"
grep -R '__REQUIRED' "$bundle" && exit 1 || true
```

After source tests, reviewed CI image publication and explicit approval, candidate deployment requires both GO and the exact rendered manifest hash:

```sh
LINKA_RELEASE_GO=GO EXPECTED_RELEASE_SHA="$release_sha" bash "$bundle/deploy-candidate.sh"
bash "$bundle/candidate-readiness.sh"
LINKA_RELEASE_GO=GO EXPECTED_RELEASE_SHA="$release_sha" bash "$bundle/switch-traffic.sh"
```

`switch-traffic.sh` switches the API Gateway only after the IAM-only candidate reports liveness and DB readiness. It polls public readiness and automatically restores `gateway-stable.yaml` on failure. Explicit rollback is:

```sh
LINKA_RELEASE_GO=GO EXPECTED_RELEASE_SHA="$release_sha" bash "$bundle/rollback.sh"
```

Do not delete the stable container or its exact image digest until the observation window closes. Commit/push/deploy are GO only when local tests, CI, manifest review, candidate readiness and rollback artifact review all pass.
