<?php
/** Student profile page: details, profile photo, documents and upload form. */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = Auth::requireAdmin();
$id = input_id($_GET);
if ($id === null) {
    throw new NotFoundException('Invalid student ID.');
}
$student = StudentRepository::findOrFail($id);
$files = FileRepository::forStudent($id);
$login = UserRepository::forStudent($id);

// Split files: the newest profile photo becomes the avatar; images and PDFs are listed separately.
$photo = null;
$images = [];
$documents = [];
foreach ($files as $f) {
    if ($f['category'] === 'profile_photo' && $photo === null) {
        $photo = $f;
    }
    if (str_starts_with($f['mime_type'], 'image/')) {
        $images[] = $f;
    }
    if ($f['category'] !== 'profile_photo') {
        $documents[] = $f;
    }
}
// Check that each file really exists on disk so missing files are flagged, not broken.
$storage = new FileStorage();
$missing = [];
foreach ($files as $f) {
    if ($storage->resolve($f['relative_path'], $id) === null) {
        $missing[(int) $f['id']] = true;
    }
}

$pageTitle = $student['full_name'];
$activeNav = 'students';
$breadcrumbs = [['students.php', 'Students']];
require APP_ROOT . '/includes/views/layout_start.php';
?>
<section class="card profile-head">
    <div class="profile-photo">
        <?php if ($photo && !isset($missing[(int) $photo['id']])): ?>
            <img src="file.php?id=<?= e((string) $photo['id']) ?>" alt="Profile photo of <?= e($student['full_name']) ?>" width="128" height="128">
        <?php else: ?>
            <span class="avatar avatar-xl" aria-hidden="true"><?= e(initials($student['full_name'])) ?></span>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <p class="eyebrow"><?= e($student['course_name']) ?></p>
        <h2><?= e($student['full_name']) ?></h2>
        <dl class="meta">
            <div><dt>Student ID</dt><dd><?= e((string) $student['id']) ?></dd></div>
            <div><dt>Roll number</dt><dd><code><?= e($student['roll_number']) ?></code></dd></div>
            <div><dt>Email</dt><dd><a href="mailto:<?= e($student['email']) ?>"><?= e($student['email']) ?></a></dd></div>
            <div><dt>Created</dt><dd><?= e(format_date($student['created_at'], 'd M Y, H:i')) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= e(format_date($student['updated_at'], 'd M Y, H:i')) ?></dd></div>
            <div><dt>Source</dt><dd><?= $student['registration_source'] === 'self' ? 'Online registration' : 'Added by admin' ?></dd></div>
            <div><dt>Portal login</dt><dd><?= $login ? 'Yes (' . e($login['username']) . ')' : 'No' ?></dd></div>
        </dl>
    </div>
    <div class="profile-actions">
        <a class="btn btn-primary" href="student_edit.php?id=<?= e((string) $id) ?>">Edit details</a>
        <a class="btn btn-danger-ghost" href="student_delete.php?id=<?= e((string) $id) ?>"
           data-confirm-delete="student_delete.php" data-id="<?= e((string) $id) ?>"
           data-message="Delete <?= e($student['full_name']) ?> and all <?= e((string) count($files)) ?> uploaded file(s)? This cannot be undone.">Delete student</a>
    </div>
</section>

<div class="profile-grid">
    <section class="card panel">
        <header class="panel-head">
            <h2>Documents <span class="count"><?= e((string) count($documents)) ?></span></h2>
        </header>
        <?php if (!$documents): ?>
            <div class="empty">
                <p class="empty-title">No documents uploaded</p>
                <p>Use the form to add an ID proof, certificate or marksheet.</p>
            </div>
        <?php else: ?>
            <ul class="doc-list">
                <?php foreach ($documents as $d): $isMissing = isset($missing[(int) $d['id']]); ?>
                    <li class="<?= $isMissing ? 'is-missing' : '' ?>">
                        <span class="doc-icon <?= $d['mime_type'] === 'application/pdf' ? 'pdf' : 'img' ?>" aria-hidden="true">
                            <?= $d['mime_type'] === 'application/pdf' ? 'PDF' : 'IMG' ?>
                        </span>
                        <div class="doc-body">
                            <strong><?= e($d['original_name']) ?></strong>
                            <small><?= e(category_label($d['category'])) ?> · <?= e(format_bytes((int) $d['file_size'])) ?> · <?= e(format_date($d['uploaded_at'], 'd M Y, H:i')) ?></small>
                            <?php if ($isMissing): ?><small class="text-danger">File missing from storage</small><?php endif; ?>
                        </div>
                        <div class="doc-actions">
                            <?php if (!$isMissing): ?>
                                <a class="btn btn-ghost btn-sm" href="file.php?id=<?= e((string) $d['id']) ?>" target="_blank" rel="noopener">View</a>
                                <a class="btn btn-ghost btn-sm" href="file.php?id=<?= e((string) $d['id']) ?>&amp;download=1">Download</a>
                            <?php endif; ?>
                            <form method="post" action="file_delete.php" data-confirm="Delete “<?= e($d['original_name']) ?>”? This cannot be undone.">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $d['id']) ?>">
                                <button type="submit" class="btn btn-danger-ghost btn-sm">Delete</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card panel">
        <header class="panel-head"><h2>Upload a file</h2></header>
        <form method="post" action="file_upload.php" enctype="multipart/form-data" class="upload-form" data-prevent-double-submit>
            <?= Csrf::field() ?>
            <input type="hidden" name="student_id" value="<?= e((string) $id) ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= FileStorage::MAX_DOCUMENT_BYTES ?>">
            <div class="field">
                <label for="category">File type</label>
                <select id="category" name="category" required>
                    <?php foreach (FileStorage::CATEGORY_LABELS as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="file">File</label>
                <input type="file" id="file" name="file" required accept="application/pdf,image/jpeg,image/png,image/webp" aria-describedby="file-hint">
                <p class="hint" id="file-hint">Photos: JPG/PNG/WebP up to 2 MB (replaces the current photo). Documents: PDF/JPG/PNG/WebP up to 5 MB.</p>
            </div>
            <button type="submit" class="btn btn-primary">
                <span class="spinner" aria-hidden="true"></span>
                <span class="btn-label">Upload</span>
            </button>
        </form>
    </section>

    <section class="card panel panel-wide">
        <header class="panel-head"><h2>Images <span class="count"><?= e((string) count($images)) ?></span></h2></header>
        <?php if (!$images): ?>
            <div class="empty"><p class="empty-title">No images uploaded</p><p>Profile photos and scanned documents appear here.</p></div>
        <?php else: ?>
            <div class="gallery">
                <?php foreach ($images as $img): ?>
                    <figure>
                        <?php if (isset($missing[(int) $img['id']])): ?>
                            <div class="gallery-missing">File missing</div>
                        <?php else: ?>
                            <a href="file.php?id=<?= e((string) $img['id']) ?>" target="_blank" rel="noopener">
                                <img src="file.php?id=<?= e((string) $img['id']) ?>" alt="<?= e(category_label($img['category'])) ?> of <?= e($student['full_name']) ?>" loading="lazy">
                            </a>
                        <?php endif; ?>
                        <figcaption><?= e(category_label($img['category'])) ?><small><?= e($img['original_name']) ?></small></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require APP_ROOT . '/includes/views/layout_end.php'; ?>
