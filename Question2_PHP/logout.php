<?php
/**
 * logout.php – ends the admin session.
 * Only accepts POST with a valid CSRF token (sent by the Logout button
 * in the header), clears the session data, deletes the session cookie
 * and destroys the session on the server.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_post();
if (!verify_csrf()) {
    render_error_page('Invalid request', 'Your form has expired. Please go back and try again.', 400);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $params['path'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_destroy();

// Start a fresh, empty session only to carry the "logged out" message
session_start();
set_flash('success', 'You have been logged out successfully.');
redirect('login.php');
