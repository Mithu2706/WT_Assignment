<?php
/**
 * Shared add/edit student form.
 * Expects: $mode ('create'|'edit'), $values (array), $errors (array), $courses (list),
 *          $action (string), $student (array|null when editing)
 */
declare(strict_types=1);
$v = static fn(string $key): string => (string) ($values[$key] ?? '');
$err = static fn(string $key): string => (string) ($errors[$key] ?? '');
$invalid = static fn(string $key): string => isset($errors[$key]) ? ' aria-invalid="true"' : '';
?>
<?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        <strong>Please correct the following:</strong>
        <ul>
            <?php foreach ($errors as $field => $message): ?>
                <li><a href="#<?= e($field) ?>"><?= e($message) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card form-card" data-prevent-double-submit>
    <?= Csrf::field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e((string) $student['id']) ?>">
    <?php endif; ?>
    <!-- Lets the browser refuse very large files early; the server re-checks every size. -->
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= FileStorage::MAX_DOCUMENT_BYTES ?>">

    <fieldset>
        <legend>Student details</legend>
        <div class="grid-2">
            <div class="field">
                <label for="full_name">Full name <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="full_name" name="full_name" value="<?= e($v('full_name')) ?>"
                       required minlength="2" maxlength="100" autocomplete="off"<?= $invalid('full_name') ?>
                       aria-describedby="full_name-error">
                <p class="field-error" id="full_name-error"><?= e($err('full_name')) ?></p>
            </div>
            <div class="field">
                <label for="roll_number">Roll number <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="roll_number" name="roll_number" value="<?= e($v('roll_number')) ?>"
                       required pattern="[A-Za-z0-9]{5,15}" maxlength="15" autocapitalize="characters"<?= $invalid('roll_number') ?>
                       aria-describedby="roll_number-hint roll_number-error">
                <p class="hint" id="roll_number-hint">5–15 letters or digits; must be unique.</p>
                <p class="field-error" id="roll_number-error"><?= e($err('roll_number')) ?></p>
            </div>
            <div class="field">
                <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" value="<?= e($v('email')) ?>"
                       required maxlength="150"<?= $invalid('email') ?> aria-describedby="email-error">
                <p class="field-error" id="email-error"><?= e($err('email')) ?></p>
            </div>
            <div class="field">
                <label for="course">Course <span class="req" aria-hidden="true">*</span></label>
                <select id="course" name="course" required<?= $invalid('course') ?> aria-describedby="course-error">
                    <option value="">Select a course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= e($course['code']) ?>" <?= $v('course') === $course['code'] ? 'selected' : '' ?>>
                            <?= e($course['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error" id="course-error"><?= e($err('course')) ?></p>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><?= $mode === 'edit' ? 'Replace profile photo (optional)' : 'Files (optional)' ?></legend>
        <div class="grid-2">
            <div class="field">
                <label for="profile_photo">Profile photo</label>
                <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp"
                       <?= $invalid('profile_photo') ?> aria-describedby="profile_photo-hint profile_photo-error">
                <p class="hint" id="profile_photo-hint"><?= e(FileStorage::hintFor('profile_photo')) ?><?= $mode === 'edit' ? ' · replaces the current photo' : '' ?></p>
                <p class="field-error" id="profile_photo-error"><?= e($err('profile_photo')) ?></p>
            </div>
            <?php if ($mode === 'create'): ?>
                <div class="field">
                    <label for="document">Document</label>
                    <input type="file" id="document" name="document" accept="application/pdf,image/jpeg,image/png,image/webp"
                           <?= $invalid('document') ?> aria-describedby="document-hint document-error">
                    <p class="hint" id="document-hint"><?= e(FileStorage::hintFor('id_proof')) ?></p>
                    <p class="field-error" id="document-error"><?= e($err('document')) ?></p>
                    <label for="document_category" class="sub-label">Document type</label>
                    <select id="document_category" name="document_category">
                        <?php foreach (FileStorage::CATEGORY_LABELS as $key => $label): if ($key === 'profile_photo') continue; ?>
                            <option value="<?= e($key) ?>" <?= $v('document_category') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </fieldset>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= e($mode === 'edit' ? 'student_view.php?id=' . $student['id'] : 'students.php') ?>">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <span class="spinner" aria-hidden="true"></span>
            <span class="btn-label"><?= $mode === 'edit' ? 'Save changes' : 'Add student' ?></span>
        </button>
    </div>
</form>
