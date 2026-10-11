<?php
/** Entry point: send signed-in admins to the dashboard, everyone else to sign in. */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

redirect(Auth::user() !== null ? 'dashboard.php' : 'login.php');
