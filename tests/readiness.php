<?php

define('LINKA_NKO_READINESS_LIBRARY_ONLY', true);
$readiness_source = $argv[1] ?? dirname(__DIR__) . '/infra/wordpress/readyz.php';
require $readiness_source;

function readiness_assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$environment = [
    'WORDPRESS_DB_HOST' => 'database.internal:3307',
    'WORDPRESS_DB_NAME' => 'wordpress',
    'WORDPRESS_DB_USER' => 'wordpress',
    'WORDPRESS_DB_PASSWORD' => 'test-password',
    'LINKA_NKO_READINESS_DB_TIMEOUT_SECONDS' => '3',
];

$config = linka_nko_readiness_config($environment);
readiness_assert_same('database.internal', $config['host'] ?? null, 'Database host parsing failed');
readiness_assert_same(3307, $config['port'] ?? null, 'Database port parsing failed');
readiness_assert_same(3, $config['timeout'] ?? null, 'Database timeout parsing failed');
readiness_assert_same(null, linka_nko_readiness_config(array_merge($environment, ['WORDPRESS_DB_PASSWORD' => ''])), 'Missing database config must fail closed');
readiness_assert_same(null, linka_nko_readiness_config(array_merge($environment, ['LINKA_NKO_READINESS_DB_TIMEOUT_SECONDS' => '30'])), 'Unsafe timeout must fail closed');
readiness_assert_same(['host' => '2001:db8::1', 'port' => 3308], linka_nko_readiness_parse_db_host('[2001:db8::1]:3308'), 'IPv6 database host parsing failed');

$connected = linka_nko_database_ready($config, static function (array $received): bool {
    return $received['name'] === 'wordpress';
});
readiness_assert_same(true, $connected, 'Successful database readiness failed');
readiness_assert_same(false, linka_nko_database_ready($config, static fn(): bool => false), 'Database failure must report not ready');

$failure = linka_nko_readiness_result(false);
readiness_assert_same(503, $failure['status_code'], 'Readiness failure status must be 503');
readiness_assert_same(['status' => 'not_ready'], $failure['body'], 'Readiness failure must not disclose diagnostics');
readiness_assert_same(false, str_contains(json_encode($failure), 'password'), 'Readiness response leaked configuration');

fwrite(STDOUT, "Readiness tests passed\n");
