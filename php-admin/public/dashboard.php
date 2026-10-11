<?php
/** Admin dashboard: headline numbers, course distribution, recent activity. */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = Auth::requireAdmin();
$stats = StudentRepository::stats();
$recentFiles = FileRepository::recent(5);
$maxCourse = max(1, ...array_map(static fn($c) => (int) $c['total'], $stats['by_course'] ?: [['total' => 1]]));

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require APP_ROOT . '/includes/views/layout_start.php';
?>
<section class="stats" aria-label="Summary">
    <article class="stat card">
        <p class="stat-label">Students</p>
        <p class="stat-value"><?= e((string) $stats['students']) ?></p>
        <p class="stat-note"><?= e((string) $stats['self']) ?> self-registered</p>
    </article>
    <article class="stat card">
        <p class="stat-label">Uploaded files</p>
        <p class="stat-value"><?= e((string) $stats['files']) ?></p>
        <p class="stat-note"><?= e(format_bytes($stats['bytes'])) ?> stored</p>
    </article>
    <article class="stat card">
        <p class="stat-label">Missing photos</p>
        <p class="stat-value"><?= e((string) $stats['without_photo']) ?></p>
        <p class="stat-note">students without a profile photo</p>
    </article>
    <article class="stat card stat-action">
        <p class="stat-label">Quick action</p>
        <a class="btn btn-primary" href="student_create.php">Add a student</a>
        <a class="link-muted" href="students.php">Browse all records →</a>
    </article>
</section>

<div class="dash-grid">
    <section class="card panel">
        <header class="panel-head">
            <h2>Recently added students</h2>
            <a href="students.php">View all</a>
        </header>
        <?php if (!$stats['recent']): ?>
            <div class="empty">
                <p class="empty-title">No students yet</p>
                <p>Add the first record or share the registration form.</p>
                <a class="btn btn-primary btn-sm" href="student_create.php">Add student</a>
            </div>
        <?php else: ?>
            <ul class="person-list">
                <?php foreach ($stats['recent'] as $s): ?>
                    <li>
                        <span class="avatar" aria-hidden="true"><?= e(initials($s['full_name'])) ?></span>
                        <div>
                            <a href="student_view.php?id=<?= e((string) $s['id']) ?>"><?= e($s['full_name']) ?></a>
                            <small><?= e($s['roll_number']) ?> · <?= e($s['course_name']) ?></small>
                        </div>
                        <span class="badge <?= $s['registration_source'] === 'self' ? 'badge-teal' : '' ?>">
                            <?= $s['registration_source'] === 'self' ? 'Self-registered' : 'Admin' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card panel">
        <header class="panel-head"><h2>Students per course</h2></header>
        <ul class="bars">
            <?php foreach ($stats['by_course'] as $c): $pct = (int) round(((int) $c['total'] / $maxCourse) * 100); ?>
                <li>
                    <span class="bar-label"><?= e($c['name']) ?></span>
                    <span class="bar"><span class="bar-fill w-<?= e((string) (int) (round($pct / 5) * 5)) ?>"></span></span>
                    <span class="bar-value"><?= e((string) $c['total']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card panel panel-wide">
        <header class="panel-head"><h2>Latest uploads</h2></header>
        <?php if (!$recentFiles): ?>
            <div class="empty"><p class="empty-title">No files uploaded yet</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">File</th><th scope="col">Student</th><th scope="col">Type</th><th scope="col">Size</th><th scope="col">Uploaded</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentFiles as $f): ?>
                        <tr>
                            <td><a href="file.php?id=<?= e((string) $f['id']) ?>" target="_blank" rel="noopener"><?= e($f['original_name']) ?></a></td>
                            <td><a href="student_view.php?id=<?= e((string) $f['student_id']) ?>"><?= e($f['full_name']) ?></a></td>
                            <td><span class="badge"><?= e(category_label($f['category'])) ?></span></td>
                            <td><?= e(format_bytes((int) $f['file_size'])) ?></td>
                            <td><?= e(format_date($f['uploaded_at'], 'd M Y, H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require APP_ROOT . '/includes/views/layout_end.php'; ?>
