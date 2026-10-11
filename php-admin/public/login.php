<?php
/**
 * Admin sign-in page.
 * GET  -> show the form
 * POST -> verify CSRF token + credentials, regenerate session id, redirect
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (Auth::user() !== null) {
    redirect('dashboard.php');
}

// Only allow redirects back to our own pages (prevents "open redirect").
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if (!preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%\-]*)?$/', $next) || str_starts_with($next, 'login.php')) {
    $next = 'dashboard.php';
}

$error = '';
$notice = '';
$username = '';

if (Session::$expired || isset($_GET['expired'])) {
    $notice = 'Your session expired after a period of inactivity. Please sign in again.';
}
if (isset($_GET['logged_out'])) {
    $notice = 'You have been signed out.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
        $error = 'Your sign-in form expired. Please try again.';
    } else {
        $result = Auth::attempt($username, $password);
        if ($result['ok']) {
            Auth::login($result['user']);
            flash('success', 'Welcome back, ' . $result['user']['display_name'] . '.');
            redirect($next);
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Student Admin</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="assets/js/admin.js" defer></script>
</head>
<body class="auth-page">
<main class="auth-card card">
    <div class="auth-brand">
        <img src="assets/img/favicon.svg" alt="" width="44" height="44">
        <div>
            <p class="eyebrow">Student Management Portal</p>
            <h1>Admin sign in</h1>
        </div>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="alert alert-info" role="status"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate data-prevent-double-submit>
        <?= Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($username) ?>"
                   required maxlength="50" autocomplete="username" autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required maxlength="72" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">
            <span class="spinner" aria-hidden="true"></span>
            <span class="btn-label">Sign in</span>
        </button>
    </form>

    <p class="auth-foot">
        Not an administrator? <a href="../../frontend/register.html">Student registration</a> ·
        <a href="http://localhost:8080/student-portal-servlet/login">Profile portal</a>
    </p>
</main>
</body>
</html>
