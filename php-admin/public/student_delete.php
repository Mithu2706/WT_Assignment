<?php
/**
 * Delete a student (Delete of CRUD).
 * GET  -> confirmation page (works without JavaScript)
 * POST -> CSRF check, delete row (metadata cascades) + only this student's files
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = input_id($_POST) ?? throw new NotFoundException('Invalid student ID.');
    $service = new StudentService(new FileStorage());
    $deleted = $service->deleteStudent($id);
    flash('success', 'Deleted ' . $deleted['full_name'] . ' (' . $deleted['roll_number'] . ') and their files.');
    redirect('students.php');
}

$id = input_id($_GET) ?? throw new NotFoundException('Invalid student ID.');
$student = StudentRepository::findOrFail($id);
$fileCount = count(FileRepository::forStudent($id));

$pageTitle = 'Delete student';
$activeNav = 'students';
$breadcrumbs = [['students.php', 'Students'], ['student_view.php?id=' . $id, $student['full_name']]];
require APP_ROOT . '/includes/views/layout_start.php';
?>
<section class="card confirm-card">
    <h2>Delete <?= e($student['full_name']) ?>?</h2>
    <p>This permanently removes the record for roll number <code><?= e($student['roll_number']) ?></code>,
       <?= e((string) $fileCount) ?> uploaded file(s) and the student's portal login (if any). This cannot be undone.</p>
    <form method="post" action="student_delete.php" class="form-actions">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
        <a class="btn btn-ghost" href="student_view.php?id=<?= e((string) $id) ?>">Cancel</a>
        <button type="submit" class="btn btn-danger">Delete permanently</button>
    </form>
</section>
<?php require APP_ROOT . '/includes/views/layout_end.php'; ?>
