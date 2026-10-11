<?php
/**
 * Generic error page.  Shows only the safe, user-facing message - never a
 * stack trace, SQL statement or file path.
 * Expects: $status (int), $title (string), $message (string)
 */
declare(strict_types=1);
$loggedIn = session_status() === PHP_SESSION_ACTIVE && Auth::user() !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Student Admin</title>
    <link rel="stylesheet" href="<?= e(base_path()) ?>assets/css/admin.css">
</head>
<body class="auth-page">
<main class="auth-card card error-card">
    <p class="error-code"><?= e((string) $status) ?></p>
    <h1><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <div class="error-actions">
        <?php if ($loggedIn): ?>
            <a class="btn btn-primary" href="<?= e(url('dashboard.php')) ?>">Back to dashboard</a>
            <a class="btn btn-ghost" href="<?= e(url('students.php')) ?>">Student list</a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Go to sign in</a>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
