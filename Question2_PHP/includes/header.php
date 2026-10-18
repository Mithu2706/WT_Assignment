<?php
/**
 * includes/header.php
 * ---------------------------------------------------------------------
 * Common page header for all logged-in admin pages:
 * <head>, top navigation bar, logged-in user name, logout button and the
 * one-time flash messages (success / error) set by the previous page.
 *
 * Expects the including page to set:
 *   $pageTitle  – text for the <title> tag
 *   $activePage – 'dashboard' or 'add' to highlight the menu item
 */

$pageTitle = $pageTitle ?? 'Admin Panel';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Student Portal Admin</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="dashboard.php">
            <span class="brand-logo" aria-hidden="true">SP</span>
            <span>
                <span class="brand-title">Student Portal</span>
                <span class="brand-subtitle">Admin Panel</span>
            </span>
        </a>

        <nav class="nav" aria-label="Admin navigation">
            <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="add_student.php" class="<?= $activePage === 'add' ? 'active' : '' ?>">Add Student</a>
            <a href="../Question1_Frontend/register.html" target="_blank" rel="noopener">Registration Form</a>
        </nav>

        <div class="user-box">
            <span class="user-name">Signed in as <strong><?= e($_SESSION['admin_name'] ?? '') ?></strong></span>
            <!-- Logout is a POST form with a CSRF token so other sites cannot log the admin out -->
            <form method="post" action="logout.php" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-light btn-sm">Logout</button>
            </form>
        </div>
    </div>
</header>

<main class="container page">
<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
