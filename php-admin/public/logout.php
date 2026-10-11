<?php
/**
 * Logout - POST only, CSRF protected (a malicious link cannot log you out),
 * destroys the server-side session and expires the cookie.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

require_post();
if (Auth::user() !== null) {
    Csrf::requireValid();
}
Auth::logout();
redirect('login.php', ['logged_out' => 1]);
