<?php
/**
 * delete_student.php – DELETE part of CRUD.
 * ---------------------------------------------------------------------
 *  GET  ?id=N → confirmation page showing the student and how many files
 *               will be removed (nothing is deleted yet)
 *  POST       → CSRF check, then inside a transaction:
 *                 - read the student's file paths
 *                 - DELETE the student (ON DELETE CASCADE removes the
 *                   student_files metadata rows automatically)
 *               after COMMIT the physical files are removed from disk.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$studentId = $isPost ? get_id_param('id', INPUT_POST) : get_id_param('id');
if ($studentId === null) {
    render_error_page('Invalid student ID', 'The student ID is missing or not a valid number.', 400);
}

try {
    $student = find_student($studentId);
    if ($student === null) {
        render_error_page('Student not found', 'No student exists with ID ' . $studentId . '.', 404);
    }
    $fileStmt = db()->prepare('SELECT file_path, original_name, file_category FROM student_files WHERE student_id = :id');
    $fileStmt->execute([':id' => $studentId]);
    $files = $fileStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Load student for delete failed: ' . $e->getMessage());
    render_error_page('Database error', 'The student record could not be loaded.');
}

if ($isPost) {
    if (!verify_csrf()) {
        render_error_page('Invalid request', 'Your form has expired. Please go back and try again.', 400);
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM students WHERE id = :id');
        $delete->execute([':id' => $studentId]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Delete student failed: ' . $e->getMessage());
        set_flash('error', 'The student could not be deleted because of a database error.');
        redirect('dashboard.php');
    }

    // Database rows are gone – now remove the physical files
    foreach ($files as $file) {
        delete_physical_file($file['file_path']);
    }

    set_flash('success', 'Student "' . $student['name'] . '" and ' . count($files) . ' associated file(s) were deleted.');
    redirect('dashboard.php');
}

$pageTitle = 'Delete Student';
require __DIR__ . '/includes/header.php';
?>

<section class="card confirm-card">
    <div class="confirm-icon" aria-hidden="true">!</div>
    <h1>Delete this student?</h1>
    <p>You are about to permanently delete the following record. This action <strong>cannot be undone</strong>.</p>

    <dl class="detail-list compact">
        <dt>Name</dt><dd><?= e($student['name']) ?></dd>
        <dt>Roll Number</dt><dd><?= e($student['roll_number']) ?></dd>
        <dt>Course</dt><dd><?= e($student['course']) ?></dd>
        <dt>Email</dt><dd><?= e($student['email']) ?></dd>
        <dt>Files</dt><dd><?= count($files) ?> uploaded file(s) will also be deleted from the server</dd>
    </dl>

    <?php if ($files): ?>
        <ul class="file-mini-list">
            <?php foreach ($files as $file): ?>
                <li><?= e($file['original_name']) ?> <span class="muted">(<?= e($file['file_category']) ?>)</span></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="delete_student.php" class="form-actions center">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $studentId ?>">
        <a href="view_student.php?id=<?= $studentId ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-danger">Yes, Delete Student</button>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
