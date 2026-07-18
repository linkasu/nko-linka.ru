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

fwrite(STDOUT, "Donation security tests passed\n");
