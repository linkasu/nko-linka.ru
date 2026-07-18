<?php

define('ABSPATH', __DIR__ . '/');
$state_source = $argv[1] ?? dirname(__DIR__) . '/wp-content/mu-plugins/linka-nko-recurring-state.php';
require $state_source;

function recurring_assert($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$now = '2026-07-18 12:00:00';
$active = [
    'status' => 'active',
    'next_charge_at' => '2026-07-18 11:59:00',
    'charge_claim_token' => '',
    'charge_lease_until' => '',
    'payment_method_id' => 'saved-method',
];
recurring_assert(linka_nko_recurring_state_can_claim_charge($active, $now), 'Due active subscription was not claimable');

$claimed = linka_nko_recurring_state_claim_charge($active, 'claim-a', '2026-07-18 12:05:00', 41);
recurring_assert(is_array($claimed), 'Charge claim failed');
recurring_assert(!linka_nko_recurring_state_can_claim_charge($claimed, $now), 'Concurrent worker could claim an in-flight charge');
recurring_assert(linka_nko_recurring_state_can_submit_charge($claimed, 'claim-a', 41, $now), 'Claim owner could not submit charge');
recurring_assert(!linka_nko_recurring_state_can_submit_charge($claimed, 'claim-b', 41, $now), 'Wrong claim token could submit charge');
recurring_assert(!linka_nko_recurring_state_can_submit_charge($claimed, 'claim-a', 41, '2026-07-18 12:05:00'), 'Expired lease could submit charge');

$canceled = linka_nko_recurring_state_cancel($claimed, '2026-07-18 12:01:00', '2026-08-17 12:01:00');
recurring_assert($canceled['status'] === 'canceled', 'Cancellation transition failed');
recurring_assert(!linka_nko_recurring_state_can_submit_charge($canceled, 'claim-a', 41, '2026-07-18 12:02:00'), 'Charge remained possible after cancellation claim');

$activation = linka_nko_recurring_state_prepare_activation([
    'status' => 'pending',
    'management_token_reference' => '',
    'activation_email_sent_at' => '',
    'activation_email_lease_until' => '',
], 'encrypted-token-a');
recurring_assert(is_array($activation) && $activation['status'] === 'activation_pending', 'Activation was not held pending delivery');

$mail_claim = linka_nko_recurring_state_claim_activation($activation, 'mail-a', $now, '2026-07-18 12:05:00');
recurring_assert(is_array($mail_claim), 'Activation email claim failed');
$mail_failed = linka_nko_recurring_state_activation_failed($mail_claim, 'mail-a');
recurring_assert($mail_failed['status'] === 'activation_pending', 'Mail failure activated subscription');
recurring_assert($mail_failed['management_token_reference'] === 'encrypted-token-a', 'Mail failure rotated management token');

$mail_retry = linka_nko_recurring_state_claim_activation($mail_failed, 'mail-b', $now, '2026-07-18 12:05:00');
recurring_assert(is_array($mail_retry), 'Activation email was not retryable');
recurring_assert(linka_nko_recurring_state_activation_succeeded($mail_retry, 'mail-a', $now, '2026-08-18 12:00:00') === null, 'Stale mail claim activated subscription');
$activated = linka_nko_recurring_state_activation_succeeded($mail_retry, 'mail-b', $now, '2026-08-18 12:00:00');
recurring_assert(is_array($activated) && $activated['status'] === 'active', 'Successful management-link delivery did not activate subscription');

$activation_canceled = linka_nko_recurring_state_cancel($mail_retry, $now, '2026-08-17 12:00:00');
recurring_assert(linka_nko_recurring_state_activation_succeeded($activation_canceled, 'mail-b', $now, '2026-08-18 12:00:00') === null, 'Mail completion reactivated a canceled subscription');

$status = linka_nko_payment_status_transition('pending', 'succeeded');
$status = linka_nko_payment_status_transition($status, 'pending');
recurring_assert($status === 'succeeded', 'Out-of-order pending webhook regressed succeeded payment');
recurring_assert(linka_nko_payment_status_transition('succeeded', 'canceled') === 'succeeded', 'Late canceled webhook regressed succeeded payment');
recurring_assert(linka_nko_payment_status_transition('canceled', 'succeeded') === 'canceled', 'Late succeeded webhook revived canceled provider payment');
recurring_assert(linka_nko_payment_status_transition('waiting_for_capture', 'succeeded') === 'succeeded', 'Valid payment progression failed');
recurring_assert(linka_nko_payment_status_transition('waiting_for_capture', 'pending') === 'waiting_for_capture', 'Out-of-order pending regressed waiting-for-capture payment');
recurring_assert(linka_nko_payment_status_transition('failed', 'succeeded') === 'succeeded', 'Provider success could not recover a local transport failure');

fwrite(STDOUT, "Recurring state tests passed\n");
