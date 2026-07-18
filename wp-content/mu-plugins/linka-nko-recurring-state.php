<?php
/**
 * Pure state transitions for recurring donation claims and activation delivery.
 */

if (!defined('ABSPATH')) {
    exit;
}

function linka_nko_recurring_state_can_claim_charge(array $state, string $now): bool
{
    return ($state['status'] ?? '') === 'active'
        && ($state['next_charge_at'] ?? '') !== ''
        && $state['next_charge_at'] <= $now
        && (($state['charge_lease_until'] ?? '') === '' || $state['charge_lease_until'] <= $now);
}

function linka_nko_recurring_state_claim_charge(array $state, string $claim_token, string $lease_until, int $payment_id): ?array
{
    if ($claim_token === '' || $payment_id <= 0 || ($state['status'] ?? '') !== 'active') {
        return null;
    }

    $state['status'] = 'charging';
    $state['charge_claim_token'] = $claim_token;
    $state['charge_lease_until'] = $lease_until;
    $state['last_payment_id'] = $payment_id;

    return $state;
}

function linka_nko_recurring_state_can_submit_charge(array $state, string $claim_token, int $payment_id, string $now): bool
{
    return ($state['status'] ?? '') === 'charging'
        && $claim_token !== ''
        && hash_equals((string) ($state['charge_claim_token'] ?? ''), $claim_token)
        && (int) ($state['last_payment_id'] ?? 0) === $payment_id
        && ($state['charge_lease_until'] ?? '') > $now
        && ($state['payment_method_id'] ?? '') !== '';
}

function linka_nko_recurring_state_cancel(array $state, string $canceled_at, string $token_expires_at): array
{
    $state['status'] = 'canceled';
    $state['payment_method_id'] = '';
    $state['next_charge_at'] = '';
    $state['charge_claim_token'] = '';
    $state['charge_lease_until'] = '';
    $state['activation_email_claim_token'] = '';
    $state['activation_email_lease_until'] = '';
    $state['canceled_at'] = $canceled_at;
    $state['management_token_expires_at'] = $token_expires_at;

    return $state;
}

function linka_nko_recurring_state_prepare_activation(array $state, string $token_reference): ?array
{
    if (!in_array($state['status'] ?? '', ['pending', 'activation_pending'], true) || $token_reference === '') {
        return null;
    }

    $state['status'] = 'activation_pending';
    if (($state['management_token_reference'] ?? '') === '') {
        $state['management_token_reference'] = $token_reference;
    }

    return $state;
}

function linka_nko_recurring_state_claim_activation(array $state, string $claim_token, string $now, string $lease_until): ?array
{
    if (($state['status'] ?? '') !== 'activation_pending'
        || ($state['activation_email_sent_at'] ?? '') !== ''
        || $claim_token === ''
        || (($state['activation_email_lease_until'] ?? '') !== '' && $state['activation_email_lease_until'] > $now)) {
        return null;
    }

    $state['activation_email_claim_token'] = $claim_token;
    $state['activation_email_lease_until'] = $lease_until;

    return $state;
}

function linka_nko_recurring_state_activation_failed(array $state, string $claim_token): array
{
    if (($state['status'] ?? '') === 'activation_pending'
        && hash_equals((string) ($state['activation_email_claim_token'] ?? ''), $claim_token)) {
        $state['activation_email_claim_token'] = '';
        $state['activation_email_lease_until'] = '';
    }

    return $state;
}

function linka_nko_recurring_state_activation_succeeded(array $state, string $claim_token, string $sent_at, string $next_charge_at): ?array
{
    if (($state['status'] ?? '') !== 'activation_pending'
        || !hash_equals((string) ($state['activation_email_claim_token'] ?? ''), $claim_token)) {
        return null;
    }

    $state['status'] = 'active';
    $state['activation_email_sent_at'] = $sent_at;
    $state['next_charge_at'] = $next_charge_at;
    $state['activation_email_claim_token'] = '';
    $state['activation_email_lease_until'] = '';

    return $state;
}

function linka_nko_payment_status_transition(string $current, string $incoming): string
{
    $known = ['pending', 'waiting_for_capture', 'succeeded', 'canceled', 'failed'];
    $current = in_array($current, $known, true) ? $current : 'pending';
    $incoming = in_array($incoming, $known, true) ? $incoming : 'pending';

    if ($current === 'succeeded' || $current === 'canceled') {
        return $current;
    }
    if ($incoming === 'succeeded' || $incoming === 'canceled') {
        return $incoming;
    }
    if ($current === 'failed') {
        return 'failed';
    }
    if ($current === 'waiting_for_capture' && $incoming === 'pending') {
        return 'waiting_for_capture';
    }

    return $incoming;
}
