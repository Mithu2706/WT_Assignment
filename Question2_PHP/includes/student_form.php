<?php
/**
 * includes/student_form.php
 * ---------------------------------------------------------------------
 * Shared form fields used by BOTH add_student.php and edit_student.php
 * (avoids writing the same HTML twice).
 *
 * Variables expected from the including page:
 *   $data   – current field values (old input or the record from MySQL)
 *   $errors – validation errors keyed by field name
 *   $isEdit – true on the edit page (password becomes optional)
 */

/** Print the CSS class "is-invalid" for a field that has an error. */
$invalid = static fn(string $field): string => isset($errors[$field]) ? ' is-invalid' : '';

/** Print the error message under a field (escaped). */
$errorFor = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<small class="field-error">' . e($errors[$field]) . '</small>' : '';
};
?>
<div class="form-grid">
    <div class="form-group">
        <label for="name">Full Name <span class="required">*</span></label>
        <input type="text" id="name" name="name" class="<?= $invalid('name') ?>" maxlength="100"
               value="<?= e($data['name'] ?? '') ?>" required>
        <?= $errorFor('name') ?>
    </div>

    <div class="form-group">
        <label for="roll_number">Roll Number <span class="required">*</span></label>
        <input type="text" id="roll_number" name="roll_number" class="<?= $invalid('roll_number') ?>" maxlength="15"
               value="<?= e($data['roll_number'] ?? '') ?>" placeholder="e.g. 23CS101" required>
        <?= $errorFor('roll_number') ?>
    </div>

    <div class="form-group">
        <label for="email">Email <span class="required">*</span></label>
        <input type="email" id="email" name="email" class="<?= $invalid('email') ?>" maxlength="150"
               value="<?= e($data['email'] ?? '') ?>" required>
        <?= $errorFor('email') ?>
    </div>

    <div class="form-group">
        <label for="course">Course <span class="required">*</span></label>
        <select id="course" name="course" class="<?= $invalid('course') ?>" required>
            <option value="">-- Select course --</option>
            <?php foreach (COURSES as $course): ?>
                <option value="<?= e($course) ?>" <?= ($data['course'] ?? '') === $course ? 'selected' : '' ?>>
                    <?= e($course) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= $errorFor('course') ?>
    </div>

    <div class="form-group">
        <label for="phone">Mobile Number</label>
        <input type="tel" id="phone" name="phone" class="<?= $invalid('phone') ?>" maxlength="10"
               value="<?= e($data['phone'] ?? '') ?>" placeholder="10-digit number">
        <?= $errorFor('phone') ?>
    </div>

    <div class="form-group">
        <label for="date_of_birth">Date of Birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth" class="<?= $invalid('date_of_birth') ?>"
               value="<?= e($data['date_of_birth'] ?? '') ?>">
        <?= $errorFor('date_of_birth') ?>
    </div>

    <div class="form-group full-width">
        <label for="address">Address</label>
        <textarea id="address" name="address" rows="2" maxlength="255"
                  class="<?= $invalid('address') ?>"><?= e($data['address'] ?? '') ?></textarea>
        <?= $errorFor('address') ?>
    </div>

    <div class="form-group">
        <label for="password">
            <?= $isEdit ? 'New Password' : 'Portal Password <span class="required">*</span>' ?>
        </label>
        <input type="password" id="password" name="password" class="<?= $invalid('password') ?>"
               maxlength="64" autocomplete="new-password"
               placeholder="<?= $isEdit ? 'Leave blank to keep current password' : 'Min 8 chars, Aa, 0-9, symbol' ?>">
        <?= $errorFor('password') ?>
    </div>

    <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password"
               class="<?= $invalid('confirm_password') ?>" maxlength="64" autocomplete="new-password">
        <?= $errorFor('confirm_password') ?>
    </div>
</div>
