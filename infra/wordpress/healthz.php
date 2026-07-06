<?php
$tmpDir = ini_get('sys_temp_dir') ?: sys_get_temp_dir();

if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "temp directory is not writable\n";
    exit;
}

http_response_code(200);
header('Content-Type: text/plain; charset=UTF-8');
echo "ok\n";
