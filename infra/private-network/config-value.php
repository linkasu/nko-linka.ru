<?php

define('LINKA_PRIVATE_NETWORK_LIBRARY_ONLY', true);
require __DIR__ . '/validate-config.php';

$arguments = $argv;
array_shift($arguments);
$stage = 'final';
if (in_array($arguments[0] ?? '', ['--stage=bootstrap', '--stage=final'], true)) {
    $stage = substr($arguments[0], strlen('--stage='));
    array_shift($arguments);
}
if (count($arguments) !== 2 || !preg_match('/^[A-Z][A-Z0-9_]*$/', $arguments[1])) {
    fwrite(STDERR, "Usage: php config-value.php [--stage=bootstrap|--stage=final] NETWORK_ENV VARIABLE_NAME\n");
    exit(2);
}

try {
    $config = linka_private_network_parse_env_file($arguments[0]);
    $errors = linka_private_network_validate($config, false, $stage);
} catch (RuntimeException $error) {
    fwrite(STDERR, "Configuration validation failed without displaying values\n");
    exit(1);
}
if ($errors !== [] || !array_key_exists($arguments[1], $config) || linka_private_network_is_placeholder($config[$arguments[1]])) {
    fwrite(STDERR, "Configuration or requested variable is invalid\n");
    exit(1);
}

fwrite(STDOUT, $config[$arguments[1]]);
