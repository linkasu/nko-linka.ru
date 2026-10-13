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

final class RegistryInternalAuthJsonResponse extends RuntimeException
{
    public function __construct(public array $payload, public int $status)
    {
        parent::__construct('JSON response');
    }
}

function wp_send_json(array $payload, int $status): void
{
    throw new RegistryInternalAuthJsonResponse($payload, $status);
}

function wp_parse_url(string $url, int $component)
{
    return parse_url($url, $component);
}

final class WP_Role
{
    public function __construct(public array $capabilities = [])
    {
    }

    public function has_cap(string $capability): bool
    {
        return !empty($this->capabilities[$capability]);
    }

    public function add_cap(string $capability): void
    {
        $this->capabilities[$capability] = true;
    }
}

$GLOBALS['linka_test_roles'] = [
    'administrator' => new WP_Role(['manage_options' => true]),
];

function get_role(string $name): ?WP_Role
{
    return $GLOBALS['linka_test_roles'][$name] ?? null;
}

function add_role(string $name, string $label, array $capabilities): WP_Role
{
    $role = new WP_Role($capabilities);
    $GLOBALS['linka_test_roles'][$name] = $role;
    return $role;
}

function add_action(): void
{
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function sanitize_file_name(string $filename): string
{
    return basename($filename);
}

$plugin = $argv[1] ?? dirname(__DIR__) . '/wp-content/mu-plugins/linka-nko-registries.php';
require $plugin;

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

linka_nko_register_registry_roles();
putenv('LINKA_NKO_INTERNAL_AUTH_MODE=bearer');
putenv('LINKA_NKO_INTERNAL_KEY=' . str_repeat('k', 32));
$_SERVER['REQUEST_URI'] = LINKA_NKO_REGISTRY_IMPORT_PATH;
$_SERVER['REQUEST_METHOD'] = 'POST';
unset($_SERVER['HTTP_AUTHORIZATION']);
try {
    linka_nko_run_internal_registry_import();
    assert_same(true, false, 'Registry import continued without bearer token');
} catch (RegistryInternalAuthJsonResponse $response) {
    assert_same(401, $response->status, 'Registry import bearer rejection status');
    assert_same('unauthorized', $response->payload['error'] ?? '', 'Registry import bearer rejection payload');
}
putenv('LINKA_NKO_INTERNAL_AUTH_MODE');
putenv('LINKA_NKO_INTERNAL_KEY');
unset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
$registry_source = file_get_contents($plugin);
$internal_import_start = is_string($registry_source) ? strpos($registry_source, 'function linka_nko_run_internal_registry_import') : false;
$internal_import_end = $internal_import_start === false || !is_string($registry_source) ? false : strpos($registry_source, 'function linka_nko_store_registry', $internal_import_start);
$internal_import = $internal_import_start === false || $internal_import_end === false ? '' : substr($registry_source, $internal_import_start, $internal_import_end - $internal_import_start);
assert_same(true, strpos($internal_import, 'linka_nko_require_internal_auth') < strpos($internal_import, 'linka_nko_import_registries_from_mail'), 'Registry import authenticates before writes');
assert_same(true, get_role('administrator')->has_cap(LINKA_NKO_REGISTRY_CAPABILITY), 'Administrator registry capability');
assert_same(true, get_role(LINKA_NKO_REGISTRY_ACCOUNTANT_ROLE)->has_cap('read'), 'Accountant read capability');
assert_same(true, get_role(LINKA_NKO_REGISTRY_ACCOUNTANT_ROLE)->has_cap(LINKA_NKO_REGISTRY_CAPABILITY), 'Accountant registry capability');
assert_same(false, get_role(LINKA_NKO_REGISTRY_ACCOUNTANT_ROLE)->has_cap('manage_options'), 'Accountant admin capability');

$csv = implode("\n", [
    'РЕЕСТР ПЛАТЕЖЕЙ ПО ДОГОВОРУ НЭК.451387.01 (1403902)',
    'Дата платежей: 2026-07-12',
    'Идентификатор платежа;Сумма платежа;Валюта платежа;Сумма за вычетом комиссии и НДС;Сумма комиссии без НДС;Описание;НДС с комиссии',
    'payment-1;500.00;RUB;487.80;10.00;Добровольное пожертвование на уставную деятельность АНО Линка;2.20',
    'payment-2;1000.00;RUB;980.00;20.00;Добровольное пожертвование на уставную деятельность АНО Линка;0.00',
    '',
    'Сумма принятых платежей: 1500.00 RUB',
    'Число платежей: 2',
]);

$parsed = linka_nko_parse_registry_csv('yoomoney-payments-1403902-2026-07-12.csv', $csv);
if (is_wp_error($parsed)) {
    fwrite(STDERR, 'Valid registry was rejected: ' . $parsed->get_error_code() . PHP_EOL);
    exit(1);
}

assert_same('payments', $parsed['type'], 'Registry type');
assert_same('2026-07-12', $parsed['date'], 'Registry date');
assert_same(2, count($parsed['rows']), 'Registry row count');
assert_same(1500.0, $parsed['gross_total'], 'Gross total');
assert_same(1467.8, $parsed['net_total'], 'Net total');
assert_same(30.0, $parsed['commission_total'], 'Commission total');
assert_same(2.2, $parsed['commission_vat_total'], 'Commission VAT total');
assert_same("'=SUM(A1:A2)", linka_nko_registry_safe_csv_value('=SUM(A1:A2)'), 'CSV formula protection');
assert_same("'\t=SUM(A1:A2)", linka_nko_registry_safe_csv_value("\t=SUM(A1:A2)"), 'Whitespace CSV formula protection');

$trusted_headers = "Received: from mail-nwsmtp-mxfront-production-main-91.iva.yp-c.yandex.net (mail-nwsmtp-mxfront-production-main-91.iva.yp-c.yandex.net [IPv6:2a02:6b8::1])\r\n"
    . "\tby postback3a.mail.yandex.net (postfix) with ESMTPS id test\r\n"
    . "Authentication-Results: mail-nwsmtp-mxfront-production-main-91.iva.yp-c.yandex.net; spf=pass (sender permitted) smtp.mail=reports@yoomoney.ru; dkim=pass header.i=@yoomoney.ru\r\n"
    . "Return-Path: reports@yoomoney.ru\r\n";
assert_same(true, linka_nko_registry_authenticated_sender('reports@yoomoney.ru', $trusted_headers), 'Authenticated sender');
$forged_headers = "Authentication-Results: attacker.example; spf=pass smtp.mail=reports@yoomoney.ru; dkim=pass header.i=@yoomoney.ru\r\n"
    . "Return-Path: reports@yoomoney.ru\r\n";
assert_same(false, linka_nko_registry_authenticated_sender('reports@yoomoney.ru', $forged_headers), 'Forged authentication result rejection');
$forged_yandex_headers = "Authentication-Results: mail-forged.yandex.net; spf=pass smtp.mail=reports@yoomoney.ru; dkim=pass header.i=@yoomoney.ru\r\n"
    . "Return-Path: reports@yoomoney.ru\r\n";
assert_same(false, linka_nko_registry_authenticated_sender('reports@yoomoney.ru', $forged_yandex_headers), 'Forged Yandex authentication result rejection');

$boundary = 'registry-test-boundary';
$mime_message = $trusted_headers
    . "From: reports@yoomoney.ru\r\n"
    . "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n"
    . "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nRegistry attached.\r\n"
    . "--{$boundary}\r\nContent-Type: text/csv; name=\"yoomoney-payments-1403902-2026-07-12.csv\"\r\n"
    . "Content-Disposition: attachment; filename=\"yoomoney-payments-1403902-2026-07-12.csv\"\r\n"
    . "Content-Transfer-Encoding: base64\r\n\r\n"
    . chunk_split(base64_encode($csv), 76, "\r\n")
    . "--{$boundary}--\r\n";
$attachments = linka_nko_registry_mail_attachments($mime_message);
assert_same(1, count($attachments), 'MIME attachment count');
assert_same('yoomoney-payments-1403902-2026-07-12.csv', $attachments[0]['filename'], 'MIME attachment filename');
assert_same($csv, $attachments[0]['content'], 'MIME attachment content');

$refund_csv = implode("\n", [
    'РЕЕСТР ВОЗВРАТОВ ПО ДОГОВОРУ НЭК.451387.01 (1403902)',
    'Дата возвратов: 2026-07-13',
    'Идентификатор возврата;Идентификатор платежа;Сумма возврата;Валюта возврата;Время возврата',
    'refund-1;payment-1;500.00;RUB;13.07.2026 10:00:00',
    '',
    'Сумма возвратов: 500.00 RUB',
    'Число возвратов: 1',
]);
$refund = linka_nko_parse_registry_csv('yoomoney-refunds-1403902-2026-07-13.csv', $refund_csv);
if (is_wp_error($refund)) {
    fwrite(STDERR, 'Valid refund registry was rejected: ' . $refund->get_error_code() . PHP_EOL);
    exit(1);
}
assert_same('refunds', $refund['type'], 'Refund registry type');
assert_same(500.0, $refund['gross_total'], 'Refund gross total');

$wrong_contract = str_replace('НЭК.451387.01', 'НЭК.000000.00', $csv);
$invalid = linka_nko_parse_registry_csv('yoomoney-payments-1403902-2026-07-12.csv', $wrong_contract);
assert_same(true, is_wp_error($invalid), 'Wrong contract rejection');
assert_same('registry_identity_mismatch', $invalid->get_error_code(), 'Wrong contract error');

if (isset($argv[2])) {
    $real_content = file_get_contents($argv[2]);
    $real = is_string($real_content) ? linka_nko_parse_registry_csv(basename($argv[2]), $real_content) : new WP_Error('read_failed');
    if (is_wp_error($real)) {
        fwrite(STDERR, 'Real registry was rejected: ' . $real->get_error_code() . PHP_EOL);
        exit(1);
    }
    fwrite(STDOUT, sprintf("Real registry validated: %d rows, %.2f gross\n", count($real['rows']), $real['gross_total']));
}

if (isset($argv[3])) {
    $message = file_get_contents($argv[3]);
    $message_attachments = is_string($message) ? linka_nko_registry_mail_attachments($message) : [];
    $registry_attachments = array_values(array_filter($message_attachments, static fn(array $attachment): bool => str_ends_with($attachment['filename'], '.csv')));
    assert_same(1, count($registry_attachments), 'Real email registry attachment count');
    $email_registry = linka_nko_parse_registry_csv($registry_attachments[0]['filename'], $registry_attachments[0]['content']);
    if (is_wp_error($email_registry)) {
        fwrite(STDERR, 'Real email registry was rejected: ' . $email_registry->get_error_code() . PHP_EOL);
        exit(1);
    }
    fwrite(STDOUT, "Real registry email validated\n");
}

fwrite(STDOUT, "Registry tests passed\n");
