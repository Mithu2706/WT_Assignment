<?php
/** Upload one photo/document for an existing student (POST, CSRF protected). */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = Auth::requireAdmin();
require_post();
Csrf::requireValid();

$studentId = input_id($_POST, 'student_id') ?? throw new NotFoundException('Invalid student ID.');
StudentRepository::findOrFail($studentId);
$category = is_string($_POST['category'] ?? null) ? $_POST['category'] : '';

$service = new StudentService(new FileStorage());
try {
    if (!FileStorage::isValidCategory($category)) {
        throw new UploadException('Choose a valid file type.');
    }
    $file = StudentService::uploadedFile('file') ?? throw new UploadException('Choose a file to upload.');
    [$valid, $errors] = $service->validateUploads([['field' => 'file', 'category' => $category, 'file' => $file]]);
    if ($errors) {
        throw new UploadException(reset($errors));
    }
    $service->addFile($studentId, $category, $valid[0]['validated'], $user['id']);
    flash('success', category_label($category) . ' “' . $valid[0]['validated']['original'] . '” uploaded.');
} catch (UploadException $e) {
    flash('error', 'Upload failed: ' . $e->getMessage());
}
redirect('student_view.php', ['id' => $studentId]);
