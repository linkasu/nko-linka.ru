<?php

define('ABSPATH', __DIR__ . '/');

final class RetentionTestDatabase
{
    public array $queries = [];
    public array $inserts = [];

    public function prepare(string $sql, ...$arguments): string
    {
        return $sql;
    }

    public function get_var(string $sql)
    {
        $this->queries[] = ['read', $sql];
        return 2;
    }

    public function query(string $sql)
    {
        $this->queries[] = ['write', $sql];
        return 1;
    }

    public function insert(string $table, array $data, array $format): bool
    {
        $this->inserts[] = [$table, $data, $format];
        return true;
    }

    public function get_results(string $sql): array
    {
        $this->queries[] = ['read', $sql];
        return [(object) ['dry_run' => 1, 'source' => 'admin', 'report_json' => '{}', 'created_at' => '2026-07-18 00:00:00']];
    }
}

function linka_nko_donation_tables(): array
{
    return [
        'payments' => 'wp_payments',
        'subscriptions' => 'wp_subscriptions',
        'retention_runs' => 'wp_retention_runs',
    ];
}

function linka_nko_expire_canceled_management_tokens(): int
{
    return 3;
}

function sanitize_key($value): string
{
    return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
}

function wp_json_encode($value, int $flags = 0): string
{
    return json_encode($value, $flags);
}

require dirname(__DIR__) . '/wp-content/mu-plugins/linka-nko-retention.php';

function retention_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$cutoffs = linka_nko_retention_cutoffs(new DateTimeImmutable('2026-07-18 12:00:00', new DateTimeZone('UTC')));
retention_assert($cutoffs['attempts'] === '2025-07-18 12:00:00', 'Failed/pending one-year cutoff is wrong');
retention_assert($cutoffs['successful'] === '2021-01-01 00:00:00', 'Five-year reporting cutoff is wrong');
retention_assert($cutoffs['logs'] === '2026-06-18 12:00:00', 'Thirty-day log cutoff is wrong');

$specs = linka_nko_retention_query_specs(linka_nko_donation_tables(), $cutoffs);
retention_assert(str_contains($specs['stale_attempt_payments']['count'], "status IN ('pending', 'waiting_for_capture', 'failed', 'canceled')"), 'Failed/pending retention query is incomplete');
retention_assert(str_contains($specs['successful_payments']['count'], "status = 'succeeded'"), 'Successful retention query is incomplete');
retention_assert(str_contains($specs['consent_evidence']['apply'], 'INTERVAL 5 YEAR'), 'Consent evidence retention query is incomplete');

$GLOBALS['wpdb'] = new RetentionTestDatabase();
$dry_run = linka_nko_run_retention_cleanup(true, 'admin');
retention_assert($dry_run['dry_run'] === true, 'Dry-run report lost mode');
retention_assert(count(array_filter($GLOBALS['wpdb']->queries, static fn(array $query): bool => $query[0] === 'write')) === 0, 'Dry-run mutated retained data');
retention_assert(count($GLOBALS['wpdb']->inserts) === 1, 'Dry-run audit report was not stored');

$GLOBALS['wpdb'] = new RetentionTestDatabase();
$applied = linka_nko_run_retention_cleanup(false, 'scheduled');
retention_assert($applied['counts']['management_tokens'] === 3, 'Scheduled token cleanup was not reported');
retention_assert(count(array_filter($GLOBALS['wpdb']->queries, static fn(array $query): bool => $query[0] === 'write')) === count($specs) + 1, 'Scheduled cleanup did not execute all retention operations');
retention_assert(count(linka_nko_retention_recent_runs()) === 1, 'Admin retention reporting did not return audit rows');

fwrite(STDOUT, "Retention tests passed\n");
