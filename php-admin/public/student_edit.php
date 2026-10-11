<?php
/** Edit a student (Update of CRUD); optionally replace the profile photo. */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = Auth::requireAdmin();
$id = input_id($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);
if ($id === null) {
    throw new NotFoundException('Invalid student ID.');
}
$student = StudentRepository::findOrFail($id);
$courses = CourseRepository::all();
$errors = [];
$values = [
    'full_name'   => $student['full_name'],
    'roll_number' => $student['roll_number'],
    'email'       => $student['email'],
    'course'      => $student['course_code'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $values = array_map(static fn($v) => is_string($v) ? $v : '', $_POST);
    [$clean, $errors] = StudentValidator::validate($_POST, CourseRepository::codeMap());

    $service = new StudentService(new FileStorage());
    $validPhoto = null;
    if ($photo = StudentService::uploadedFile('profile_photo')) {
        [$valid, $uploadErrors] = $service->validateUploads([['field' => 'profile_photo', 'category' => 'profile_photo', 'file' => $photo]]);
        $errors += $uploadErrors;
        $validPhoto = $valid[0]['validated'] ?? null;
    }

    if (!$errors) {
        try {
            $service->update($id, $clean);
            if ($validPhoto !== null) {
                $service->addFile($id, 'profile_photo', $validPhoto, $user['id']);
            }
            flash('success', 'Changes to ' . $clean['full_name'] . ' were saved.');
            redirect('student_view.php', ['id' => $id]);
        } catch (ValidationException $e) {
            $errors = $e->errors;
        }
    }
}

$pageTitle = 'Edit ' . $student['full_name'];
$activeNav = 'students';
$breadcrumbs = [['students.php', 'Students'], ['student_view.php?id=' . $id, $student['full_name']]];
$mode = 'edit';
$action = 'student_edit.php';
require APP_ROOT . '/includes/views/layout_start.php';
require APP_ROOT . '/includes/views/student_form.php';
require APP_ROOT . '/includes/views/layout_end.php';
