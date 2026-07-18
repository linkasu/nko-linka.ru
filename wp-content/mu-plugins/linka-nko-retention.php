<?php
/**
 * Donation retention planning, anonymization and audit reporting.
 */

if (!defined('ABSPATH')) {
    exit;
}

function linka_nko_retention_cutoffs(?DateTimeImmutable $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $now = $now->setTimezone(new DateTimeZone('UTC'));
    $successful_year = (int) $now->format('Y') - 5;

    return [
        'now' => $now->format('Y-m-d H:i:s'),
        'attempts' => $now->sub(new DateInterval('P1Y'))->format('Y-m-d H:i:s'),
        'successful' => sprintf('%04d-01-01 00:00:00', $successful_year),
        'logs' => $now->sub(new DateInterval('P30D'))->format('Y-m-d H:i:s'),
    ];
}

function linka_nko_retention_query_specs(array $tables, array $cutoffs): array
{
    $payment_anonymization = "donor_name = '', donor_email = '', yookassa_payment_id = NULL, idempotence_key = NULL, payment_method_id = NULL, payment_method_saved = 0, cancellation_reason = NULL, anonymized_at = %s";
    $subscription_anonymization = "donor_name = '', donor_email = '', payment_method_id = NULL, cancellation_token_hash = NULL, management_token_ciphertext = NULL, management_token_expires_at = NULL, anonymized_at = %s";

    return [
        'stale_attempt_payments' => [
            'count' => "SELECT COUNT(*) FROM {$tables['payments']} WHERE status IN ('pending', 'waiting_for_capture', 'failed', 'canceled') AND updated_at < %s AND anonymized_at IS NULL",
            'apply' => "UPDATE {$tables['payments']} SET {$payment_anonymization} WHERE status IN ('pending', 'waiting_for_capture', 'failed', 'canceled') AND updated_at < %s AND anonymized_at IS NULL",
            'count_args' => [$cutoffs['attempts']],
            'apply_args' => [$cutoffs['now'], $cutoffs['attempts']],
        ],
        'successful_payments' => [
            'count' => "SELECT COUNT(*) FROM {$tables['payments']} WHERE status = 'succeeded' AND COALESCE(paid_at, updated_at) < %s AND anonymized_at IS NULL",
            'apply' => "UPDATE {$tables['payments']} SET {$payment_anonymization} WHERE status = 'succeeded' AND COALESCE(paid_at, updated_at) < %s AND anonymized_at IS NULL",
            'count_args' => [$cutoffs['successful']],
            'apply_args' => [$cutoffs['now'], $cutoffs['successful']],
        ],
        'stale_attempt_subscriptions' => [
            'count' => "SELECT COUNT(*) FROM {$tables['subscriptions']} WHERE status IN ('pending', 'failed') AND updated_at < %s AND anonymized_at IS NULL",
            'apply' => "UPDATE {$tables['subscriptions']} SET {$subscription_anonymization} WHERE status IN ('pending', 'failed') AND updated_at < %s AND anonymized_at IS NULL",
            'count_args' => [$cutoffs['attempts']],
            'apply_args' => [$cutoffs['now'], $cutoffs['attempts']],
        ],
        'closed_subscriptions' => [
            'count' => "SELECT COUNT(*) FROM {$tables['subscriptions']} WHERE status = 'canceled' AND DATE_ADD(COALESCE(canceled_at, updated_at), INTERVAL 5 YEAR) <= %s AND anonymized_at IS NULL",
            'apply' => "UPDATE {$tables['subscriptions']} SET {$subscription_anonymization} WHERE status = 'canceled' AND DATE_ADD(COALESCE(canceled_at, updated_at), INTERVAL 5 YEAR) <= %s AND anonymized_at IS NULL",
            'count_args' => [$cutoffs['now']],
            'apply_args' => [$cutoffs['now'], $cutoffs['now']],
        ],
        'consent_evidence' => [
            'count' => "SELECT COUNT(*) FROM {$tables['payments']} p LEFT JOIN {$tables['subscriptions']} s ON s.id = p.subscription_id WHERE p.consent_at IS NOT NULL AND ((p.subscription_id IS NULL AND DATE_ADD(COALESCE(p.paid_at, p.updated_at), INTERVAL 5 YEAR) <= %s) OR (p.subscription_id IS NOT NULL AND s.status IN ('pending', 'failed', 'canceled') AND DATE_ADD(COALESCE(s.canceled_at, s.updated_at), INTERVAL 5 YEAR) <= %s))",
            'apply' => "UPDATE {$tables['payments']} p LEFT JOIN {$tables['subscriptions']} s ON s.id = p.subscription_id SET p.consent_at = NULL, p.privacy_version = NULL, p.offer_version = NULL, p.consent_subject_hash = NULL WHERE p.consent_at IS NOT NULL AND ((p.subscription_id IS NULL AND DATE_ADD(COALESCE(p.paid_at, p.updated_at), INTERVAL 5 YEAR) <= %s) OR (p.subscription_id IS NOT NULL AND s.status IN ('pending', 'failed', 'canceled') AND DATE_ADD(COALESCE(s.canceled_at, s.updated_at), INTERVAL 5 YEAR) <= %s))",
            'count_args' => [$cutoffs['now'], $cutoffs['now']],
            'apply_args' => [$cutoffs['now'], $cutoffs['now']],
        ],
    ];
}

function linka_nko_retention_prepare(string $sql, array $arguments): string
{
    global $wpdb;
    return $arguments === [] ? $sql : $wpdb->prepare($sql, ...$arguments);
}

function linka_nko_retention_plan(?DateTimeImmutable $now = null): array
{
    global $wpdb;
    $cutoffs = linka_nko_retention_cutoffs($now);
    $specs = linka_nko_retention_query_specs(linka_nko_donation_tables(), $cutoffs);
    $counts = [];
    foreach ($specs as $name => $spec) {
        $counts[$name] = (int) $wpdb->get_var(linka_nko_retention_prepare($spec['count'], $spec['count_args']));
    }

    return ['cutoffs' => $cutoffs, 'counts' => $counts];
}

function linka_nko_run_retention_cleanup(bool $dry_run, string $source): array
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $plan = linka_nko_retention_plan();
    $specs = linka_nko_retention_query_specs($tables, $plan['cutoffs']);
    $affected = $plan['counts'];
    $logs_deleted = 0;

    if (!$dry_run) {
        foreach ($specs as $name => $spec) {
            $result = $wpdb->query(linka_nko_retention_prepare($spec['apply'], $spec['apply_args']));
            $affected[$name] = is_int($result) && $result > 0 ? $result : 0;
        }
        $affected['management_tokens'] = linka_nko_expire_canceled_management_tokens();
        $logs_deleted_result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tables['retention_runs']} WHERE created_at < %s",
            $plan['cutoffs']['logs']
        ));
        $logs_deleted = is_int($logs_deleted_result) && $logs_deleted_result > 0 ? $logs_deleted_result : 0;
    } else {
        $affected['management_tokens'] = 0;
    }

    $report = [
        'dry_run' => $dry_run,
        'source' => sanitize_key($source),
        'cutoffs' => $plan['cutoffs'],
        'counts' => $affected,
        'audit_reports_deleted' => $logs_deleted,
    ];
    $wpdb->insert($tables['retention_runs'], [
        'dry_run' => $dry_run ? 1 : 0,
        'source' => $report['source'],
        'report_json' => wp_json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => $plan['cutoffs']['now'],
    ], ['%d', '%s', '%s', '%s']);

    return $report;
}

function linka_nko_schedule_retention_cleanup(): void
{
    if (!wp_next_scheduled('linka_nko_daily_retention_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'linka_nko_daily_retention_cleanup');
    }
}

function linka_nko_execute_scheduled_retention_cleanup(): void
{
    linka_nko_run_retention_cleanup(false, 'scheduled');
}

function linka_nko_retention_recent_runs(int $limit = 10): array
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    return $wpdb->get_results($wpdb->prepare(
        "SELECT dry_run, source, report_json, created_at FROM {$tables['retention_runs']} ORDER BY created_at DESC LIMIT %d",
        max(1, min(50, $limit))
    ));
}

function linka_nko_retention_dry_run_admin(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.', '', ['response' => 403]);
    }
    check_admin_referer('linka_nko_retention_dry_run');
    linka_nko_run_retention_cleanup(true, 'admin');
    wp_safe_redirect(add_query_arg(['page' => 'linka-nko-donations', 'retention_dry_run' => '1'], admin_url('admin.php')));
    exit;
}
