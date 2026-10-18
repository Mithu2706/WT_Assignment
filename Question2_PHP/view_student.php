<?php
/**
 * view_student.php – READ one student + DISPLAY UPLOADED FILES.
 * ---------------------------------------------------------------------
 *  - Loads the student with a prepared SELECT (id validated as an integer)
 *  - Loads all rows of student_files for that student (the metadata)
 *  - Shows the current profile photo through view_file.php (never the raw path)
 *  - Lists every file with type, size, upload date and View / Download / Delete
 *  - Contains the upload form (posts to upload_file.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$studentId = get_id_param('id');
if ($studentId === null) {
    render_error_page('Invalid student ID', 'The student ID in the address is missing or not a valid number.', 400);
}

try {
    $student = find_student($studentId);
    if ($student === null) {
        render_error_page('Student not found', 'No student exists with ID ' . $studentId . '. It may have been deleted.', 404);
    }

    $stmt = db()->prepare(
        'SELECT id, file_category, document_label, original_name, file_type, file_size, upload_date
         FROM student_files WHERE student_id = :sid
         ORDER BY file_category DESC, upload_date DESC, id DESC'
    );
    $stmt->execute([':sid' => $studentId]);
    $files = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('View student failed: ' . $e->getMessage());
    render_error_page('Database error', 'The student details could not be loaded.');
}

// The newest profile-category file is the current photo
$photo = null;
foreach ($files as $file) {
    if ($file['file_category'] === 'profile') {
        $photo = $file;
        break;
    }
}

/** Short label for the file-type badge, e.g. "PDF", "JPEG". */
function type_badge(string $mime): string
{
    return match ($mime) {
        'application/pdf' => 'PDF',
        'image/jpeg'      => 'JPEG',
        'image/png'       => 'PNG',
        'image/webp'      => 'WEBP',
        default           => 'FILE',
    };
}

$pageTitle = $student['name'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Student Details</h1>
        <p class="muted">Complete record and uploaded files.</p>
    </div>
    <div class="button-group">
        <a href="edit_student.php?id=<?= $studentId ?>" class="btn btn-secondary">Edit</a>
        <a href="delete_student.php?id=<?= $studentId ?>" class="btn btn-danger">Delete</a>
        <a href="dashboard.php" class="btn btn-secondary">&larr; Dashboard</a>
    </div>
</div>

<div class="profile-layout">
    <!-- Left: photo + key facts -->
    <section class="card profile-card">
        <?php if ($photo): ?>
            <img class="profile-photo" src="view_file.php?id=<?= (int) $photo['id'] ?>"
                 alt="Profile photo of <?= e($student['name']) ?>">
        <?php else: ?>
            <div class="profile-photo placeholder">No photo</div>
        <?php endif; ?>
        <h2><?= e($student['name']) ?></h2>
        <span class="badge"><?= e($student['roll_number']) ?></span>
        <p class="muted"><?= e($student['course']) ?></p>
    </section>

    <!-- Right: full details -->
    <section class="card">
        <h2 class="section-title">Personal Information</h2>
        <dl class="detail-list">
            <dt>Student ID</dt>     <dd>#<?= (int) $student['id'] ?></dd>
            <dt>Full Name</dt>      <dd><?= e($student['name']) ?></dd>
            <dt>Roll Number</dt>    <dd><?= e($student['roll_number']) ?></dd>
            <dt>Email</dt>          <dd><?= e($student['email']) ?></dd>
            <dt>Course</dt>         <dd><?= e($student['course']) ?></dd>
            <dt>Mobile</dt>         <dd><?= e($student['phone'] ?? '—') ?: '—' ?></dd>
            <dt>Date of Birth</dt>  <dd><?= $student['date_of_birth'] ? e(date('d M Y', strtotime($student['date_of_birth']))) : '—' ?></dd>
            <dt>Address</dt>        <dd><?= e($student['address'] ?? '') ?: '—' ?></dd>
            <dt>Registered On</dt>  <dd><?= e(date('d M Y, h:i A', strtotime($student['created_at']))) ?></dd>
        </dl>
    </section>
</div>

<!-- Uploaded files (metadata from the student_files table) -->
<section class="card">
    <h2 class="section-title">Uploaded Files <span class="count-pill"><?= count($files) ?></span></h2>

    <?php if (!$files): ?>
        <p class="empty">No files uploaded yet. Use the form below to upload a profile photo or document.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Preview</th>
                    <th>File Name</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Uploaded On</th>
                    <th class="right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td data-label="Preview">
                            <?php if (str_starts_with($file['file_type'], 'image/')): ?>
                                <img class="file-thumb" src="view_file.php?id=<?= (int) $file['id'] ?>" alt="">
                            <?php else: ?>
                                <span class="file-icon"><?= e(type_badge($file['file_type'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="File Name" class="break"><?= e($file['original_name']) ?></td>
                        <td data-label="Category">
                            <?= $file['file_category'] === 'profile'
                                ? '<span class="tag tag-photo">Profile Photo</span>'
                                : '<span class="tag tag-doc">' . e($file['document_label'] ?? 'Document') . '</span>' ?>
                        </td>
                        <td data-label="Type"><?= e(type_badge($file['file_type'])) ?> <span class="muted small">(<?= e($file['file_type']) ?>)</span></td>
                        <td data-label="Size"><?= e(format_bytes((int) $file['file_size'])) ?></td>
                        <td data-label="Uploaded On"><?= e(date('d M Y, h:i A', strtotime($file['upload_date']))) ?></td>
                        <td data-label="Actions" class="right actions">
                            <a class="btn btn-sm btn-info" href="view_file.php?id=<?= (int) $file['id'] ?>" target="_blank" rel="noopener">View</a>
                            <a class="btn btn-sm btn-secondary" href="view_file.php?id=<?= (int) $file['id'] ?>&amp;download=1">Download</a>
                            <form method="post" action="delete_file.php" class="inline-form"
                                  onsubmit="return confirm('Delete this file permanently?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Upload form -->
<section class="card" id="upload">
    <h2 class="section-title">Upload a File</h2>
    <form method="post" action="upload_file.php" enctype="multipart/form-data" class="upload-form">
        <?= csrf_field() ?>
        <input type="hidden" name="student_id" value="<?= $studentId ?>">

        <div class="form-group">
            <label for="category">File Category</label>
            <select id="category" name="category" required>
                <option value="profile">Profile Photo (JPG, PNG, WEBP · max 2 MB)</option>
                <option value="document" selected>Document (PDF, JPG, PNG · max 5 MB)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="document_label">Document Type</label>
            <select id="document_label" name="document_label">
                <?php foreach (DOCUMENT_LABELS as $label): ?>
                    <option value="<?= e($label) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group grow">
            <label for="file">Choose File</label>
            <input type="file" id="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp">
        </div>

        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
    <p class="muted small">Files are renamed with a random unique name on the server, so uploading a file with the
        same name never overwrites an existing file. A new profile photo replaces the previous one.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
