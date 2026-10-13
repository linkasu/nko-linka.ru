<?php
/**
 * Optional application-level authentication for VM internal endpoints.
 */

function linka_nko_internal_request_authorized(): bool
{
    $mode = (string) getenv('LINKA_NKO_INTERNAL_AUTH_MODE');
    if ($mode === '' || $mode === 'iam') {
        return true;
    }
    if ($mode !== 'bearer') {
        return false;
    }

    $key = trim((string) getenv('LINKA_NKO_INTERNAL_KEY'));
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (strlen($key) < 32 || !preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
        return false;
    }

    return hash_equals($key, $matches[1]);
}

function linka_nko_require_internal_auth(): void
{
    if (linka_nko_internal_request_authorized()) {
        return;
    }

    wp_send_json(['ok' => false, 'error' => 'unauthorized'], 401);
}
