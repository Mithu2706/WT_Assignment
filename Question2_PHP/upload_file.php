<?php
/**
 * upload_file.php – FILE UPLOAD HANDLING (POST only).
 * ---------------------------------------------------------------------
 * Receives the upload form from view_student.php and:
 *   1. checks the CSRF token and that the student exists
 *   2. validates the file (upload error, size, extension, real MIME type)
 *   3. stores it under a random unique name (prevents overwriting)
 *   4. inserts the metadata (student id, path, type, size, upload date)
 *   5. for a new profile photo, removes the previous photo
 * and redirects back to the student page with a success or error message.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
require_post();

// A request bigger than post_max_size arrives with empty $_POST/$_FILES
if (post_body_too_large()) {
    set_flash('error', 'The file is too large for the server. Maximum sizes: 2 MB (photo), 5 MB (document).');
    redirect('dashboard.php');   // $_POST is empty, so the student id is unknown
}

$studentId = get_id_param('student_id', INPUT_POST);
if ($studentId === null) {
    render_error_page('Invalid student ID', 'The upload request did not contain a valid student ID.', 400);
}
$backUrl = 'view_student.php?id=' . $studentId . '#upload';

if (!verify_csrf()) {
    set_flash('error', 'Your form has expired. Please try the upload again.');
    redirect($backUrl);
}

try {
    $student = find_student($studentId);
} catch (PDOException $e) {
    error_log('Upload: student lookup failed: ' . $e->getMessage());
    render_error_page('Database error', 'The student record could not be loaded.');
}
if ($student === null) {
    render_error_page('Student not found', 'Cannot upload a file for a student that does not exist.', 404);
}

$category = $_POST['category'] ?? '';
if (!array_key_exists($category, UPLOAD_RULES)) {
    set_flash('error', 'Please choose a valid file category.');
    redirect($backUrl);
}
$label = in_array($_POST['document_label'] ?? '', DOCUMENT_LABELS, true) ? $_POST['document_label'] : 'Other';

$file = $_FILES['file'] ?? null;
if (!upload_was_attempted($file)) {
    set_flash('error', 'Please choose a file to upload.');
    redirect($backUrl);
}

$validated = validate_upload($file, $category);
if (!$validated['ok']) {
    set_flash('error', 'Upload failed: ' . $validated['error']);
    redirect($backUrl);
}

try {
    $stored = store_upload($studentId, $file, $category, $validated, $label);
    if ($category === 'profile') {
        remove_old_profile_photos($studentId, $stored['id']);
    }
    set_flash('success', sprintf(
        '"%s" (%s) was uploaded successfully as %s.',
        $validated['original_name'],
        format_bytes((int) $file['size']),
        $category === 'profile' ? 'the new profile photo' : 'a document (' . $label . ')'
    ));
} catch (RuntimeException $e) {
    set_flash('error', 'Upload failed: ' . $e->getMessage());
}

redirect($backUrl);
