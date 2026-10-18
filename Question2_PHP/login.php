<?php
/**
 * login.php – Admin login page.
 * ---------------------------------------------------------------------
 *  GET  → show the login form
 *  POST → look up the username with a PREPARED statement, check the
 *         password with password_verify() against the bcrypt hash stored
 *         in the users table, then create the login session.
 *
 * session_regenerate_id(true) gives the admin a brand-new session id after
 * login, which prevents "session fixation" attacks.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf()) {
        $error = 'Your form has expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (strlen($username) > 50 || strlen($password) > 72) {
        $error = 'Invalid username or password.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, username, full_name, password_hash FROM users WHERE username = :username');
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            // password_verify() re-hashes the typed password with the stored salt and compares safely
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name'] = $user['full_name'];
                $_SESSION['last_activity'] = time();

                // Upgrade the hash automatically if PHP's default algorithm/cost changes
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $rehash = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                    $rehash->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
                }
                $update = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
                $update->execute([':id' => $user['id']]);

                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect('dashboard.php');
            }
            // Same message for "unknown user" and "wrong password" – does not reveal which usernames exist
            $error = 'Invalid username or password.';
        } catch (PDOException $e) {
            error_log('Login query failed: ' . $e->getMessage());
            $error = 'A database error occurred. Please try again later.';
        }
    }
}

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Student Portal</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="auth-header">
        <span class="brand-logo large" aria-hidden="true">SP</span>
        <h1>Admin Panel Login</h1>
        <p>Student Portal · Records &amp; Documents Management</p>
    </div>

    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($username) ?>"
                   maxlength="50" autocomplete="username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="72"
                   autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>

    <p class="auth-footer">
        <a href="../Question1_Frontend/index.html">&larr; Back to portal home</a>
    </p>
</div>
</body>
</html>
