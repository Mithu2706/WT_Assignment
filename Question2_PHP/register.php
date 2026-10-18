<?php
/**
 * register.php – PUBLIC endpoint that receives the frontend registration
 * form (Question1_Frontend/register.html, Question 1).
 * ---------------------------------------------------------------------
 * JavaScript already validated the form in the browser, but a user can
 * bypass JavaScript, so EVERYTHING is validated again here:
 *   - student fields (same rules as the admin form)
 *   - duplicate roll number / e-mail
 *   - profile photo (required) and ID proof (optional): type, MIME, size
 * Then the student and files are saved in one transaction and a
 * confirmation page is shown. No login is needed for this page.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$errors = [];
$data = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../Question1_Frontend/register.html');
}

if (post_body_too_large()) {
    $errors['form'] = 'The uploaded files are too large. Photo: max 2 MB, ID proof: max 5 MB.';
} else {
    [$data, $errors] = validate_student_data($_POST, true);
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'You must accept the declaration.';
    }
    try {
        $errors = add_duplicate_errors($errors, $data);
    } catch (PDOException $e) {
        error_log('Register duplicate check failed: ' . $e->getMessage());
        $errors['form'] = 'A database error occurred. Please try again later.';
    }
    [$uploads, $uploadErrors] = collect_student_uploads('profile_photo', 'id_proof', true, 'ID Proof');
    $errors += $uploadErrors;

    if (!$errors) {
        try {
            create_student_with_files($data, $uploads);
        } catch (RuntimeException $e) {
            $errors['form'] = $e->getMessage();
        }
    }
}

$success = !$errors;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $success ? 'Registration Successful' : 'Registration Failed' ?> | Student Admission Portal</title>
    <link rel="stylesheet" href="../Question1_Frontend/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="../Question1_Frontend/index.html" class="brand">
            <span class="brand-logo" aria-hidden="true">SP</span>
            <span>
                <span class="brand-title">Student Admission Portal</span>
                <span class="brand-subtitle">Academic Year 2026 – 2027</span>
            </span>
        </a>
        <nav class="header-nav">
            <a href="../Question1_Frontend/index.html">Home</a>
            <a href="../Question1_Frontend/register.html">Register</a>
        </nav>
    </div>
</header>

<main class="container" style="max-width:720px; padding-top:40px; padding-bottom:40px;">
    <section class="form-card">
        <?php if ($success): ?>
            <h2>Registration Successful</h2>
            <div class="alert alert-success" role="status">
                Thank you, <strong><?= e($data['name']) ?></strong>! Your application has been received and saved.
            </div>
            <dl class="summary-list">
                <dt>Roll Number</dt><dd><?= e($data['roll_number']) ?></dd>
                <dt>Email</dt><dd><?= e($data['email']) ?></dd>
                <dt>Course</dt><dd><?= e($data['course']) ?></dd>
                <dt>Files received</dt><dd><?= count($uploads) ?> (profile photo<?= count($uploads) > 1 ? ' + ID proof' : '' ?>)</dd>
            </dl>
            <div class="form-actions">
                <a class="btn btn-secondary" href="../Question1_Frontend/index.html">Home</a>
                <a class="btn btn-primary" href="../Question1_Frontend/register.html">Register Another Student</a>
            </div>
        <?php else: ?>
            <h2>Registration could not be completed</h2>
            <div class="alert alert-error" role="alert">
                <strong>The server found the following problems:</strong>
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= e($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="form-actions">
                <a class="btn btn-primary" href="../Question1_Frontend/register.html">Back to Registration Form</a>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
