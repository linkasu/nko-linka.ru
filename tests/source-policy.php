<?php

function source_policy_fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = $argv[1] ?? dirname(__DIR__);
$donations = file_get_contents($root . '/wp-content/mu-plugins/linka-nko-donations.php');
if (!is_string($donations)) {
    source_policy_fail('Could not read donation source');
}

foreach (['admin_post_nopriv_linka_nko_run_recurring_donations', 'admin_post_linka_nko_run_recurring_donations', 'LINKA_NKO_RECURRING_TOKEN'] as $legacy_marker) {
    if (str_contains($donations, $legacy_marker)) {
        source_policy_fail('Legacy public recurring action is still present: ' . $legacy_marker);
    }
}
if (!str_contains($donations, "LINKA_NKO_RECURRING_INTERNAL_PATH = 'internal/run-recurring-donations'")) {
    source_policy_fail('IAM-only recurring path is missing');
}
foreach (['charge_claim_token', 'charge_lease_until', 'activation_email_claim_token', 'activation_email_lease_until', 'FOR UPDATE', "status = 'activation_pending'"] as $required_marker) {
    if (!str_contains($donations, $required_marker)) {
        source_policy_fail('Recurring race guard is missing: ' . $required_marker);
    }
}
foreach (['consent_at', 'consent_version', 'offer_accepted_at', 'privacy_version', 'offer_version', 'consent_subject_hash', 'linka_nko_migrate_payment_consent_evidence', 'linka_nko_migrate_separate_payment_acceptance_evidence'] as $retention_marker) {
    if (!str_contains($donations, $retention_marker)) {
        source_policy_fail('Donation consent evidence is missing: ' . $retention_marker);
    }
}
foreach (['name="offer_accepted"', 'name="personal_data_consent"', '$has_offer_acceptance', '$has_personal_data_consent'] as $separate_consent_marker) {
    if (!str_contains($donations, $separate_consent_marker)) {
        source_policy_fail('Separate donation acceptance control is missing: ' . $separate_consent_marker);
    }
}
if (str_contains($donations, 'name="donor_consent"')) {
    source_policy_fail('Legacy combined donation consent control is still rendered');
}
$retention_source = file_get_contents($root . '/wp-content/mu-plugins/linka-nko-retention.php');
foreach (['linka_nko_daily_retention_cleanup', 'linka_nko_run_retention_cleanup', 'INTERVAL 5 YEAR', 'P30D'] as $retention_marker) {
    if (!is_string($retention_source) || !str_contains($retention_source, $retention_marker)) {
        source_policy_fail('Scheduled retention implementation is missing: ' . $retention_marker);
    }
}

$analytics_patterns = [
    '/mc\.yandex\.ru/i',
    '/metrika\/tag\.js/i',
    '/googletagmanager\.com/i',
    '/google-analytics\.com/i',
    '/webvisor\s*:\s*true/i',
];
$source_directories = [$root . '/wp-content', $root . '/infra/wordpress'];
foreach ($source_directories as $source_directory) {
    if (!is_dir($source_directory)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'html'], true)) {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        foreach ($analytics_patterns as $pattern) {
            if (is_string($source) && preg_match($pattern, $source)) {
                source_policy_fail('Analytics marker found in runtime source: ' . $file->getPathname());
            }
        }
    }
}

$privacy_policy = file_get_contents($root . '/content/pages/privacy-policy.md');
if (!is_string($privacy_policy)
    || !str_contains($privacy_policy, 'приказом № 5 от 19 августа 2026 года')
    || !str_contains($privacy_policy, '/wp-content/uploads/documents/privacy-policy-order-5-2026-08-19.pdf')) {
    source_policy_fail('Approved privacy policy wrapper is incomplete');
}
$donation_consent = file_get_contents($root . '/content/pages/donation-consent.md');
if (!is_string($donation_consent)
    || !str_contains($donation_consent, '/wp-content/uploads/documents/donation-consent-tracker-29-2026-08-24.pdf')
    || !str_contains($donations, 'personal_data_consent')
    || !str_contains($donations, 'offer_accepted')) {
    source_policy_fail('Separate donation consent publication is incomplete');
}
foreach (['privacy-policy-order-5-2026-08-19.pdf', 'donation-consent-tracker-29-2026-08-24.pdf'] as $legal_pdf) {
    $pdf = file_get_contents($root . '/content/documents/' . $legal_pdf, false, null, 0, 5);
    if ($pdf !== '%PDF-') {
        source_policy_fail('Approved legal PDF is missing or invalid: ' . $legal_pdf);
    }
}
$cookie_notice = file_get_contents($root . '/wp-content/mu-plugins/linka-nko-cookie-notice.php');
foreach (['LINKA_NKO_COOKIE_NOTICE_MAX_AGE = 15552000', 'Понятно', '/privacy-policy/', 'SameSite=Lax'] as $cookie_notice_marker) {
    if (!is_string($cookie_notice) || !str_contains($cookie_notice, $cookie_notice_marker)) {
        source_policy_fail('Technical cookie notice is incomplete: ' . $cookie_notice_marker);
    }
}

fwrite(STDOUT, "Source policy tests passed\n");
