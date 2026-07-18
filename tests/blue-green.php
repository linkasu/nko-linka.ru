<?php

function blue_green_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function blue_green_render(array $manifest, string $parent): array
{
    $manifest_path = $parent . '/manifest-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($manifest_path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    chmod($manifest_path, 0600);
    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__) . '/infra/yandex/blue-green/render-release.php', $manifest_path, $parent],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    blue_green_assert(is_resource($process), 'Could not start release renderer');
    $output = trim(stream_get_contents($pipes[1]));
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    unlink($manifest_path);

    return [$status, $output, $error];
}

function blue_green_remove_bundle(string $directory): void
{
    foreach (glob($directory . '/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}

$digest = str_repeat('a', 64);
$manifest = [
    'version' => 1,
    'releaseId' => 'phase0-test',
    'image' => 'cr.yandex/test/repository@sha256:' . $digest,
    'stableContainerId' => 'stable-container',
    'candidateContainerId' => 'candidate-container',
    'networkId' => 'test-network',
    'runtimeServiceAccountId' => 'runtime-sa',
    'gatewayId' => 'gateway-id',
    'gatewayServiceAccountId' => 'gateway-sa',
    'publicHost' => 'nkolinka.test',
    'resources' => [
        'memory' => '1GB',
        'cores' => 1,
        'coreFraction' => 100,
        'executionTimeout' => '300s',
        'concurrency' => 4,
        'minInstances' => 0,
        'zoneInstancesLimit' => 2,
        'zoneRequestsLimit' => 10,
        'logGroupId' => 'log-group',
        'minLogLevel' => 'info',
        'metadataOptions' => [
            'awsV1HttpEndpoint' => 'disabled',
            'gceHttpEndpoint' => 'enabled',
        ],
    ],
    'environment' => [
        'LINKA_NKO_READINESS_DB_TIMEOUT_SECONDS' => '2',
        'WP_ENVIRONMENT_TYPE' => 'production',
    ],
    'secrets' => [[
        'id' => 'secret-id',
        'versionId' => 'secret-version',
        'key' => 'db-host',
        'environmentVariable' => 'WORDPRESS_DB_HOST',
    ]],
    'mounts' => [[
        'type' => 'object-storage',
        'mountPoint' => '/var/www/html/wp-content/uploads',
        'bucket' => 'uploads-bucket',
        'mode' => 'rw',
    ]],
];

$parent = sys_get_temp_dir() . '/linka-blue-green-test-' . bin2hex(random_bytes(8));
blue_green_assert(mkdir($parent, 0700), 'Could not create release test directory');
[$status_a, $bundle_a, $error_a] = blue_green_render($manifest, $parent);
[$status_b, $bundle_b, $error_b] = blue_green_render($manifest, $parent);
blue_green_assert($status_a === 0 && $status_b === 0, 'Release renderer failed: ' . $error_a . $error_b);

$expected_files = ['manifest.lock.json', 'manifest.sha256', 'gateway-stable.yaml', 'gateway-candidate.yaml', 'deploy-candidate.sh', 'candidate-readiness.sh', 'switch-traffic.sh', 'rollback.sh'];
foreach ($expected_files as $file) {
    $path_a = $bundle_a . '/' . $file;
    $path_b = $bundle_b . '/' . $file;
    blue_green_assert(is_file($path_a) && !is_link($path_a) && ((fileperms($path_a) & 0077) === 0), 'Release artifact permissions are unsafe: ' . $file);
    blue_green_assert(file_get_contents($path_a) === file_get_contents($path_b), 'Release rendering is not reproducible: ' . $file);
}

$deploy = file_get_contents($bundle_a . '/deploy-candidate.sh');
$readiness = file_get_contents($bundle_a . '/candidate-readiness.sh');
$switch = file_get_contents($bundle_a . '/switch-traffic.sh');
$rollback = file_get_contents($bundle_a . '/rollback.sh');
blue_green_assert(str_contains($deploy, '@sha256:' . $digest) && !str_contains($deploy, ':latest'), 'Candidate deploy is not exact-sha');
blue_green_assert(str_contains($deploy, 'LINKA_RELEASE_GO') && str_contains($deploy, 'EXPECTED_RELEASE_SHA'), 'Candidate deploy lacks GO/hash guard');
blue_green_assert(str_contains($readiness, 'candidate-revision.json') && str_contains($readiness, '@sha256:' . $digest) && str_contains($readiness, 'Authorization: Bearer') && str_contains($readiness, '/readyz.php') && str_contains($readiness, 'mc\\.yandex\\.ru'), 'Exact-sha IAM candidate readiness or no-analytics gate is missing');
blue_green_assert(str_contains($switch, 'gateway-candidate.yaml') && str_contains($switch, 'gateway-stable.yaml'), 'Traffic switch lacks automatic rollback');
blue_green_assert(str_contains($rollback, 'gateway-stable.yaml'), 'Explicit rollback is missing');

foreach (['deploy-candidate.sh', 'candidate-readiness.sh', 'switch-traffic.sh', 'rollback.sh'] as $script) {
    $lint = proc_open(['bash', '-n', $bundle_a . '/' . $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $lint_pipes);
    blue_green_assert(is_resource($lint), 'Could not lint release script');
    stream_get_contents($lint_pipes[1]);
    $lint_error = stream_get_contents($lint_pipes[2]);
    fclose($lint_pipes[1]);
    fclose($lint_pipes[2]);
    blue_green_assert(proc_close($lint) === 0, 'Generated release script is invalid: ' . $lint_error);
}

$tag_manifest = array_merge($manifest, ['image' => 'cr.yandex/test/repository:latest']);
[$tag_status, $tag_output, $tag_error] = blue_green_render($tag_manifest, $parent);
blue_green_assert($tag_status !== 0 && $tag_output === '', 'Tag-based image was accepted');

blue_green_remove_bundle($bundle_a);
blue_green_remove_bundle($bundle_b);
rmdir($parent);

fwrite(STDOUT, "Blue-green release tests passed\n");
