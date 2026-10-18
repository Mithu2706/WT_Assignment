<?php
/**
 * dashboard.php – Admin home page (READ part of CRUD).
 * ---------------------------------------------------------------------
 *  - Summary cards: total students, total files, photos, documents
 *  - Search by name / roll number / e-mail and filter by course
 *    (built with PREPARED statements – user input is never concatenated into SQL)
 *  - Student table with thumbnail, file count and View / Edit / Delete buttons
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$search = trim((string) ($_GET['q'] ?? ''));
$courseFilter = trim((string) ($_GET['course'] ?? ''));
if ($courseFilter !== '' && !in_array($courseFilter, COURSES, true)) {
    $courseFilter = '';     // ignore tampered filter values
}

try {
    // ---- Statistics ----
    $stats = db()->query(
        "SELECT
            (SELECT COUNT(*) FROM students)                                          AS total_students,
            (SELECT COUNT(*) FROM student_files)                                     AS total_files,
            (SELECT COUNT(*) FROM student_files WHERE file_category = 'profile')     AS total_photos,
            (SELECT COUNT(*) FROM student_files WHERE file_category = 'document')    AS total_documents,
            (SELECT COUNT(DISTINCT course) FROM students)                            AS total_courses"
    )->fetch();

    // ---- Student list with optional search/filter ----
    $where = [];
    $params = [];
    if ($search !== '') {
        // Separate placeholders because native prepared statements cannot reuse a name
        $where[] = '(s.name LIKE :q1 OR s.roll_number LIKE :q2 OR s.email LIKE :q3)';
        $like = '%' . $search . '%';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
    }
    if ($courseFilter !== '') {
        $where[] = 's.course = :course';
        $params[':course'] = $courseFilter;
    }

    $sql = "SELECT s.id, s.name, s.roll_number, s.email, s.course, s.created_at,
                   (SELECT COUNT(*) FROM student_files f WHERE f.student_id = s.id) AS file_count,
                   (SELECT p.id FROM student_files p
                     WHERE p.student_id = s.id AND p.file_category = 'profile'
                     ORDER BY p.upload_date DESC, p.id DESC LIMIT 1) AS photo_id
            FROM students s"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY s.roll_number ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Dashboard query failed: ' . $e->getMessage());
    render_error_page('Could not load students', 'A database error occurred while loading the student list.');
}

/** First letters of the first two words – used when a student has no photo. */
function initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $letters .= strtoupper($word[0] ?? '');
    }
    return $letters;
}

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p class="muted">Manage student records, profile photos and documents.</p>
    </div>
    <a href="add_student.php" class="btn btn-primary">+ Add Student</a>
</div>

<!-- Summary cards -->
<section class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Total Students</span>
        <span class="stat-value"><?= (int) $stats['total_students'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Uploaded Files</span>
        <span class="stat-value"><?= (int) $stats['total_files'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Profile Photos</span>
        <span class="stat-value"><?= (int) $stats['total_photos'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Documents</span>
        <span class="stat-value"><?= (int) $stats['total_documents'] ?></span>
    </div>
</section>

<!-- Search / filter form (GET so the URL can be bookmarked) -->
<section class="card">
    <form method="get" action="dashboard.php" class="filter-bar">
        <input type="search" name="q" value="<?= e($search) ?>" maxlength="100"
               placeholder="Search by name, roll number or email" aria-label="Search students">
        <select name="course" aria-label="Filter by course">
            <option value="">All courses</option>
            <?php foreach (COURSES as $course): ?>
                <option value="<?= e($course) ?>" <?= $course === $courseFilter ? 'selected' : '' ?>><?= e($course) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== '' || $courseFilter !== ''): ?>
            <a href="dashboard.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
            <tr>
                <th>Student</th>
                <th>Roll Number</th>
                <th>Course</th>
                <th>Email</th>
                <th class="center">Files</th>
                <th class="right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$students): ?>
                <tr><td colspan="6" class="empty">No students found<?= $search !== '' || $courseFilter !== '' ? ' for this search' : '' ?>.</td></tr>
            <?php endif; ?>

            <?php foreach ($students as $student): ?>
                <tr>
                    <td data-label="Student">
                        <div class="student-cell">
                            <?php if ($student['photo_id']): ?>
                                <img class="avatar" src="view_file.php?id=<?= (int) $student['photo_id'] ?>"
                                     alt="Photo of <?= e($student['name']) ?>">
                            <?php else: ?>
                                <span class="avatar avatar-initials"><?= e(initials($student['name'])) ?></span>
                            <?php endif; ?>
                            <strong><?= e($student['name']) ?></strong>
                        </div>
                    </td>
                    <td data-label="Roll Number"><span class="badge"><?= e($student['roll_number']) ?></span></td>
                    <td data-label="Course"><?= e($student['course']) ?></td>
                    <td data-label="Email"><?= e($student['email']) ?></td>
                    <td data-label="Files" class="center"><?= (int) $student['file_count'] ?></td>
                    <td data-label="Actions" class="right actions">
                        <a class="btn btn-sm btn-info" href="view_student.php?id=<?= (int) $student['id'] ?>">View</a>
                        <a class="btn btn-sm btn-secondary" href="edit_student.php?id=<?= (int) $student['id'] ?>">Edit</a>
                        <a class="btn btn-sm btn-danger" href="delete_student.php?id=<?= (int) $student['id'] ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted small">Showing <?= count($students) ?> of <?= (int) $stats['total_students'] ?> students.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
