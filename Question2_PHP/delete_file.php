<?php
/**
 * delete_file.php – deletes ONE uploaded file (POST only).
 * Removes the metadata row from student_files and the physical file
 * from the uploads folder, then returns to the student's page.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
require_post();

$fileId = get_id_param('file_id', INPUT_POST);
if ($fileId === null) {
    render_error_page('Invalid file ID', 'The file ID is missing or not a valid number.', 400);
}

try {
    $stmt = db()->prepare('SELECT id, student_id, original_name, file_path FROM student_files WHERE id = :id');
    $stmt->execute([':id' => $fileId]);
    $file = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Delete file lookup failed: ' . $e->getMessage());
    render_error_page('Database error', 'The file record could not be loaded.');
}

if (!$file) {
    set_flash('error', 'The file was not found. It may already have been deleted.');
    redirect('dashboard.php');
}

$backUrl = 'view_student.php?id=' . (int) $file['student_id'];

if (!verify_csrf()) {
    set_flash('error', 'Your form has expired. Please try again.');
    redirect($backUrl);
}

try {
    $delete = db()->prepare('DELETE FROM student_files WHERE id = :id');
    $delete->execute([':id' => $fileId]);
    delete_physical_file($file['file_path']);
    set_flash('success', 'File "' . $file['original_name'] . '" was deleted.');
} catch (PDOException $e) {
    error_log('Delete file failed: ' . $e->getMessage());
    set_flash('error', 'The file could not be deleted because of a database error.');
}

redirect($backUrl);
