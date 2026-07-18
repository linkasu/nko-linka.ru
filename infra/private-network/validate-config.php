<?php

function linka_private_network_parse_env_file(string $path): array
{
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('cannot read config file');
    }

    $config = [];
    foreach ($lines as $line_number => $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
            throw new RuntimeException('invalid config line ' . ($line_number + 1));
        }
        if (array_key_exists($matches[1], $config)) {
            throw new RuntimeException('duplicate config key ' . $matches[1]);
        }
        $config[$matches[1]] = trim($matches[2]);
    }

    return $config;
}

function linka_private_network_is_placeholder(string $value): bool
{
    return $value === '' || str_starts_with($value, '__REQUIRED');
}

function linka_private_network_is_shell_safe(string $value): bool
{
    return $value !== '' && preg_match('/^[A-Za-z0-9_\.\/:+,=@-]+$/', $value) === 1;
}

function linka_private_network_is_private_ipv4(string $value): bool
{
    if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $numeric = (int) sprintf('%u', ip2long($value));
    $ranges = [
        [(int) sprintf('%u', ip2long('10.0.0.0')), 8],
        [(int) sprintf('%u', ip2long('172.16.0.0')), 12],
        [(int) sprintf('%u', ip2long('192.168.0.0')), 16],
    ];
    foreach ($ranges as [$network, $prefix]) {
        $mask = (0xffffffff << (32 - $prefix)) & 0xffffffff;
        if (($numeric & $mask) === $network) {
            return true;
        }
    }

    return false;
}

function linka_private_network_ipv4_cidr(string $value): ?array
{
    if (!preg_match('/^([^\/]+)\/(\d{1,2})$/', $value, $matches)) {
        return null;
    }
    $ip = filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $prefix = (int) $matches[2];
    if ($ip === false || $prefix < 0 || $prefix > 32) {
        return null;
    }

    $numeric = (int) sprintf('%u', ip2long($ip));
    $mask = $prefix === 0 ? 0 : ((0xffffffff << (32 - $prefix)) & 0xffffffff);

    return ['ip' => $ip, 'prefix' => $prefix, 'network' => $numeric & $mask, 'mask' => $mask];
}

function linka_private_network_cidr_contains(string $cidr, string $ip): bool
{
    $parsed = linka_private_network_ipv4_cidr($cidr);
    $numeric = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false ? false : (int) sprintf('%u', ip2long($ip));

    return $parsed !== null && $numeric !== false && (($numeric & $parsed['mask']) === $parsed['network']);
}

function linka_private_network_validate(array $config, bool $allow_placeholders = false, string $stage = 'final'): array
{
    $required = [
        'YC_FOLDER_ID', 'YC_ZONE', 'YC_NETWORK_NAME', 'YC_PRIVATE_SUBNET_NAME', 'YC_PRIVATE_SUBNET_CIDR',
        'YC_ROUTE_TABLE_NAME', 'YC_GATEWAY_VM_NAME', 'YC_GATEWAY_ADDRESS_NAME', 'YC_GATEWAY_SECURITY_GROUP_NAME',
        'YC_GATEWAY_PRIVATE_IP', 'YC_GATEWAY_PUBLIC_IP', 'YC_GATEWAY_VPC_INTERFACE', 'YC_GATEWAY_PUBLIC_INTERFACE',
        'YC_GATEWAY_SSH_PUBLIC_KEY_FILE', 'OPERATOR_SSH_CIDR', 'VPS_PUBLIC_IP', 'VPS_PUBLIC_INTERFACE',
        'GATEWAY_WG_ADDRESS_CIDR', 'VPS_WG_ADDRESS_CIDR', 'GATEWAY_WG_PUBLIC_KEY', 'VPS_WG_PUBLIC_KEY',
        'GATEWAY_WG_PRIVATE_KEY_FILE', 'VPS_WG_PRIVATE_KEY_FILE', 'WG_INTERFACE', 'WIREGUARD_PORT',
        'MARIADB_BIND_ADDRESS', 'MARIADB_PORT',
        'VPS_PRIVATE_PROXY_PORT',
    ];
    $bootstrap_generated = ['YC_GATEWAY_PUBLIC_IP', 'GATEWAY_WG_PUBLIC_KEY', 'VPS_WG_PUBLIC_KEY'];
    $errors = [];

    foreach ($required as $name) {
        $generated_placeholder = $stage === 'bootstrap' && in_array($name, $bootstrap_generated, true);
        if (!array_key_exists($name, $config) || (!$allow_placeholders && !$generated_placeholder && linka_private_network_is_placeholder((string) $config[$name]))) {
            $errors[] = $name . ' is required';
        }
    }
    if ($errors !== []) {
        return $errors;
    }

    foreach ($config as $name => $value) {
        if ((!$allow_placeholders || !linka_private_network_is_placeholder((string) $value)) && !linka_private_network_is_shell_safe((string) $value)) {
            $errors[] = $name . ' contains unsafe characters';
        }
    }

    $semantic = static function (string $name) use ($config, $allow_placeholders, $stage, $bootstrap_generated): bool {
        return (!$allow_placeholders && !($stage === 'bootstrap' && in_array($name, $bootstrap_generated, true))) || !linka_private_network_is_placeholder((string) $config[$name]);
    };

    foreach (['YC_GATEWAY_PRIVATE_IP', 'YC_GATEWAY_PUBLIC_IP', 'VPS_PUBLIC_IP', 'MARIADB_BIND_ADDRESS'] as $name) {
        if ($semantic($name) && filter_var($config[$name], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $errors[] = $name . ' must be an IPv4 address';
        }
    }
    foreach (['YC_PRIVATE_SUBNET_CIDR', 'OPERATOR_SSH_CIDR', 'GATEWAY_WG_ADDRESS_CIDR', 'VPS_WG_ADDRESS_CIDR'] as $name) {
        if ($semantic($name) && linka_private_network_ipv4_cidr($config[$name]) === null) {
            $errors[] = $name . ' must be an IPv4 CIDR';
        }
    }
    foreach (['YC_GATEWAY_PRIVATE_IP', 'MARIADB_BIND_ADDRESS'] as $name) {
        if ($semantic($name) && !linka_private_network_is_private_ipv4($config[$name])) {
            $errors[] = $name . ' must be an RFC1918 private IPv4 address';
        }
    }
    foreach (['WIREGUARD_PORT', 'MARIADB_PORT', 'VPS_PRIVATE_PROXY_PORT'] as $name) {
        if ($semantic($name) && (!ctype_digit($config[$name]) || (int) $config[$name] < 1 || (int) $config[$name] > 65535)) {
            $errors[] = $name . ' must be a valid port';
        }
    }
    if ($semantic('MARIADB_PORT') && $config['MARIADB_PORT'] !== '3306') {
        $errors[] = 'MARIADB_PORT must match the Compose published port 3306';
    }
    if ($semantic('VPS_PRIVATE_PROXY_PORT') && ((int) $config['VPS_PRIVATE_PROXY_PORT'] < 1024 || $config['VPS_PRIVATE_PROXY_PORT'] === $config['MARIADB_PORT'])) {
        $errors[] = 'VPS_PRIVATE_PROXY_PORT must be a distinct unprivileged port';
    }
    if ($semantic('WG_INTERFACE') && $config['WG_INTERFACE'] !== 'wg0') {
        $errors[] = 'WG_INTERFACE must be wg0 for the generated systemd unit';
    }
    foreach (['YC_FOLDER_ID', 'YC_ZONE', 'YC_NETWORK_NAME', 'YC_PRIVATE_SUBNET_NAME', 'YC_ROUTE_TABLE_NAME', 'YC_GATEWAY_VM_NAME', 'YC_GATEWAY_ADDRESS_NAME', 'YC_GATEWAY_SECURITY_GROUP_NAME', 'YC_GATEWAY_VPC_INTERFACE', 'YC_GATEWAY_PUBLIC_INTERFACE', 'VPS_PUBLIC_INTERFACE', 'WG_INTERFACE'] as $name) {
        if ($semantic($name) && !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $config[$name])) {
            $errors[] = $name . ' is invalid';
        }
    }
    foreach (['GATEWAY_WG_PUBLIC_KEY', 'VPS_WG_PUBLIC_KEY'] as $name) {
        if ($semantic($name) && !preg_match('/^[A-Za-z0-9+\/]{43}=$/', $config[$name])) {
            $errors[] = $name . ' must be a WireGuard public key';
        }
    }
    foreach (['GATEWAY_WG_PRIVATE_KEY_FILE', 'VPS_WG_PRIVATE_KEY_FILE', 'YC_GATEWAY_SSH_PUBLIC_KEY_FILE'] as $name) {
        if ($semantic($name) && (!str_starts_with($config[$name], '/') || str_contains($config[$name], '..') || str_contains($config[$name], '//'))) {
            $errors[] = $name . ' must be an absolute path';
        }
    }

    $gateway_wg = $semantic('GATEWAY_WG_ADDRESS_CIDR') ? linka_private_network_ipv4_cidr($config['GATEWAY_WG_ADDRESS_CIDR']) : null;
    $vps_wg = $semantic('VPS_WG_ADDRESS_CIDR') ? linka_private_network_ipv4_cidr($config['VPS_WG_ADDRESS_CIDR']) : null;
    $yc_subnet = $semantic('YC_PRIVATE_SUBNET_CIDR') ? linka_private_network_ipv4_cidr($config['YC_PRIVATE_SUBNET_CIDR']) : null;

    foreach (['GATEWAY_WG_ADDRESS_CIDR' => $gateway_wg, 'VPS_WG_ADDRESS_CIDR' => $vps_wg, 'YC_PRIVATE_SUBNET_CIDR' => $yc_subnet] as $name => $parsed_cidr) {
        if ($parsed_cidr !== null && !linka_private_network_is_private_ipv4($parsed_cidr['ip'])) {
            $errors[] = $name . ' must use RFC1918 private address space';
        }
    }

    if ($gateway_wg !== null && $vps_wg !== null && ($gateway_wg['prefix'] !== $vps_wg['prefix'] || $gateway_wg['network'] !== $vps_wg['network'] || $gateway_wg['ip'] === $vps_wg['ip'])) {
        $errors[] = 'WireGuard addresses must be distinct members of the same subnet';
    }
    if ($gateway_wg !== null && $yc_subnet !== null && (($gateway_wg['network'] & $yc_subnet['mask']) === $yc_subnet['network'] || ($yc_subnet['network'] & $gateway_wg['mask']) === $gateway_wg['network'])) {
        $errors[] = 'WireGuard and YC private subnets must not overlap';
    }
    if ($yc_subnet !== null && $semantic('YC_GATEWAY_PRIVATE_IP') && !linka_private_network_cidr_contains($config['YC_PRIVATE_SUBNET_CIDR'], $config['YC_GATEWAY_PRIVATE_IP'])) {
        $errors[] = 'YC_GATEWAY_PRIVATE_IP must belong to YC_PRIVATE_SUBNET_CIDR';
    }
    if ($vps_wg !== null && $semantic('MARIADB_BIND_ADDRESS') && $config['MARIADB_BIND_ADDRESS'] !== $vps_wg['ip']) {
        $errors[] = 'MARIADB_BIND_ADDRESS must equal the VPS WireGuard address';
    }
    if ($semantic('MARIADB_BIND_ADDRESS') && in_array($config['MARIADB_BIND_ADDRESS'], ['0.0.0.0', $config['VPS_PUBLIC_IP']], true)) {
        $errors[] = 'MARIADB_BIND_ADDRESS must not be public or wildcard';
    }

    return $errors;
}

function linka_private_network_validate_mariadb(array $network, array $mariadb, bool $allow_placeholders = false): array
{
    $errors = [];
    foreach (['MARIADB_BIND_ADDRESS', 'MARIADB_ROOT_PASSWORD', 'WORDPRESS_DB_NAME', 'WORDPRESS_DB_USER', 'WORDPRESS_DB_PASSWORD'] as $name) {
        if (!array_key_exists($name, $mariadb) || (!$allow_placeholders && linka_private_network_is_placeholder((string) $mariadb[$name]))) {
            $errors[] = 'MariaDB ' . $name . ' is required';
        }
    }
    if ($errors === [] && (!$allow_placeholders || (!linka_private_network_is_placeholder($network['MARIADB_BIND_ADDRESS']) && !linka_private_network_is_placeholder($mariadb['MARIADB_BIND_ADDRESS']))) && $network['MARIADB_BIND_ADDRESS'] !== $mariadb['MARIADB_BIND_ADDRESS']) {
        $errors[] = 'MariaDB bind address does not match private-network config';
    }

    return $errors;
}

if (!defined('LINKA_PRIVATE_NETWORK_LIBRARY_ONLY')) {
    $arguments = $argv;
    array_shift($arguments);
    $allow_placeholders = ($arguments[0] ?? '') === '--check-template';
    if ($allow_placeholders) {
        array_shift($arguments);
    }
    $stage = 'final';
    if (in_array($arguments[0] ?? '', ['--stage=bootstrap', '--stage=final'], true)) {
        $stage = substr($arguments[0], strlen('--stage='));
        array_shift($arguments);
    }
    if (count($arguments) < 1 || count($arguments) > 2) {
        fwrite(STDERR, "Usage: php validate-config.php [--check-template] [--stage=bootstrap|--stage=final] NETWORK_ENV [MARIADB_ENV]\n");
        exit(2);
    }

    try {
        $network = linka_private_network_parse_env_file($arguments[0]);
        $errors = linka_private_network_validate($network, $allow_placeholders, $stage);
        if (isset($arguments[1])) {
            $errors = array_merge($errors, linka_private_network_validate_mariadb($network, linka_private_network_parse_env_file($arguments[1]), $allow_placeholders));
        }
    } catch (RuntimeException $error) {
        fwrite(STDERR, "Configuration validation failed without displaying values\n");
        exit(1);
    }

    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, $error . PHP_EOL);
        }
        exit(1);
    }

    fwrite(STDOUT, "Private-network configuration is valid\n");
}
