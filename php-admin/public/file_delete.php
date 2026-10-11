<?php
/** Delete one uploaded file (metadata row + physical file). POST + CSRF only. */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = Auth::requireAdmin();
require_post();
Csrf::requireValid();

$fileId = input_id($_POST) ?? throw new NotFoundException('Invalid file ID.');
$service = new StudentService(new FileStorage());
$studentId = $service->deleteFile($fileId);
flash('success', 'The file was deleted.');
redirect('student_view.php', ['id' => $studentId]);
