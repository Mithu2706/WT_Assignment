<?php
/**
 * Opening half of the admin layout (sidebar + top bar).
 * Expects: $pageTitle (string), $activeNav (string), $user (array from Auth).
 * Every dynamic value is escaped with e().
 */
declare(strict_types=1);
$activeNav = $activeNav ?? '';
$navItems = [
    'dashboard' => ['dashboard.php', 'Dashboard', '<path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/>'],
    'students'  => ['students.php', 'Students', '<path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm0 2c-2.7 0-8 1.3-8 4v2h7v-2c0-1.2.6-2.3 1.8-3.2A11 11 0 0 0 8 13Zm8 0c-3 0-9 1.5-9 4.5V20h18v-2.5c0-3-6-4.5-9-4.5Z"/>'],
    'create'    => ['student_create.php', 'Add student', '<path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6v-2Z"/>'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($pageTitle) ?> · Student Admin</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="assets/js/admin.js" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#content">Skip to content</a>
<div class="shell">
    <aside class="sidebar" aria-label="Admin navigation">
        <a class="brand" href="dashboard.php">
            <img src="assets/img/favicon.svg" alt="" width="34" height="34">
            <span>Student Admin<small>Records &amp; documents</small></span>
        </a>
        <nav class="side-nav">
            <?php foreach ($navItems as $key => [$href, $label, $icon]): ?>
                <a href="<?= e($href) ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"
                   <?= $activeNav === $key ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon /* static SVG markup, not user input */ ?></svg>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="side-links">
            <p>Other modules</p>
            <a href="../../frontend/register.html">Registration form</a>
            <a href="http://localhost:8080/student-portal-servlet/students">Profile portal (Servlet)</a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <?php if (!empty($breadcrumbs)): ?>
                    <nav class="breadcrumbs" aria-label="Breadcrumb">
                        <?php foreach ($breadcrumbs as [$crumbHref, $crumbLabel]): ?>
                            <a href="<?= e($crumbHref) ?>"><?= e($crumbLabel) ?></a><span aria-hidden="true">/</span>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="user-menu">
                <span class="user-chip" title="Signed in">
                    <span class="avatar avatar-sm" aria-hidden="true"><?= e(initials($user['display_name'])) ?></span>
                    <span><?= e($user['display_name']) ?><small><?= e($user['role']) ?></small></span>
                </span>
                <!-- Logout changes state, so it is a POST form with a CSRF token -->
                <form method="post" action="logout.php">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                </form>
            </div>
        </header>

        <main class="content" id="content" tabindex="-1">
            <?php foreach (take_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endforeach; ?>
