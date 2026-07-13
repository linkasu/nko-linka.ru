<?php

final class WP_Error
{
    public function __construct(private string $code, private string $message = '')
    {
    }

    public function get_error_code(): string
    {
        return $this->code;
    }
}

function add_action(): void
{
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function wp_remote_request(string $url, array $args)
{
    $curl = curl_init($url);
    if ($curl === false) {
        return new WP_Error('curl_init_failed');
    }
    $headers = [];
    foreach ($args['headers'] ?? [] as $name => $value) {
        $headers[] = $name . ': ' . $value;
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $args['method'] ?? 'GET',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $args['timeout'] ?? 30,
    ]);
    if (array_key_exists('body', $args) && $args['body'] !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $args['body']);
    }
    $body = curl_exec($curl);
    if ($body === false) {
        $error = curl_error($curl);
        return new WP_Error('curl_failed', $error);
    }
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($status >= 400) {
        fwrite(STDERR, $body . PHP_EOL);
    }
    return ['response' => ['code' => $status], 'body' => $body];
}

function wp_remote_retrieve_response_code(array $response): int
{
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body(array $response): string
{
    return (string) ($response['body'] ?? '');
}

require dirname(__DIR__) . '/wp-content/mu-plugins/linka-nko-registries.php';

$key = '_health/plugin-s3-access-check.txt';
$expected = "registry plugin storage access check\n";
$put = linka_nko_registry_s3_request('PUT', $key, $expected, 'text/plain; charset=utf-8');
if (is_wp_error($put)) {
    fwrite(STDERR, 'S3 PUT failed: ' . $put->get_error_code() . PHP_EOL);
    exit(1);
}
$actual = linka_nko_registry_s3_request('GET', $key);
if (is_wp_error($actual) || $actual !== $expected) {
    fwrite(STDERR, 'S3 GET failed or returned unexpected content' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Registry S3 test passed\n");
