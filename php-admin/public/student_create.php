<?php
/** Add a student (Create of CRUD) with optional profile photo and document. */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = Auth::requireAdmin();
$courses = CourseRepository::all();
$errors = [];
$values = ['document_category' => 'id_proof'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::requireValid();
    $values = array_map(static fn($v) => is_string($v) ? $v : '', $_POST);

    // 1) validate text fields on the server
    [$clean, $errors] = StudentValidator::validate($_POST, CourseRepository::codeMap());

    // 2) validate files (type/size/content) before touching the database
    $uploads = [];
    if ($photo = StudentService::uploadedFile('profile_photo')) {
        $uploads[] = ['field' => 'profile_photo', 'category' => 'profile_photo', 'file' => $photo];
    }
    if ($doc = StudentService::uploadedFile('document')) {
        $category = (string) ($values['document_category'] ?? '');
        if ($category === 'profile_photo' || !FileStorage::isValidCategory($category)) {
            $errors['document'] = 'Choose a valid document type.';
        } else {
            $uploads[] = ['field' => 'document', 'category' => $category, 'file' => $doc];
        }
    }
    $service = new StudentService(new FileStorage());
    [$validUploads, $uploadErrors] = $service->validateUploads($uploads);
    $errors += $uploadErrors;

    // 3) save everything in one transaction
    if (!$errors) {
        try {
            $id = $service->create($clean, 'admin', $validUploads, $user['id']);
            flash('success', 'Student ' . $clean['full_name'] . ' (' . $clean['roll_number'] . ') was added.');
            redirect('student_view.php', ['id' => $id]);
        } catch (ValidationException $e) {
            $errors = $e->errors;
        }
    }
    if ($errors && $uploads) {
        // Browsers cannot keep a chosen file after a failed submit - tell the user.
        flash('warning', 'For security, selected files are not kept after an error. Please choose them again.');
    }
}

$pageTitle = 'Add student';
$activeNav = 'create';
$breadcrumbs = [['students.php', 'Students']];
$mode = 'create';
$action = 'student_create.php';
$student = null;
require APP_ROOT . '/includes/views/layout_start.php';
require APP_ROOT . '/includes/views/student_form.php';
require APP_ROOT . '/includes/views/layout_end.php';
