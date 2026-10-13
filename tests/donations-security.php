<?php

define('ABSPATH', __DIR__ . '/');

final class InternalAuthJsonResponse extends RuntimeException
{
    public function __construct(public array $payload, public int $status)
    {
        parent::__construct('JSON response');
    }
}

function wp_send_json(array $payload, int $status): void
{
    throw new InternalAuthJsonResponse($payload, $status);
}

function wp_parse_url(string $url, int $component)
{
    return parse_url($url, $component);
}

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

function sanitize_textarea_field($value): string
{
    return strip_tags((string) $value);
}

function wp_unslash($value)
{
    return $value;
}

function wp_json_encode($value, $flags = 0)
{
    return json_encode($value, $flags);
}

function wp_generate_uuid4(): string
{
    static $counter = 0;
    $counter++;
    return sprintf('00000000-0000-4000-8000-%012d', $counter);
}

function sanitize_key($value): string
{
    return strtolower((string) $value);
}

function wp_mail(): bool
{
    return true;
}

final class DonationsSecurityWpdb
{
    public string $prefix = 'wp_';
    public object $payment;
    public int $outbox_insert_failures = 1;
    public array $outbox = [];
    private array $prepared_args = [];
    private ?object $transaction_payment = null;
    private ?array $transaction_outbox = null;

    public function __construct()
    {
        $this->payment = (object) [
            'id' => 42,
            'subscription_id' => 0,
            'yookassa_payment_id' => '',
            'amount_value' => '1200.00',
            'frequency' => LINKA_NKO_DONATION_FREQUENCY_ONE_TIME,
            'donor_email' => 'donor@example.test',
            'status' => 'pending',
            'payment_method_id' => null,
            'payment_method_saved' => 0,
            'cancellation_reason' => null,
            'thank_you_email_sent_at' => null,
            'paid_at' => null,
            'created_at' => '2026-07-21 11:59:00',
            'updated_at' => '2026-07-21 11:59:00',
        ];
    }

    public function prepare(string $query, ...$args): string
    {
        $this->prepared_args = $args;
        return $query;
    }

    public function get_row(string $query): object
    {
        return clone $this->payment;
    }

    public function get_var(string $query)
    {
        $event_key = (string) ($this->prepared_args[0] ?? '');
        if (str_contains($query, 'SELECT 1')) {
            return isset($this->outbox[$event_key]) ? '1' : null;
        }
        if (str_contains($query, 'SELECT event_key')) {
            return isset($this->outbox[$event_key]) ? $event_key : null;
        }

        return null;
    }

    public function update(string $table, array $data): int
    {
        foreach ($data as $field => $value) {
            $this->payment->{$field} = $value;
        }
        return 1;
    }

    public function insert(string $table, array $data)
    {
        if (!str_contains($table, 'fundraising_outbox')) {
            return false;
        }
        if ($this->outbox_insert_failures > 0) {
            $this->outbox_insert_failures--;
            return false;
        }
        if (isset($this->outbox[$data['event_key']])) {
            return false;
        }
        $this->outbox[$data['event_key']] = $data;
        return 1;
    }

    public function query(string $query): int
    {
        if ($query === 'START TRANSACTION') {
            $this->transaction_payment = clone $this->payment;
            $this->transaction_outbox = $this->outbox;
        }
        if ($query === 'ROLLBACK') {
            $this->payment = $this->transaction_payment ?? $this->payment;
            $this->outbox = $this->transaction_outbox ?? $this->outbox;
        }
        if ($query === 'COMMIT' || $query === 'ROLLBACK') {
            $this->transaction_payment = null;
            $this->transaction_outbox = null;
        }
        return 1;
    }
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
donations_security_assert(linka_nko_donation_message(" Привет!\nСпасибо ") === "Привет!\nСпасибо", 'Donation message lost line breaks');
donations_security_assert(linka_nko_donation_message(str_repeat('x', 501)) === null, 'Oversized donation message was accepted');
donations_security_assert(linka_nko_donation_message(['unexpected']) === null, 'Array donation message was accepted');
donations_security_assert(!linka_nko_message_consent_valid('Спасибо', false), 'Message accepted without separate consent');
donations_security_assert(linka_nko_message_consent_valid('Спасибо', true) && linka_nko_message_consent_valid('', false), 'Valid message or empty optional message rejected');
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
$auth_source = file_get_contents(dirname(__DIR__) . '/wp-content/mu-plugins/linka-nko-internal-auth.php');
putenv('LINKA_NKO_INTERNAL_AUTH_MODE=bearer');
putenv('LINKA_NKO_INTERNAL_KEY=' . str_repeat('k', 32));
unset($_SERVER['HTTP_AUTHORIZATION']);
donations_security_assert(!linka_nko_internal_request_authorized(), 'Missing bearer token was accepted');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong';
donations_security_assert(!linka_nko_internal_request_authorized(), 'Invalid bearer token was accepted');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('k', 32);
donations_security_assert(linka_nko_internal_request_authorized(), 'Valid bearer token was rejected');
putenv('LINKA_NKO_INTERNAL_KEY=too-short');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer too-short';
donations_security_assert(!linka_nko_internal_request_authorized(), 'Weak internal key was accepted');
putenv('LINKA_NKO_INTERNAL_AUTH_MODE=unknown');
donations_security_assert(!linka_nko_internal_request_authorized(), 'Unknown internal auth mode was accepted');
putenv('LINKA_NKO_INTERNAL_AUTH_MODE=bearer');
putenv('LINKA_NKO_INTERNAL_KEY=' . str_repeat('k', 32));
unset($_SERVER['HTTP_AUTHORIZATION']);
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach (['/' . LINKA_NKO_RECURRING_INTERNAL_PATH => 'recurring runner', '/' . LINKA_NKO_DONATION_SYNC_INTERNAL_PATH => 'donation sync'] as $path => $label) {
    $_SERVER['REQUEST_URI'] = $path;
    try {
        $path === '/' . LINKA_NKO_RECURRING_INTERNAL_PATH
            ? linka_nko_run_internal_recurring_path()
            : linka_nko_run_internal_donation_sync_path();
        donations_security_assert(false, ucfirst($label) . ' continued without bearer token');
    } catch (InternalAuthJsonResponse $response) {
        donations_security_assert($response->status === 401 && ($response->payload['error'] ?? '') === 'unauthorized', ucfirst($label) . ' did not reject missing bearer token');
    }
}
putenv('LINKA_NKO_INTERNAL_AUTH_MODE');
putenv('LINKA_NKO_INTERNAL_KEY');
unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
donations_security_assert(linka_nko_internal_request_authorized(), 'Default IAM-compatible mode requires application bearer auth');
donations_security_assert(is_string($auth_source) && str_contains($auth_source, 'strlen($key) < 32'), 'Weak internal key is not rejected');
$recurring_route = substr($donations_source, strpos($donations_source, 'function linka_nko_run_internal_recurring_path'), strpos($donations_source, 'function linka_nko_run_internal_donation_sync_path') - strpos($donations_source, 'function linka_nko_run_internal_recurring_path'));
$sync_route = substr($donations_source, strpos($donations_source, 'function linka_nko_run_internal_donation_sync_path'), strpos($donations_source, 'function linka_nko_sync_successful_payments_from_yookassa') - strpos($donations_source, 'function linka_nko_run_internal_donation_sync_path'));
donations_security_assert(strpos($recurring_route, 'linka_nko_require_internal_auth') < strpos($recurring_route, 'linka_nko_process_due_subscriptions'), 'Recurring runner authenticates after writes');
donations_security_assert(strpos($sync_route, 'linka_nko_require_internal_auth') < strpos($sync_route, 'linka_nko_sync_successful_payments_from_yookassa'), 'Donation sync authenticates after writes');
donations_security_assert(str_contains($donations_source, 'payments.created_at >= %s') && str_contains($donations_source, 'payments.created_at < %s'), 'Future-only charging recovery cutoff is incomplete');
donations_security_assert(str_contains($donations_source, "status = 'active' AND next_charge_at = %s{\$cutoff_sql}"), 'Future-only cutoff is missing from the atomic charge claim');
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
donations_security_assert($sync_wrapper !== '' && str_contains($sync_wrapper, 'START TRANSACTION') && str_contains($sync_wrapper, 'ROLLBACK') && strpos($sync_wrapper, 'COMMIT') < strpos($sync_wrapper, 'linka_nko_finalize_payment_sync'), 'Verified payment sync must commit the terminal outbox before post-sync delivery');
donations_security_assert(str_contains($donations_source, 'linka_nko_enqueue_subscription_activated_fundraising_event') && str_contains($donations_source, "'recurring_activated'"), 'Recurring activation outbox event is missing');
$activation_start = is_string($donations_source) ? strpos($donations_source, 'function linka_nko_maybe_send_subscription_activation_email') : false;
$activation_end = $activation_start === false || !is_string($donations_source) ? false : strpos($donations_source, 'function linka_nko_cancel_subscription_record', $activation_start);
$activation_delivery = $activation_start === false || $activation_end === false || !is_string($donations_source) ? '' : substr($donations_source, $activation_start, $activation_end - $activation_start);
$activation_enqueue = strpos($activation_delivery, 'linka_nko_enqueue_subscription_activated_fundraising_event');
donations_security_assert($activation_enqueue !== false && $activation_enqueue < strrpos($activation_delivery, 'COMMIT') && str_contains($activation_delivery, 'ROLLBACK'), 'Recurring activation event must be recorded before its transaction commits');
donations_security_assert(strpos($donations_source, 'linka_nko_get_pending_activation_subscriptions') < strpos($donations_source, "getenv('LINKA_NKO_FUTURE_ONLY')"), 'Future-only cutoff blocks activation email processing');

global $wpdb;
$wpdb = new DonationsSecurityWpdb();
$terminal_payment = [
    'id' => 'yookassa-terminal-payment',
    'status' => 'succeeded',
    'metadata' => ['local_payment_id' => '42'],
    'amount' => ['value' => '1200.00', 'currency' => 'RUB'],
    'captured_at' => '2026-07-21T12:00:00Z',
];
donations_security_assert(!linka_nko_sync_verified_payment_from_yookassa($terminal_payment, null), 'Terminal sync succeeded despite forced outbox insert failure');
donations_security_assert($wpdb->payment->status === 'pending' && $wpdb->outbox === [], 'Failed terminal outbox enqueue did not roll back the payment update');
donations_security_assert(linka_nko_sync_verified_payment_from_yookassa($terminal_payment, null), 'Repeated terminal sync did not retry the outbox enqueue');
$terminal_event_key = linka_nko_fundraising_event_key('payment', 42, 'payment_succeeded');
donations_security_assert(count($wpdb->outbox) === 1 && isset($wpdb->outbox[$terminal_event_key]), 'Terminal retry did not enqueue exactly one deterministic payment event');

fwrite(STDOUT, "Donation security tests passed\n");
