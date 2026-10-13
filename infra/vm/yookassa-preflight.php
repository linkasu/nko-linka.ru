<?php
// Read-only connectivity/authentication probe. Never print credentials or response data.
$shop = getenv('YOOKASSA_SHOP_ID');
$secret = getenv('YOOKASSA_SECRET_KEY');
if (!$shop || !$secret) {
    fwrite(STDERR, "YooKassa credentials unavailable\n");
    exit(1);
}
$context = stream_context_create(['http' => [
    'method' => 'GET',
    'header' => 'Accept: application/json' . "\r\n" . 'Authorization: Basic ' . base64_encode($shop . ':' . $secret) . "\r\n",
    'timeout' => 12,
    'ignore_errors' => true,
]]);
$body = @file_get_contents('https://api.yookassa.ru/v3/me', false, $context);
$status = $http_response_header[0] ?? '';
if ($body === false || !preg_match('~^HTTP/\S+ 200(?: |$)~', $status) || !is_array(json_decode($body, true))) {
    fwrite(STDERR, "YooKassa read-only probe failed\n");
    exit(1);
}
echo "YooKassa GET /v3/me OK\n";
