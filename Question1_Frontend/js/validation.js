/* =====================================================================
   Question1_Frontend/js/validation.js  —  Question 1 (Client-side validation)
   ---------------------------------------------------------------------
   How it works
   1. Every field has a small "validator" function that returns an error
      message (string) or "" when the value is valid.
   2. validateField() runs a validator, then shows/hides the inline error
      message and colours the input red (is-invalid) or green (is-valid).
   3. Fields are re-checked on "blur" (leaving the field) and while the
      user types once the field has been touched.
   4. On submit we validate ALL fields. If anything is wrong we call
      event.preventDefault() so the form is NOT sent, show a summary of
      the errors and move the cursor to the first wrong field.
   The form uses the `novalidate` attribute, so the browser's own HTML5
   validation bubbles are disabled and everything below is our own code.
   ===================================================================== */

(function () {
    'use strict';

    const form = document.getElementById('registrationForm');
    if (!form) { return; }

    // ---------- Constants used by the validators ----------
    const MB = 1024 * 1024;
    const NAME_PATTERN = /^[A-Za-z][A-Za-z .']*$/;          // letters, spaces, dot, apostrophe
    const ROLL_PATTERN = /^[A-Za-z0-9]{5,15}$/;             // e.g. 23CS101
    const EMAIL_PATTERN = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/;
    const PHONE_PATTERN = /^[6-9][0-9]{9}$/;                // Indian 10-digit mobile

    const PHOTO_RULES = { extensions: ['jpg', 'jpeg', 'png', 'webp'],
                          mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxSize: 2 * MB };
    const PROOF_RULES = { extensions: ['pdf', 'jpg', 'jpeg', 'png'],
                          mimeTypes: ['application/pdf', 'image/jpeg', 'image/png'], maxSize: 5 * MB };

    // Password strength rules – each one is shown as a ✓/✕ item in the checklist.
    const PASSWORD_RULES = {
        length:  (pw) => pw.length >= 8,
        upper:   (pw) => /[A-Z]/.test(pw),
        lower:   (pw) => /[a-z]/.test(pw),
        number:  (pw) => /[0-9]/.test(pw),
        special: (pw) => /[^A-Za-z0-9]/.test(pw)
    };

    // Shortcut to read a trimmed value from a field by its id.
    const valueOf = (id) => document.getElementById(id).value.trim();

    // ---------- Helper: validate a chosen file (type + size) ----------
    function checkFile(input, rules, required, label) {
        const file = input.files[0];
        if (!file) {
            return required ? label + ' is required.' : '';
        }
        const extension = file.name.split('.').pop().toLowerCase();
        if (!rules.extensions.includes(extension)) {
            return 'Invalid file type ".' + extension + '". Allowed: ' +
                   rules.extensions.join(', ').toUpperCase() + '.';
        }
        // file.type is the MIME type reported by the browser (may be empty for unknown types)
        if (file.type && !rules.mimeTypes.includes(file.type)) {
            return 'The selected file does not look like a valid ' + extension.toUpperCase() + ' file.';
        }
        if (file.size === 0) {
            return 'The selected file is empty.';
        }
        if (file.size > rules.maxSize) {
            return 'File is too large (' + (file.size / MB).toFixed(2) + ' MB). Maximum size is ' +
                   (rules.maxSize / MB) + ' MB.';
        }
        return '';
    }

    // ---------- One validator per field ----------
    const validators = {
        name: function () {
            const name = valueOf('name');
            if (name === '') { return 'Full name is required.'; }
            if (name.length < 3) { return 'Name must be at least 3 characters long.'; }
            if (name.length > 100) { return 'Name must not exceed 100 characters.'; }
            if (!NAME_PATTERN.test(name)) { return 'Name may contain only letters, spaces, dots and apostrophes.'; }
            return '';
        },
        roll_number: function () {
            const roll = valueOf('roll_number');
            if (roll === '') { return 'Roll number is required.'; }
            if (!ROLL_PATTERN.test(roll)) { return 'Roll number must be 5–15 letters/digits with no spaces (e.g. 23CS101).'; }
            return '';
        },
        email: function () {
            const email = valueOf('email');
            if (email === '') { return 'Email address is required.'; }
            if (!EMAIL_PATTERN.test(email)) { return 'Please enter a valid email address (e.g. name@example.com).'; }
            return '';
        },
        phone: function () {
            const phone = valueOf('phone');
            if (phone === '') { return ''; }               // optional field
            if (!PHONE_PATTERN.test(phone)) { return 'Enter a valid 10-digit mobile number starting with 6–9.'; }
            return '';
        },
        course: function () {
            return valueOf('course') === '' ? 'Please select a course.' : '';
        },
        password: function () {
            const password = document.getElementById('password').value;
            if (password === '') { return 'Password is required.'; }
            const missing = [];
            if (!PASSWORD_RULES.length(password))  { missing.push('8+ characters'); }
            if (!PASSWORD_RULES.upper(password))   { missing.push('an upper-case letter'); }
            if (!PASSWORD_RULES.lower(password))   { missing.push('a lower-case letter'); }
            if (!PASSWORD_RULES.number(password))  { missing.push('a number'); }
            if (!PASSWORD_RULES.special(password)) { missing.push('a special character'); }
            if (missing.length > 0) { return 'Weak password – add ' + missing.join(', ') + '.'; }
            if (password.length > 64) { return 'Password must not exceed 64 characters.'; }
            return '';
        },
        confirm_password: function () {
            const confirm = document.getElementById('confirm_password').value;
            if (confirm === '') { return 'Please confirm your password.'; }
            if (confirm !== document.getElementById('password').value) { return 'Passwords do not match.'; }
            return '';
        },
        profile_photo: function () {
            return checkFile(document.getElementById('profile_photo'), PHOTO_RULES, true, 'Profile photo');
        },
        id_proof: function () {
            return checkFile(document.getElementById('id_proof'), PROOF_RULES, false, 'ID proof');
        },
        terms: function () {
            return document.getElementById('terms').checked ? '' : 'You must accept the declaration.';
        }
    };

    // ---------- Show / clear the error for ONE field ----------
    function validateField(fieldId) {
        const input = document.getElementById(fieldId);
        const errorBox = document.getElementById(fieldId + '-error');
        const message = validators[fieldId]();

        // For file inputs we colour the dashed wrapper, for others the input itself.
        const target = input.type === 'file' ? input.closest('.file-input') : input;

        errorBox.textContent = message;
        target.classList.toggle('is-invalid', message !== '');
        target.classList.toggle('is-valid', message === '' && (input.type === 'checkbox' ? false : input.value !== ''));
        input.setAttribute('aria-invalid', message !== '' ? 'true' : 'false');
        if (message !== '') {
            input.setAttribute('aria-describedby', errorBox.id);
        }
        return message === '';
    }

    // ---------- Password strength meter ----------
    function updateStrengthMeter() {
        const password = document.getElementById('password').value;
        let score = 0;

        // Tick / cross each rule in the checklist
        Object.keys(PASSWORD_RULES).forEach(function (rule) {
            const passed = PASSWORD_RULES[rule](password);
            if (passed) { score++; }
            document.querySelector('#passwordRules [data-rule="' + rule + '"]').classList.toggle('met', passed);
        });

        const levels = [
            { label: '–',           colour: '#e5e7eb' },
            { label: 'Very weak',   colour: '#dc2626' },
            { label: 'Weak',        colour: '#ea580c' },
            { label: 'Fair',        colour: '#d97706' },
            { label: 'Good',        colour: '#65a30d' },
            { label: 'Strong',      colour: '#16a34a' }
        ];
        const level = password === '' ? levels[0] : levels[score];
        const bar = document.getElementById('strengthBar');
        bar.style.width = (score * 20) + '%';
        bar.style.background = level.colour;
        document.getElementById('strengthText').textContent = 'Password strength: ' + level.label;
    }

    // ---------- Attach live-validation events ----------
    const touched = {};
    Object.keys(validators).forEach(function (fieldId) {
        const input = document.getElementById(fieldId);
        const liveEvent = (input.type === 'file' || input.type === 'checkbox' || input.tagName === 'SELECT')
            ? 'change' : 'input';

        input.addEventListener('blur', function () {
            touched[fieldId] = true;
            validateField(fieldId);
        });
        input.addEventListener(liveEvent, function () {
            if (liveEvent === 'change') { touched[fieldId] = true; }
            if (touched[fieldId]) { validateField(fieldId); }
        });
    });

    // Password: update meter on every key, and re-check "confirm" so mismatch updates live.
    document.getElementById('password').addEventListener('input', function () {
        updateStrengthMeter();
        if (touched.confirm_password) { validateField('confirm_password'); }
    });

    // Roll number is stored in upper case – convert as the user types.
    document.getElementById('roll_number').addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });

    // Show a small preview of a valid profile photo.
    document.getElementById('profile_photo').addEventListener('change', function () {
        const preview = document.getElementById('photoPreview');
        if (this.files[0] && validators.profile_photo() === '') {
            preview.src = URL.createObjectURL(this.files[0]);
            preview.hidden = false;
        } else {
            preview.hidden = true;
            preview.removeAttribute('src');
        }
    });

    // Show / hide password buttons.
    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.target);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    // ---------- Form submission ----------
    form.addEventListener('submit', function (event) {
        const summary = document.getElementById('errorSummary');
        const success = document.getElementById('successMessage');
        success.hidden = true;

        // Validate every field (not just the touched ones)
        const errors = [];
        let firstInvalid = null;
        Object.keys(validators).forEach(function (fieldId) {
            touched[fieldId] = true;
            if (!validateField(fieldId)) {
                errors.push(document.getElementById(fieldId + '-error').textContent);
                if (!firstInvalid) { firstInvalid = document.getElementById(fieldId); }
            }
        });

        if (errors.length > 0) {
            // STOP the submission and tell the user what is wrong.
            event.preventDefault();
            summary.innerHTML = '';
            const heading = document.createElement('strong');
            heading.textContent = 'Please correct ' + errors.length + ' error' +
                                  (errors.length > 1 ? 's' : '') + ' before submitting:';
            const list = document.createElement('ul');
            errors.forEach(function (text) {
                const item = document.createElement('li');
                item.textContent = text;            // textContent → no HTML injection
                list.appendChild(item);
            });
            summary.appendChild(heading);
            summary.appendChild(list);
            summary.hidden = false;
            firstInvalid.focus();
            summary.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        summary.hidden = true;

        // When the page is opened straight from disk (file://) there is no PHP
        // server to receive the form, so we just show a success message.
        if (window.location.protocol === 'file:') {
            event.preventDefault();
            success.textContent = 'All fields are valid! (Offline demo mode – open this page through ' +
                                  'XAMPP/Apache to save the registration in the database.)';
            success.hidden = false;
            success.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        // Valid → let the browser submit to Question2_PHP/register.php
        const button = document.getElementById('submitBtn');
        button.disabled = true;
        button.textContent = 'Submitting…';
    });

    // ---------- Reset button: clear every message and colour ----------
    form.addEventListener('reset', function () {
        // Wait until the browser has actually cleared the values.
        setTimeout(function () {
            Object.keys(validators).forEach(function (fieldId) {
                touched[fieldId] = false;
                const input = document.getElementById(fieldId);
                const target = input.type === 'file' ? input.closest('.file-input') : input;
                target.classList.remove('is-invalid', 'is-valid');
                input.removeAttribute('aria-invalid');
                document.getElementById(fieldId + '-error').textContent = '';
            });
            document.getElementById('errorSummary').hidden = true;
            document.getElementById('successMessage').hidden = true;
            document.getElementById('photoPreview').hidden = true;
            updateStrengthMeter();
        }, 0);
    });

    // Re-enable the submit button if the user comes back with the browser's Back button.
    window.addEventListener('pageshow', function () {
        const button = document.getElementById('submitBtn');
        button.disabled = false;
        button.textContent = 'Submit Registration';
    });
})();
