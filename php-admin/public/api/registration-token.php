<?php
/**
 * Issues the single-use anti-CSRF token for the static registration page.
 * GET only; returns JSON {"token": "..."} and binds the token to the session.
 */
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}
json_response(['ok' => true, 'token' => Csrf::issueRegistrationToken()]);
