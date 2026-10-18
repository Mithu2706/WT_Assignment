<?php
/**
 * add_student.php – CREATE part of CRUD.
 * ---------------------------------------------------------------------
 *  GET  → empty form (student details + optional photo + optional document)
 *  POST → 1. CSRF check
 *         2. server-side validation of every field (validate_student_data)
 *         3. duplicate roll number / e-mail check (prepared statement)
 *         4. file validation (type, MIME, size)
 *         5. INSERT student + move files + INSERT file metadata in one transaction
 *         6. redirect to the student's detail page with a success message
 *  Errors are shown next to the fields and the typed values are kept.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$data = [];
$errors = [];
$documentLabel = 'ID Proof';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_body_too_large()) {
        $errors['form'] = 'The uploaded files are too large for the server. Please choose smaller files.';
    } elseif (!verify_csrf()) {
        $errors['form'] = 'Your form has expired. Please submit it again.';
    } else {
        [$data, $errors] = validate_student_data($_POST, true);

        // Duplicate roll number / e-mail check (prepared SELECT)
        try {
            $errors = add_duplicate_errors($errors, $data);
        } catch (PDOException $e) {
            error_log('Duplicate check failed: ' . $e->getMessage());
            $errors['form'] = 'A database error occurred. Please try again later.';
        }

        $documentLabel = in_array($_POST['document_label'] ?? '', DOCUMENT_LABELS, true)
            ? $_POST['document_label'] : 'Other';
        [$uploads, $uploadErrors] = collect_student_uploads('profile_photo', 'document', false, $documentLabel);
        $errors += $uploadErrors;

        if (!$errors) {
            try {
                $studentId = create_student_with_files($data, $uploads);
                set_flash('success', 'Student "' . $data['name'] . '" was added successfully'
                    . ($uploads ? ' with ' . count($uploads) . ' file(s).' : '.'));
                redirect('view_student.php?id=' . $studentId);
            } catch (RuntimeException $e) {
                $errors['form'] = $e->getMessage();
            }
        }
    }
    if ($errors && !isset($errors['form'])) {
        $errors['form'] = 'Please correct the highlighted fields. (Selected files must be chosen again.)';
    }
}

$isEdit = false;
$pageTitle = 'Add Student';
$activePage = 'add';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Add New Student</h1>
        <p class="muted">Create a student record and optionally upload a profile photo and a document.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">&larr; Back to Dashboard</a>
</div>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<section class="card">
    <form method="post" action="add_student.php" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <h2 class="section-title">Student Details</h2>
        <?php require __DIR__ . '/includes/student_form.php'; ?>

        <h2 class="section-title">Files <span class="muted small">(optional)</span></h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="profile_photo">Profile Photo</label>
                <input type="file" id="profile_photo" name="profile_photo"
                       class="<?= isset($errors['profile_photo']) ? 'is-invalid' : '' ?>"
                       accept=".jpg,.jpeg,.png,.webp">
                <span class="hint">JPG, JPEG, PNG or WEBP – max 2 MB</span>
                <?php if (isset($errors['profile_photo'])): ?>
                    <small class="field-error"><?= e($errors['profile_photo']) ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="document">Document</label>
                <div class="input-row">
                    <select name="document_label" aria-label="Document type">
                        <?php foreach (DOCUMENT_LABELS as $label): ?>
                            <option value="<?= e($label) ?>" <?= $label === $documentLabel ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" id="document" name="document"
                           class="<?= isset($errors['document']) ? 'is-invalid' : '' ?>"
                           accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <span class="hint">PDF, JPG, JPEG or PNG – max 5 MB</span>
                <?php if (isset($errors['document'])): ?>
                    <small class="field-error"><?= e($errors['document']) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Student</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
