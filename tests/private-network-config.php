<?php

define('LINKA_PRIVATE_NETWORK_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/infra/private-network/validate-config.php';

function private_network_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$public_key = str_repeat('A', 43) . '=';
$config = [
    'YC_FOLDER_ID' => 'test-folder',
    'YC_ZONE' => 'test-zone-a',
    'YC_NETWORK_NAME' => 'linka-private',
    'YC_PRIVATE_SUBNET_NAME' => 'linka-private-a',
    'YC_PRIVATE_SUBNET_CIDR' => '10.80.0.0/24',
    'YC_ROUTE_TABLE_NAME' => 'linka-private-routes',
    'YC_GATEWAY_VM_NAME' => 'linka-wg-gateway',
    'YC_GATEWAY_ADDRESS_NAME' => 'linka-wg-gateway-ip',
    'YC_GATEWAY_SECURITY_GROUP_NAME' => 'linka-wg-gateway',
    'YC_GATEWAY_PRIVATE_IP' => '10.80.0.10',
    'YC_GATEWAY_PUBLIC_IP' => '192.0.2.10',
    'YC_GATEWAY_VPC_INTERFACE' => 'eth0',
    'YC_GATEWAY_PUBLIC_INTERFACE' => 'eth0',
    'YC_GATEWAY_SSH_PUBLIC_KEY_FILE' => '/tmp/id.pub',
    'OPERATOR_SSH_CIDR' => '198.51.100.10/32',
    'VPS_PUBLIC_IP' => '192.0.2.20',
    'VPS_PUBLIC_INTERFACE' => 'eth0',
    'GATEWAY_WG_ADDRESS_CIDR' => '10.90.0.1/30',
    'VPS_WG_ADDRESS_CIDR' => '10.90.0.2/30',
    'GATEWAY_WG_PUBLIC_KEY' => $public_key,
    'VPS_WG_PUBLIC_KEY' => $public_key,
    'GATEWAY_WG_PRIVATE_KEY_FILE' => '/etc/wireguard/gateway.key',
    'VPS_WG_PRIVATE_KEY_FILE' => '/etc/wireguard/vps.key',
    'WG_INTERFACE' => 'wg0',
    'WIREGUARD_PORT' => '51820',
    'MARIADB_BIND_ADDRESS' => '10.90.0.2',
    'MARIADB_PORT' => '3306',
    'VPS_PRIVATE_PROXY_PORT' => '13306',
];

private_network_assert(linka_private_network_validate($config) === [], 'Valid private-network config was rejected');
private_network_assert(linka_private_network_cidr_contains('10.80.0.0/24', '10.80.0.10'), 'CIDR membership failed');

$public_bind = array_merge($config, ['MARIADB_BIND_ADDRESS' => '0.0.0.0']);
private_network_assert(linka_private_network_validate($public_bind) !== [], 'Wildcard MariaDB bind was accepted');
$non_private_bind = array_merge($config, ['VPS_WG_ADDRESS_CIDR' => '192.0.2.2/30', 'MARIADB_BIND_ADDRESS' => '192.0.2.2']);
private_network_assert(linka_private_network_validate($non_private_bind) !== [], 'Non-private MariaDB bind was accepted');
$unsafe_value = array_merge($config, ['YC_NETWORK_NAME' => 'safe;touch/tmp/pwned']);
private_network_assert(linka_private_network_validate($unsafe_value) !== [], 'Shell-unsafe config value was accepted');
$bootstrap = array_merge($config, [
    'YC_GATEWAY_PUBLIC_IP' => '__REQUIRED_AFTER_PROVISIONING__',
    'GATEWAY_WG_PUBLIC_KEY' => '__REQUIRED_AFTER_PROVISIONING__',
    'VPS_WG_PUBLIC_KEY' => '__REQUIRED_AFTER_PROVISIONING__',
]);
private_network_assert(linka_private_network_validate($bootstrap, false, 'bootstrap') === [], 'Bootstrap validator required generated IPs or keys');
private_network_assert(linka_private_network_validate($bootstrap, false, 'final') !== [], 'Final validator accepted missing generated IPs or keys');
$overlap = array_merge($config, ['GATEWAY_WG_ADDRESS_CIDR' => '10.80.0.1/24', 'VPS_WG_ADDRESS_CIDR' => '10.80.0.2/24', 'MARIADB_BIND_ADDRESS' => '10.80.0.2']);
private_network_assert(linka_private_network_validate($overlap) !== [], 'Overlapping WireGuard subnet was accepted');

$mariadb = [
    'MARIADB_BIND_ADDRESS' => '10.90.0.2',
    'MARIADB_ROOT_PASSWORD' => 'synthetic-test-value',
    'WORDPRESS_DB_NAME' => 'wordpress',
    'WORDPRESS_DB_USER' => 'wordpress',
    'WORDPRESS_DB_PASSWORD' => 'synthetic-test-value',
];
private_network_assert(linka_private_network_validate_mariadb($config, $mariadb) === [], 'Matching MariaDB config was rejected');
private_network_assert(linka_private_network_validate_mariadb($config, array_merge($mariadb, ['MARIADB_BIND_ADDRESS' => '10.90.0.1'])) !== [], 'Mismatched MariaDB bind was accepted');

$network_runbook = file_get_contents(dirname(__DIR__) . '/infra/private-network/README.md');
$vps_firewall = file_get_contents(dirname(__DIR__) . '/infra/private-network/vps/firewall.nft.template');
$vps_firewall_service = file_get_contents(dirname(__DIR__) . '/infra/private-network/vps/linka-private-firewall.service');
private_network_assert(is_string($network_runbook) && str_contains($network_runbook, 'direction=ingress,port=3306,protocol=tcp,v4-cidrs=$YC_PRIVATE_SUBNET_CIDR'), 'YC private database security-group ingress is missing');
private_network_assert(is_string($network_runbook) && !str_contains($network_runbook, "\n. infra/private-network/network.env"), 'Runbook sources an env file');
private_network_assert(is_string($vps_firewall) && str_contains($vps_firewall, 'hook forward') && str_contains($vps_firewall, 'tcp dport {{MARIADB_PORT}} drop'), 'Docker forwarding firewall guard is missing');
private_network_assert(is_string($vps_firewall_service) && str_contains($vps_firewall_service, 'Before=docker.service') && str_contains($vps_firewall_service, 'flush table inet linka_vps_db') && str_contains($vps_firewall_service, 'ExecReload='), 'Firewall is not atomically ordered before Docker');
private_network_assert(is_string($network_runbook) && str_contains($network_runbook, 'VPS provider firewall') && str_contains($network_runbook, '--retention-period 720h'), 'External firewall or 30-day log retention instructions are missing');

$render_variables = array_merge(array_keys($config), ['WG_PRIVATE_KEY', 'GATEWAY_WG_ADDRESS', 'VPS_WG_ADDRESS']);
$templates = glob(dirname(__DIR__) . '/infra/private-network/{gateway,vps}/*.template', GLOB_BRACE);
private_network_assert(is_array($templates) && count($templates) === 7, 'Expected private-network templates were not found');
foreach ($templates as $template) {
    $content = file_get_contents($template);
    preg_match_all('/\{\{([A-Z0-9_]+)\}\}/', (string) $content, $matches);
    foreach ($matches[1] as $variable) {
        private_network_assert(in_array($variable, $render_variables, true), 'Unknown render variable: ' . $variable);
    }
}

$temporary_parent = sys_get_temp_dir() . '/linka-private-render-test-' . bin2hex(random_bytes(8));
private_network_assert(mkdir($temporary_parent, 0700), 'Could not create renderer test directory');
$private_key_path = $temporary_parent . '/test.key';
file_put_contents($private_key_path, $public_key);
chmod($private_key_path, 0600);
$render_config = array_merge($config, [
    'GATEWAY_WG_PRIVATE_KEY_FILE' => $private_key_path,
    'VPS_WG_PRIVATE_KEY_FILE' => $private_key_path,
]);
$environment_path = $temporary_parent . '/network.env';
$environment_lines = [];
foreach ($render_config as $name => $value) {
    $environment_lines[] = $name . '=' . $value;
}
file_put_contents($environment_path, implode("\n", $environment_lines) . "\n");
chmod($environment_path, 0600);

$process = proc_open(
    [PHP_BINARY, dirname(__DIR__) . '/infra/private-network/render-config.php', 'gateway', $environment_path, $temporary_parent],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
private_network_assert(is_resource($process), 'Could not start secure renderer');
$rendered_directory = trim(stream_get_contents($pipes[1]));
$renderer_error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
private_network_assert(proc_close($process) === 0, 'Secure renderer failed: ' . $renderer_error);
$real_temporary_parent = realpath($temporary_parent);
private_network_assert(is_string($real_temporary_parent) && $rendered_directory !== $real_temporary_parent && str_starts_with($rendered_directory, $real_temporary_parent . '/linka-private-gateway-'), 'Renderer output directory was predictable or misplaced');
private_network_assert(!is_link($rendered_directory) && ((fileperms($rendered_directory) & 0077) === 0), 'Renderer output directory permissions are unsafe');
foreach (['wg0.conf', 'firewall.nft', '99-linka-forwarding.conf'] as $rendered_file) {
    $rendered_path = $rendered_directory . '/' . $rendered_file;
    private_network_assert(is_file($rendered_path) && !is_link($rendered_path) && ((fileperms($rendered_path) & 0077) === 0), 'Rendered file permissions are unsafe: ' . $rendered_file);
    unlink($rendered_path);
}
rmdir($rendered_directory);
$symlink_environment = $temporary_parent . '/network-link.env';
symlink($environment_path, $symlink_environment);
$symlink_process = proc_open(
    [PHP_BINARY, dirname(__DIR__) . '/infra/private-network/render-config.php', 'gateway', $symlink_environment, $temporary_parent],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $symlink_pipes
);
private_network_assert(is_resource($symlink_process), 'Could not start symlink rejection test');
stream_get_contents($symlink_pipes[1]);
stream_get_contents($symlink_pipes[2]);
fclose($symlink_pipes[1]);
fclose($symlink_pipes[2]);
private_network_assert(proc_close($symlink_process) !== 0, 'Renderer accepted symlink configuration');
unlink($symlink_environment);
unlink($environment_path);
unlink($private_key_path);
rmdir($temporary_parent);

fwrite(STDOUT, "Private-network configuration tests passed\n");
