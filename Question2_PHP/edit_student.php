<?php
/**
 * edit_student.php – UPDATE part of CRUD.
 * ---------------------------------------------------------------------
 *  GET  ?id=N → load the existing record (prepared SELECT) into the form
 *  POST       → validate again on the server, make sure the new roll number
 *               / e-mail do not belong to ANOTHER student, then run a
 *               prepared UPDATE. The password is only changed when a new
 *               one is typed (blank = keep current password).
 *  Files are managed on the student's View page (upload / delete).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

// The id comes from the URL on GET and from a hidden field on POST
$studentId = $_SERVER['REQUEST_METHOD'] === 'POST' ? get_id_param('id', INPUT_POST) : get_id_param('id');
if ($studentId === null) {
    render_error_page('Invalid student ID', 'The student ID in the address is missing or not a valid number.', 400);
}

try {
    $student = find_student($studentId);
} catch (PDOException $e) {
    error_log('Load student failed: ' . $e->getMessage());
    render_error_page('Database error', 'The student record could not be loaded.');
}
if ($student === null) {
    render_error_page('Student not found', 'No student exists with ID ' . $studentId . '. It may have been deleted.', 404);
}

$data = $student;      // pre-fill the form with the current values
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors['form'] = 'Your form has expired. Please submit it again.';
    } else {
        [$data, $errors] = validate_student_data($_POST, false);

        // Roll number / e-mail must not belong to ANOTHER student
        try {
            $errors = add_duplicate_errors($errors, $data, $studentId);
        } catch (PDOException $e) {
            error_log('Duplicate check failed: ' . $e->getMessage());
            $errors['form'] = 'A database error occurred. Please try again later.';
        }

        if (!$errors) {
            try {
                $sql = 'UPDATE students
                           SET name = :name, roll_number = :roll, email = :email, course = :course,
                               phone = :phone, date_of_birth = :dob, address = :address'
                     . ($data['password'] !== '' ? ', password_hash = :hash' : '')
                     . ' WHERE id = :id';
                $params = [
                    ':name'    => $data['name'],
                    ':roll'    => $data['roll_number'],
                    ':email'   => $data['email'],
                    ':course'  => $data['course'],
                    ':phone'   => null_if_empty($data['phone']),
                    ':dob'     => null_if_empty($data['date_of_birth']),
                    ':address' => null_if_empty($data['address']),
                    ':id'      => $studentId,
                ];
                if ($data['password'] !== '') {
                    $params[':hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                $stmt = db()->prepare($sql);
                $stmt->execute($params);

                set_flash('success', 'Student "' . $data['name'] . '" was updated successfully.');
                redirect('view_student.php?id=' . $studentId);
            } catch (PDOException $e) {
                error_log('Update student failed: ' . $e->getMessage());
                $errors['form'] = $e->getCode() === '23000'
                    ? 'Another student already uses this roll number or email.'
                    : 'The student could not be updated because of a database error.';
            }
        }
    }
    if ($errors && !isset($errors['form'])) {
        $errors['form'] = 'Please correct the highlighted fields.';
    }
}

$isEdit = true;
$pageTitle = 'Edit Student';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Student</h1>
        <p class="muted">Updating record of <strong><?= e($student['name']) ?></strong>
            (<?= e($student['roll_number']) ?>) · last updated <?= e(date('d M Y, h:i A', strtotime($student['updated_at']))) ?></p>
    </div>
    <div class="button-group">
        <a href="view_student.php?id=<?= $studentId ?>" class="btn btn-info">View Profile</a>
        <a href="dashboard.php" class="btn btn-secondary">&larr; Dashboard</a>
    </div>
</div>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<section class="card">
    <form method="post" action="edit_student.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $studentId ?>">

        <h2 class="section-title">Student Details</h2>
        <?php require __DIR__ . '/includes/student_form.php'; ?>

        <p class="muted small">To change the profile photo or documents, use the
            <a href="view_student.php?id=<?= $studentId ?>#upload">upload section on the View page</a>.</p>

        <div class="form-actions">
            <a href="view_student.php?id=<?= $studentId ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Student</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
