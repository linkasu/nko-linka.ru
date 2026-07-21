<?php

define('ABSPATH', __DIR__ . '/');

function add_action(): void
{
}

function add_shortcode(): void
{
}

function wp_salt($scheme = 'auth'): string
{
    return 'synthetic-test-salt-' . $scheme;
}

function sanitize_text_field($value): string
{
    return (string) $value;
}

function wp_unslash($value)
{
    return $value;
}

function wp_json_encode($value, $flags = 0)
{
    return json_encode($value, $flags);
}

require dirname(__DIR__) . '/wp-content/mu-plugins/linka-nko-donations.php';

function donations_security_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$token = str_repeat('a', 48);
$encrypted = linka_nko_encrypt_subscription_token($token);
donations_security_assert(is_string($encrypted) && $encrypted !== '' && !str_contains($encrypted, $token), 'Management token was not encrypted');
donations_security_assert(linka_nko_decrypt_subscription_token($encrypted) === $token, 'Management token could not be decrypted for retry');
donations_security_assert(linka_nko_decrypt_subscription_token(substr($encrypted, 0, -2) . 'xx') === null, 'Corrupted management token ciphertext was accepted');
donations_security_assert(linka_nko_consent_subject_hash(' Donor@Example.test ') === linka_nko_consent_subject_hash('donor@example.test'), 'Consent subject hash normalization is unstable');

$batch_id = '10000000-0000-4000-8000-000000000001';
$body = linka_nko_fundraising_body($batch_id, '2026-07-21T12:00:00.000Z', [
    'event_id' => '20000000-0000-4000-8000-000000000002',
    'occurred_at' => '2026-07-21T11:59:00.000Z',
    'kind' => 'payment_succeeded',
    'frequency' => 'one_time',
    'attribution_source' => 'unknown',
    'attribution_campaign' => null,
    'amount' => '1200.00',
    'currency' => 'RUB',
]);
$expected_body = '{"schema_version":1,"batch_id":"10000000-0000-4000-8000-000000000001","sent_at":"2026-07-21T12:00:00.000Z","records":[{"event_id":"20000000-0000-4000-8000-000000000002","occurred_at":"2026-07-21T11:59:00.000Z","kind":"payment_succeeded","frequency":"one_time","attribution_source":"unknown","attribution_campaign":null,"amount":"1200.00","currency":"RUB"}]}';
donations_security_assert($body === $expected_body, 'Fundraising outbox body is not stable');

$secret = str_repeat('s', 32);
$headers = linka_nko_fundraising_headers('donation-2026-07', $secret, $batch_id, $body, '1784635200');
$canonical = "LINKA-HMAC-V2\nv2\nPOST\n/internal/fundraising/batches\nnko-donations\ndonation-2026-07\n{$batch_id}\n1784635200\n" . hash('sha256', $body);
$expected_signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $canonical, $secret, true)), '+/', '-_'), '=');
donations_security_assert($headers['Idempotency-Key'] === $batch_id && $headers['X-Linka-Request-ID'] === $batch_id, 'Fundraising idempotency headers do not bind the batch');
donations_security_assert($headers['X-Linka-Signature'] === $expected_signature && !str_contains($headers['X-Linka-Signature'], '='), 'Fundraising HMAC does not match LINKA-HMAC-V2');

$donations_source = file_get_contents(dirname(__DIR__) . '/wp-content/mu-plugins/linka-nko-donations.php');
$schema_start = is_string($donations_source) ? strpos($donations_source, "dbDelta(\"CREATE TABLE {\$tables['fundraising_outbox']}") : false;
$schema_end = $schema_start === false || !is_string($donations_source) ? false : strpos($donations_source, '");', $schema_start);
$outbox_schema = $schema_start === false || $schema_end === false || !is_string($donations_source) ? '' : substr($donations_source, $schema_start, $schema_end - $schema_start);
donations_security_assert($outbox_schema !== '' && str_contains($outbox_schema, 'event_key') && str_contains($outbox_schema, 'sent_at'), 'Fundraising outbox schema is incomplete');
foreach (['donor_', 'yookassa_', 'payment_method', 'idempotence_key', 'token'] as $forbidden_field) {
    donations_security_assert(!str_contains($outbox_schema, $forbidden_field), 'Fundraising outbox stores a prohibited field: ' . $forbidden_field);
}
$sync_wrapper_start = is_string($donations_source) ? strpos($donations_source, 'function linka_nko_sync_verified_payment_from_yookassa') : false;
$sync_wrapper_end = $sync_wrapper_start === false || !is_string($donations_source) ? false : strpos($donations_source, 'function linka_nko_sync_payment_from_yookassa', $sync_wrapper_start);
$sync_wrapper = $sync_wrapper_start === false || $sync_wrapper_end === false || !is_string($donations_source) ? '' : substr($donations_source, $sync_wrapper_start, $sync_wrapper_end - $sync_wrapper_start);
donations_security_assert($sync_wrapper !== '' && !str_contains($sync_wrapper, 'START TRANSACTION'), 'Verified payment sync must not wrap activation delivery in a transaction');
donations_security_assert(str_contains($donations_source, 'linka_nko_enqueue_subscription_activated_fundraising_event') && str_contains($donations_source, "'recurring_activated'"), 'Recurring activation outbox event is missing');
$activation_start = is_string($donations_source) ? strpos($donations_source, 'function linka_nko_maybe_send_subscription_activation_email') : false;
$activation_end = $activation_start === false || !is_string($donations_source) ? false : strpos($donations_source, 'function linka_nko_cancel_subscription_record', $activation_start);
$activation_delivery = $activation_start === false || $activation_end === false || !is_string($donations_source) ? '' : substr($donations_source, $activation_start, $activation_end - $activation_start);
$activation_enqueue = strpos($activation_delivery, 'linka_nko_enqueue_subscription_activated_fundraising_event');
donations_security_assert($activation_enqueue !== false && $activation_enqueue < strrpos($activation_delivery, 'COMMIT') && str_contains($activation_delivery, 'ROLLBACK'), 'Recurring activation event must be recorded before its transaction commits');

fwrite(STDOUT, "Donation security tests passed\n");
