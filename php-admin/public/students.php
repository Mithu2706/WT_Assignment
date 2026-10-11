<?php
/** Student list with search, course filter, sorting and pagination (View of CRUD). */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = Auth::requireAdmin();
$courses = CourseRepository::all();
$courseIds = array_column($courses, 'id', 'code');

// ---- read and sanitise the filter inputs ----
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$courseCode = is_string($_GET['course'] ?? null) ? $_GET['course'] : '';
$courseId = isset($courseIds[$courseCode]) ? (int) $courseIds[$courseCode] : null;
$sort = in_array($_GET['sort'] ?? '', ['newest', 'oldest', 'name', 'roll'], true) ? $_GET['sort'] : 'newest';
$page = max(1, input_id($_GET, 'page') ?? 1);
$perPage = 10;

$result = StudentRepository::search($q, $courseId, $page, $perPage, $sort);
$totalPages = max(1, (int) ceil($result['total'] / $perPage));
if ($page > $totalPages && $result['total'] > 0) {
    redirect('students.php', ['q' => $q, 'course' => $courseCode, 'sort' => $sort, 'page' => $totalPages]);
}
$filterQuery = array_filter(['q' => $q, 'course' => $courseCode, 'sort' => $sort !== 'newest' ? $sort : '']);

$pageTitle = 'Students';
$activeNav = 'students';
require APP_ROOT . '/includes/views/layout_start.php';
?>
<div class="toolbar">
    <form class="filters" method="get" action="students.php" role="search">
        <div class="field field-inline">
            <label for="q" class="visually-hidden">Search students</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Search name, roll number or email" maxlength="100">
        </div>
        <div class="field field-inline">
            <label for="course-filter" class="visually-hidden">Filter by course</label>
            <select id="course-filter" name="course">
                <option value="">All courses</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= e($c['code']) ?>" <?= $courseCode === $c['code'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field-inline">
            <label for="sort" class="visually-hidden">Sort by</label>
            <select id="sort" name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
                <option value="roll" <?= $sort === 'roll' ? 'selected' : '' ?>>Roll number</option>
            </select>
        </div>
        <button type="submit" class="btn btn-ghost">Apply</button>
        <?php if ($q !== '' || $courseId !== null): ?>
            <a class="link-muted" href="students.php">Clear</a>
        <?php endif; ?>
    </form>
    <a class="btn btn-primary" href="student_create.php">+ Add student</a>
</div>

<section class="card">
    <header class="panel-head panel-head-padded">
        <h2><?= e((string) $result['total']) ?> <?= $result['total'] === 1 ? 'student' : 'students' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?></h2>
    </header>

    <?php if (!$result['rows']): ?>
        <div class="empty">
            <p class="empty-title"><?= $q !== '' || $courseId !== null ? 'No students match your search' : 'No student records yet' ?></p>
            <p><?= $q !== '' || $courseId !== null ? 'Try a different name, roll number or course.' : 'Records added here or through the registration form will appear in this list.' ?></p>
            <a class="btn btn-primary btn-sm" href="student_create.php">Add student</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-students">
                <thead>
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Roll number</th>
                    <th scope="col">Course</th>
                    <th scope="col">Files</th>
                    <th scope="col">Created</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $s): ?>
                    <tr>
                        <td data-label="Student">
                            <div class="cell-person">
                                <?php if ($s['photo_id']): ?>
                                    <img class="avatar" src="file.php?id=<?= e((string) $s['photo_id']) ?>" alt="" loading="lazy" width="40" height="40">
                                <?php else: ?>
                                    <span class="avatar" aria-hidden="true"><?= e(initials($s['full_name'])) ?></span>
                                <?php endif; ?>
                                <div>
                                    <a href="student_view.php?id=<?= e((string) $s['id']) ?>" class="strong"><?= e($s['full_name']) ?></a>
                                    <small><?= e($s['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Roll number"><code><?= e($s['roll_number']) ?></code></td>
                        <td data-label="Course"><?= e($s['course_name']) ?></td>
                        <td data-label="Files"><span class="badge"><?= e((string) $s['file_count']) ?></span></td>
                        <td data-label="Created"><?= e(format_date($s['created_at'])) ?></td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm" href="student_view.php?id=<?= e((string) $s['id']) ?>">View</a>
                            <a class="btn btn-ghost btn-sm" href="student_edit.php?id=<?= e((string) $s['id']) ?>">Edit</a>
                            <a class="btn btn-danger-ghost btn-sm" href="student_delete.php?id=<?= e((string) $s['id']) ?>"
                               data-confirm-delete="student_delete.php" data-id="<?= e((string) $s['id']) ?>"
                               data-message="Delete <?= e($s['full_name']) ?> (<?= e($s['roll_number']) ?>) and all <?= e((string) $s['file_count']) ?> uploaded file(s)? This cannot be undone.">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="students.php?<?= e(http_build_query($filterQuery + ['page' => $page - 1])) ?>" rel="prev">← Previous</a>
                <?php endif; ?>
                <span>Page <?= e((string) $page) ?> of <?= e((string) $totalPages) ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="students.php?<?= e(http_build_query($filterQuery + ['page' => $page + 1])) ?>" rel="next">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/views/layout_end.php'; ?>
