<?php

function linka_nko_readiness_environment(): array
{
    $environment = [];
    foreach (['WORDPRESS_DB_HOST', 'WORDPRESS_DB_NAME', 'WORDPRESS_DB_USER', 'WORDPRESS_DB_PASSWORD', 'LINKA_NKO_READINESS_DB_TIMEOUT_SECONDS'] as $name) {
        $value = getenv($name);
        $environment[$name] = $value === false ? '' : (string) $value;
    }

    return $environment;
}

function linka_nko_readiness_parse_db_host(string $value): ?array
{
    $value = trim($value);
    if ($value === '' || preg_match('/[\s\/]/', $value)) {
        return null;
    }

    $host = $value;
    $port = 3306;

    if ($value[0] === '[') {
        if (!preg_match('/^\[([0-9a-f:]+)\](?::([0-9]+))?$/i', $value, $matches)) {
            return null;
        }
        $host = $matches[1];
        if (isset($matches[2]) && $matches[2] !== '') {
            $port = (int) $matches[2];
        }
    } elseif (substr_count($value, ':') === 1) {
        [$candidate_host, $candidate_port] = explode(':', $value, 2);
        if ($candidate_host === '' || !ctype_digit($candidate_port)) {
            return null;
        }
        $host = $candidate_host;
        $port = (int) $candidate_port;
    } elseif (substr_count($value, ':') > 1 && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
        return null;
    }

    if ($port < 1 || $port > 65535 || !preg_match('/^[a-z0-9._:-]+$/i', $host)) {
        return null;
    }

    return ['host' => $host, 'port' => $port];
}

function linka_nko_readiness_config(array $environment): ?array
{
    foreach (['WORDPRESS_DB_HOST', 'WORDPRESS_DB_NAME', 'WORDPRESS_DB_USER', 'WORDPRESS_DB_PASSWORD'] as $name) {
        if (!isset($environment[$name]) || trim((string) $environment[$name]) === '') {
            return null;
        }
    }

    $address = linka_nko_readiness_parse_db_host((string) $environment['WORDPRESS_DB_HOST']);
    $raw_timeout = trim((string) ($environment['LINKA_NKO_READINESS_DB_TIMEOUT_SECONDS'] ?? ''));
    $raw_timeout = $raw_timeout === '' ? '2' : $raw_timeout;
    if ($address === null || !ctype_digit($raw_timeout)) {
        return null;
    }

    $timeout = (int) $raw_timeout;
    if ($timeout < 1 || $timeout > 10) {
        return null;
    }

    return [
        'host' => $address['host'],
        'port' => $address['port'],
        'name' => (string) $environment['WORDPRESS_DB_NAME'],
        'user' => (string) $environment['WORDPRESS_DB_USER'],
        'password' => (string) $environment['WORDPRESS_DB_PASSWORD'],
        'timeout' => $timeout,
    ];
}

function linka_nko_database_ready(array $config, ?callable $connector = null): bool
{
    if ($connector !== null) {
        return (bool) $connector($config);
    }

    if (!extension_loaded('mysqli')) {
        return false;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    try {
        $connection = mysqli_init();
        if ($connection === false) {
            return false;
        }

        mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, $config['timeout']);
        $connected = @mysqli_real_connect(
            $connection,
            $config['host'],
            $config['user'],
            $config['password'],
            $config['name'],
            $config['port']
        );
        mysqli_close($connection);

        return $connected;
    } catch (Throwable $error) {
        return false;
    }
}

function linka_nko_readiness_result(bool $ready): array
{
    return [
        'status_code' => $ready ? 200 : 503,
        'body' => ['status' => $ready ? 'ready' : 'not_ready'],
    ];
}

if (!defined('LINKA_NKO_READINESS_LIBRARY_ONLY')) {
    $config = linka_nko_readiness_config(linka_nko_readiness_environment());
    $result = linka_nko_readiness_result($config !== null && linka_nko_database_ready($config));

    http_response_code($result['status_code']);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($result['body'], JSON_UNESCAPED_SLASHES) . "\n";
}
