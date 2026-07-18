<?php

require dirname(__DIR__, 2) . '/private-network/secure-files.php';

function linka_release_fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function linka_release_sort_recursive($value)
{
    if (!is_array($value)) {
        return $value;
    }
    if (!array_is_list($value)) {
        ksort($value);
    }
    foreach ($value as $key => $item) {
        $value[$key] = linka_release_sort_recursive($item);
    }
    return $value;
}

function linka_release_shell(string $value): string
{
    return escapeshellarg($value);
}

function linka_release_validate(array $manifest): array
{
    $required = ['version', 'releaseId', 'image', 'stableContainerId', 'candidateContainerId', 'networkId', 'runtimeServiceAccountId', 'gatewayId', 'gatewayServiceAccountId', 'publicHost', 'resources', 'environment', 'secrets', 'mounts'];
    $errors = [];
    foreach ($required as $key) {
        if (!array_key_exists($key, $manifest)) {
            $errors[] = $key . ' is required';
        }
    }
    if ($errors !== []) {
        return $errors;
    }
    if ($manifest['version'] !== 1 || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', (string) $manifest['releaseId'])) {
        $errors[] = 'release version or id is invalid';
    }
    if (!preg_match('#^cr\.yandex/[a-z0-9._/-]+@sha256:[a-f0-9]{64}$#', (string) $manifest['image'])) {
        $errors[] = 'image must use an exact cr.yandex sha256 digest';
    }
    foreach (['stableContainerId', 'candidateContainerId', 'networkId', 'runtimeServiceAccountId', 'gatewayId', 'gatewayServiceAccountId'] as $key) {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', (string) $manifest[$key])) {
            $errors[] = $key . ' is invalid';
        }
    }
    if ($manifest['stableContainerId'] === $manifest['candidateContainerId']) {
        $errors[] = 'stable and candidate containers must be different';
    }
    if (filter_var('https://' . $manifest['publicHost'], FILTER_VALIDATE_URL) === false || str_contains((string) $manifest['publicHost'], '/')) {
        $errors[] = 'publicHost is invalid';
    }

    $resources = is_array($manifest['resources']) ? $manifest['resources'] : [];
    foreach (['memory', 'cores', 'coreFraction', 'executionTimeout', 'concurrency', 'minInstances', 'zoneInstancesLimit', 'zoneRequestsLimit', 'logGroupId', 'minLogLevel', 'metadataOptions'] as $key) {
        if (!array_key_exists($key, $resources)) {
            $errors[] = 'resources.' . $key . ' is required';
        }
    }
    if (isset($resources['memory']) && !preg_match('/^[1-9][0-9]*(MB|GB)$/', (string) $resources['memory'])) {
        $errors[] = 'resources.memory is invalid';
    }
    if (isset($resources['executionTimeout']) && !preg_match('/^[1-9][0-9]*s$/', (string) $resources['executionTimeout'])) {
        $errors[] = 'resources.executionTimeout is invalid';
    }
    foreach (['cores', 'concurrency', 'minInstances', 'zoneInstancesLimit', 'zoneRequestsLimit'] as $key) {
        if (isset($resources[$key]) && (!is_int($resources[$key]) || $resources[$key] < 0)) {
            $errors[] = 'resources.' . $key . ' is invalid';
        }
    }
    if (isset($resources['coreFraction']) && (!is_int($resources['coreFraction']) || $resources['coreFraction'] < 1 || $resources['coreFraction'] > 100)) {
        $errors[] = 'resources.coreFraction is invalid';
    }
    $metadata_options = is_array($resources['metadataOptions'] ?? null) ? $resources['metadataOptions'] : [];
    foreach (['awsV1HttpEndpoint', 'gceHttpEndpoint'] as $key) {
        if (!in_array($metadata_options[$key] ?? '', ['enabled', 'disabled'], true)) {
            $errors[] = 'resources.metadataOptions is incomplete';
        }
    }

    if (!is_array($manifest['environment']) || $manifest['environment'] === []) {
        $errors[] = 'complete non-secret environment is required';
    } else {
        foreach ($manifest['environment'] as $name => $value) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', (string) $name) || !is_string($value) || str_contains($value, "\n") || preg_match('/PASSWORD|SECRET|TOKEN|(^|_)KEY$/', (string) $name)) {
                $errors[] = 'environment contains an invalid or secret-like entry';
            }
        }
    }
    if (!is_array($manifest['secrets']) || $manifest['secrets'] === []) {
        $errors[] = 'complete secret references are required';
    } else {
        foreach ($manifest['secrets'] as $secret) {
            if (!is_array($secret)) {
                $errors[] = 'secret reference is invalid';
                continue;
            }
            foreach (['id', 'versionId', 'key', 'environmentVariable'] as $key) {
                if (!isset($secret[$key]) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', (string) $secret[$key])) {
                    $errors[] = 'secret reference field is invalid';
                }
            }
        }
    }
    if (!is_array($manifest['mounts'])) {
        $errors[] = 'mounts must be an array';
    } else {
        foreach ($manifest['mounts'] as $mount) {
            if (!is_array($mount)
                || ($mount['type'] ?? '') !== 'object-storage'
                || !preg_match('#^/[A-Za-z0-9._/-]+$#', (string) ($mount['mountPoint'] ?? ''))
                || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', (string) ($mount['bucket'] ?? ''))
                || !in_array($mount['mode'] ?? '', ['ro', 'rw'], true)) {
                $errors[] = 'mount reference is invalid';
            }
        }
    }
    if (str_contains(json_encode($manifest), '__REQUIRED')) {
        $errors[] = 'manifest contains unresolved placeholders';
    }

    return array_values(array_unique($errors));
}

function linka_release_gateway_spec(string $container_id, string $service_account_id, string $release_id): string
{
    return "openapi: 3.0.0\ninfo:\n  title: nko-linka-wordpress\n  version: " . $release_id . "\npaths:\n  /internal:\n    x-yc-apigateway-any-method:\n      x-yc-apigateway-integration:\n        type: dummy\n        http_code: 404\n        content:\n          application/json: '{\"error\":\"not found\"}'\n  /internal/{proxy+}:\n    parameters:\n      - name: proxy\n        in: path\n        required: true\n        schema:\n          type: string\n    x-yc-apigateway-any-method:\n      x-yc-apigateway-integration:\n        type: dummy\n        http_code: 404\n        content:\n          application/json: '{\"error\":\"not found\"}'\n  /:\n    x-yc-apigateway-any-method:\n      x-yc-apigateway-integration:\n        type: serverless_containers\n        container_id: " . $container_id . "\n        service_account_id: " . $service_account_id . "\n  /{proxy+}:\n    parameters:\n      - name: proxy\n        in: path\n        required: true\n        schema:\n          type: string\n    x-yc-apigateway-any-method:\n      x-yc-apigateway-integration:\n        type: serverless_containers\n        container_id: " . $container_id . "\n        service_account_id: " . $service_account_id . "\n";
}

if ($argc !== 3 || !linka_secure_regular_file($argv[1], true)) {
    fwrite(STDERR, "Usage: php render-release.php RELEASE_MANIFEST_JSON OUTPUT_PARENT\nManifest must be a regular 0600 file.\n");
    exit(2);
}
$manifest_source = file_get_contents($argv[1]);
$manifest = is_string($manifest_source) ? json_decode($manifest_source, true) : null;
if (!is_array($manifest)) {
    linka_release_fail('Release manifest is invalid JSON');
}
$errors = linka_release_validate($manifest);
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

$canonical_manifest = json_encode(linka_release_sort_recursive($manifest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$release_sha = hash('sha256', $canonical_manifest);
$output = linka_secure_random_output_directory($argv[2], 'linka-release-' . $manifest['releaseId']);
if ($output === null) {
    linka_release_fail('Could not create secure release directory');
}

$resources = $manifest['resources'];
$continuation = ' ' . chr(92);
$deploy = [
    '#!/usr/bin/env bash',
    'set -Eeuo pipefail',
    '[ "${LINKA_RELEASE_GO:-}" = GO ] || { echo "LINKA_RELEASE_GO=GO is required" >&2; exit 1; }',
    '[ "${EXPECTED_RELEASE_SHA:-}" = ' . linka_release_shell($release_sha) . ' ] || { echo "release sha mismatch" >&2; exit 1; }',
    'cd "$(dirname "$0")"',
    'sha256sum --check manifest.sha256',
    'candidate_tmp="candidate-revision.json.tmp"',
    'yc serverless container revision deploy \
  --container-id ' . linka_release_shell($manifest['candidateContainerId']) . ' \
  --image ' . linka_release_shell($manifest['image']) . ' \
  --memory ' . linka_release_shell($resources['memory']) . ' \
  --cores ' . (int) $resources['cores'] . ' \
  --core-fraction ' . (int) $resources['coreFraction'] . ' \
  --execution-timeout ' . linka_release_shell($resources['executionTimeout']) . ' \
  --concurrency ' . (int) $resources['concurrency'] . ' \
  --min-instances ' . (int) $resources['minInstances'] . ' \
  --zone-instances-limit ' . (int) $resources['zoneInstancesLimit'] . ' \
  --zone-requests-limit ' . (int) $resources['zoneRequestsLimit'] . ' \
  --service-account-id ' . linka_release_shell($manifest['runtimeServiceAccountId']) . ' \
  --network-id ' . linka_release_shell($manifest['networkId']) . ' \
  --runtime http \
  --log-group-id ' . linka_release_shell($resources['logGroupId']) . ' \
  --min-log-level ' . linka_release_shell($resources['minLogLevel']) . $continuation,
];
$deploy[] = '  --metadata-options ' . linka_release_shell('aws-v1-http-endpoint=' . $resources['metadataOptions']['awsV1HttpEndpoint'] . ',gce-http-endpoint=' . $resources['metadataOptions']['gceHttpEndpoint']) . $continuation;
foreach ($manifest['environment'] as $name => $value) {
    $deploy[] = '  --environment ' . linka_release_shell($name . '=' . $value) . $continuation;
}
foreach ($manifest['secrets'] as $secret) {
    $deploy[] = '  --secret ' . linka_release_shell('id=' . $secret['id'] . ',version-id=' . $secret['versionId'] . ',key=' . $secret['key'] . ',environment-variable=' . $secret['environmentVariable']) . $continuation;
}
foreach ($manifest['mounts'] as $mount) {
    $deploy[] = '  --mount ' . linka_release_shell('type=' . $mount['type'] . ',mount-point=' . $mount['mountPoint'] . ',bucket=' . $mount['bucket'] . ',mode=' . $mount['mode']) . $continuation;
}
$deploy[] = '  --format json > "$candidate_tmp"';
$deploy[] = 'grep -F -- ' . linka_release_shell($manifest['image']) . ' "$candidate_tmp" >/dev/null';
$deploy[] = 'mv -f "$candidate_tmp" candidate-revision.json';

$candidate_url = 'https://' . $manifest['candidateContainerId'] . '.containers.yandexcloud.net';
$readiness = "#!/usr/bin/env bash\nset -Eeuo pipefail\ncd \"\$(dirname \"\$0\")\"\nsha256sum --check manifest.sha256\ntest -f candidate-revision.json\ngrep -F -- " . linka_release_shell($manifest['image']) . " candidate-revision.json >/dev/null\niam_token=\"\$(yc iam create-token)\"\nhealth=\"\$(curl --fail --silent --show-error -H \"Authorization: Bearer \$iam_token\" " . linka_release_shell($candidate_url . '/healthz.php') . ")\"\n[ \"\$health\" = ok ]\nready=\"\$(curl --fail --silent --show-error -H \"Authorization: Bearer \$iam_token\" " . linka_release_shell($candidate_url . '/readyz.php') . ")\"\n[ \"\$ready\" = '{\"status\":\"ready\"}' ]\nhome_tmp=\"\$(mktemp)\"\ntrap 'rm -f \"\$home_tmp\"' EXIT\ncurl --fail --silent --show-error -H \"Authorization: Bearer \$iam_token\" " . linka_release_shell($candidate_url . '/') . " > \"\$home_tmp\"\nif grep -Eiq 'mc\\.yandex\\.ru|metrika/tag\\.js|googletagmanager|google-analytics|webvisor[[:space:]]*:[[:space:]]*true' \"\$home_tmp\"; then exit 1; fi\nprintf '%s\\n' " . linka_release_shell($release_sha) . " > .candidate-ready.tmp\nmv -f .candidate-ready.tmp .candidate-ready\n";

$guard = '[ "${LINKA_RELEASE_GO:-}" = GO ] && [ "${EXPECTED_RELEASE_SHA:-}" = ' . linka_release_shell($release_sha) . ' ] || { echo "GO and matching release sha are required" >&2; exit 1; }';
$rollback_command = 'yc serverless api-gateway update --id ' . linka_release_shell($manifest['gatewayId']) . ' --spec gateway-stable.yaml';
$switch = "#!/usr/bin/env bash\nset -Eeuo pipefail\n{$guard}\ncd \"\$(dirname \"\$0\")\"\nsha256sum --check manifest.sha256\n[ \"\$(cat .candidate-ready)\" = " . linka_release_shell($release_sha) . " ]\nready_tmp=\"\$(mktemp)\"\ntrap 'rm -f \"\$ready_tmp\"' EXIT\nyc serverless api-gateway update --id " . linka_release_shell($manifest['gatewayId']) . " --spec gateway-candidate.yaml\nfor attempt in {1..12}; do\n  if [ \"\$(curl --silent --show-error --output \"\$ready_tmp\" --write-out '%{http_code}' " . linka_release_shell('https://' . $manifest['publicHost'] . '/readyz.php') . ")\" = 200 ] && [ \"\$(cat \"\$ready_tmp\")\" = '{\"status\":\"ready\"}' ]; then exit 0; fi\n  sleep 5\ndone\n{$rollback_command}\nexit 1\n";
$rollback = "#!/usr/bin/env bash\nset -Eeuo pipefail\n{$guard}\ncd \"\$(dirname \"\$0\")\"\nsha256sum --check manifest.sha256\n{$rollback_command}\ncurl --fail --silent --show-error " . linka_release_shell('https://' . $manifest['publicHost'] . '/healthz.php') . "\n";

$files = [
    'manifest.lock.json' => $canonical_manifest,
    'manifest.sha256' => $release_sha . "  manifest.lock.json\n",
    'gateway-stable.yaml' => linka_release_gateway_spec($manifest['stableContainerId'], $manifest['gatewayServiceAccountId'], $manifest['releaseId'] . '-stable'),
    'gateway-candidate.yaml' => linka_release_gateway_spec($manifest['candidateContainerId'], $manifest['gatewayServiceAccountId'], $manifest['releaseId'] . '-candidate'),
    'deploy-candidate.sh' => implode("\n", $deploy) . "\n",
    'candidate-readiness.sh' => $readiness,
    'switch-traffic.sh' => $switch,
    'rollback.sh' => $rollback,
];
foreach ($files as $name => $content) {
    if (!linka_secure_atomic_write($output, $name, $content)) {
        linka_release_fail('Could not write release bundle');
    }
}

fwrite(STDOUT, $output . PHP_EOL);
