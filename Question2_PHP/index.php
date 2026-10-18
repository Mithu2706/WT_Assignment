<?php
/**
 * index.php – entry point of the admin panel.
 * Sends logged-in admins to the dashboard and everyone else to the login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
