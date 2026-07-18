<?php

define('LINKA_PRIVATE_NETWORK_LIBRARY_ONLY', true);
require __DIR__ . '/validate-config.php';
require __DIR__ . '/secure-files.php';

if ($argc !== 4 || !in_array($argv[1], ['gateway', 'vps'], true)) {
    fwrite(STDERR, "Usage: php render-config.php <gateway|vps> NETWORK_ENV OUTPUT_PARENT\n");
    exit(2);
}

$role = $argv[1];
$environment_path = $argv[2];
$output_parent = $argv[3];
if (!linka_secure_regular_file($environment_path, true)) {
    fwrite(STDERR, "NETWORK_ENV must be a regular non-symlink file with mode 0600\n");
    exit(1);
}

try {
    $config = linka_private_network_parse_env_file($environment_path);
    $errors = linka_private_network_validate($config);
} catch (RuntimeException $error) {
    fwrite(STDERR, "Configuration validation failed without displaying values\n");
    exit(1);
}
if ($errors !== []) {
    fwrite(STDERR, "Configuration validation failed; run validate-config.php for variable names\n");
    exit(1);
}

$private_key_variable = $role === 'gateway' ? 'GATEWAY_WG_PRIVATE_KEY_FILE' : 'VPS_WG_PRIVATE_KEY_FILE';
if (!linka_secure_regular_file($config[$private_key_variable], true)) {
    fwrite(STDERR, $private_key_variable . " must be a regular non-symlink file with mode 0600\n");
    exit(1);
}
$private_key = @file_get_contents($config[$private_key_variable]);
if (!is_string($private_key) || !preg_match('/^[A-Za-z0-9+\/]{43}=$/', trim($private_key))) {
    fwrite(STDERR, $private_key_variable . " is invalid\n");
    exit(1);
}

$gateway_wg = linka_private_network_ipv4_cidr($config['GATEWAY_WG_ADDRESS_CIDR']);
$vps_wg = linka_private_network_ipv4_cidr($config['VPS_WG_ADDRESS_CIDR']);
$values = array_merge($config, [
    'WG_PRIVATE_KEY' => trim($private_key),
    'GATEWAY_WG_ADDRESS' => $gateway_wg['ip'],
    'VPS_WG_ADDRESS' => $vps_wg['ip'],
]);
$templates = $role === 'gateway'
    ? ['gateway/wg0.conf.template' => 'wg0.conf', 'gateway/firewall.nft.template' => 'firewall.nft', 'gateway/99-linka-forwarding.conf' => '99-linka-forwarding.conf']
    : [
        'vps/wg0.conf.template' => 'wg0.conf',
        'vps/firewall.nft.template' => 'firewall.nft',
        'vps/cutover-firewall.nft.template' => 'cutover-firewall.nft',
        'vps/linka-db-private-proxy.socket.template' => 'linka-db-private-proxy.socket',
        'vps/linka-db-private-proxy.service.template' => 'linka-db-private-proxy.service',
    ];

$output_directory = linka_secure_random_output_directory($output_parent, 'linka-private-' . $role);
if ($output_directory === null) {
    fwrite(STDERR, "Could not create secure random output directory\n");
    exit(1);
}

foreach ($templates as $template => $destination) {
    $content = file_get_contents(__DIR__ . '/' . $template);
    if (!is_string($content)) {
        fwrite(STDERR, "Could not read template\n");
        exit(1);
    }
    foreach ($values as $name => $value) {
        $content = str_replace('{{' . $name . '}}', (string) $value, $content);
    }
    if (preg_match('/\{\{[A-Z0-9_]+\}\}/', $content) || !linka_secure_atomic_write($output_directory, $destination, $content)) {
        fwrite(STDERR, "Could not atomically render configuration\n");
        exit(1);
    }
}

fwrite(STDOUT, $output_directory . PHP_EOL);
