<?php
/**
 * includes/auth.php
 * ---------------------------------------------------------------------
 * Included at the TOP of every page. It:
 *   1. loads the configuration and helper functions,
 *   2. starts a secure PHP session (HttpOnly + SameSite cookie),
 *   3. logs the admin out automatically after 30 minutes of inactivity,
 *   4. provides require_login() which protected pages call so that only
 *      a logged-in admin can reach the CRUD pages.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/upload_handler.php';

const SESSION_TIMEOUT_SECONDS = 30 * 60;

if (session_status() === PHP_SESSION_NONE) {
    session_name('SP_ADMIN_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,          // cookie disappears when the browser closes
        'path'     => '/',
        'httponly' => true,       // JavaScript cannot read the session cookie
        'samesite' => 'Lax',      // cookie not sent with cross-site POSTs
    ]);
    session_start();
}

// Inactivity timeout: destroy the session if the admin was idle too long.
if (isset($_SESSION['admin_id'], $_SESSION['last_activity'])
    && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS) {
    $_SESSION = [];
    session_regenerate_id(true);
    set_flash('error', 'Your session expired after 30 minutes of inactivity. Please log in again.');
}
$_SESSION['last_activity'] = time();

/** True when an admin is logged in. */
function is_logged_in(): bool
{
    return isset($_SESSION['admin_id']);
}

/** Call at the top of protected pages – redirects to login.php if not logged in. */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to access the admin panel.');
        redirect('login.php');
    }
}

/** Only allow POST for pages that change data (add/delete/upload). */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Method not allowed', 'This action can only be performed by submitting a form.', 405);
    }
}
