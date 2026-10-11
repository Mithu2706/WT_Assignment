/* =====================================================================
   validation.js - client-side validation for the registration form.

   Structure
   ---------
   PART A: pure validation functions (no DOM).  They are exported for the
           browser as window.RegistrationValidator and for Node.js tests
           via module.exports (see frontend/tests/validation.test.js).
   PART B: DOM wiring - live feedback, password-strength meter, file checks,
           error summary, duplicate-submit protection and AJAX submission.

   IMPORTANT: client-side checks only improve the user experience.  The PHP
   endpoint (php-admin/public/register.php) repeats every check on the
   server, because anything in the browser can be bypassed.
   ===================================================================== */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;                       // Node.js (unit tests)
    } else {
        root.RegistrationValidator = api;           // Browser global
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /* ------------------------------------------------------------------
       PART A - Pure validation rules (shared limits mirror the server)
       ------------------------------------------------------------------ */
    var MB = 1024 * 1024;

    var FILE_RULES = {
        profile_photo: {
            label: 'Profile photo',
            required: true,
            maxBytes: 2 * MB,
            extensions: ['jpg', 'jpeg', 'png', 'webp'],
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp']
        },
        id_proof: {
            label: 'ID proof',
            required: false,
            maxBytes: 5 * MB,
            extensions: ['pdf', 'jpg', 'jpeg', 'png'],
            mimeTypes: ['application/pdf', 'image/jpeg', 'image/png']
        }
    };

    // A small list of passwords that satisfy the character rules but are still guessable.
    var COMMON_PASSWORDS = ['password1!', 'password@123', 'welcome@123', 'admin@123', 'qwerty@123', 'p@ssw0rd', 'abc@1234'];

    function normaliseSpaces(value) {
        return String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
    }

    /** Student name: 2-100 chars, starts with a letter, letters/spaces/.'- only. */
    function validateName(value) {
        var name = normaliseSpaces(value);
        if (name === '') return 'Enter the student\'s full name.';
        if (name.length < 2) return 'Name must be at least 2 characters.';
        if (name.length > 100) return 'Name must be 100 characters or fewer.';
        // \p{L} = any Unicode letter, \p{M} = combining marks (e.g. Tamil vowel signs)
        if (!/^\p{L}[\p{L}\p{M} .'\-]*$/u.test(name)) {
            return 'Use letters, spaces, dots, apostrophes or hyphens only.';
        }
        return '';
    }

    /** Roll number: 5-15 letters/digits; compared in upper case. */
    function validateRollNumber(value) {
        var roll = String(value == null ? '' : value).trim().toUpperCase();
        if (roll === '') return 'Enter your roll number.';
        if (!/^[A-Z0-9]+$/.test(roll)) return 'Roll number may contain only letters and digits (no spaces or symbols).';
        if (roll.length < 5 || roll.length > 15) return 'Roll number must be 5 to 15 characters long.';
        return '';
    }

    /** Practical email check: local@domain.tld with sensible length limits. */
    function validateEmail(value) {
        var email = String(value == null ? '' : value).trim();
        if (email === '') return 'Enter your email address.';
        if (email.length > 150) return 'Email must be 150 characters or fewer.';
        var at = email.lastIndexOf('@');
        if (at < 1 || at === email.length - 1) return 'Enter an email address like name@example.edu.';
        var local = email.slice(0, at);
        var domain = email.slice(at + 1);
        var localOk = /^[A-Za-z0-9!#$%&'*+\/=?^_`{|}~-]+(\.[A-Za-z0-9!#$%&'*+\/=?^_`{|}~-]+)*$/.test(local);
        var domainOk = /^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,24}$/.test(domain);
        if (local.length > 64 || !localOk || !domainOk) return 'Enter an email address like name@example.edu.';
        return '';
    }

    /** Course must be one of the offered course codes. */
    function validateCourse(value, allowedCodes) {
        var code = String(value == null ? '' : value).trim();
        if (code === '') return 'Select a course.';
        if (allowedCodes && allowedCodes.indexOf(code) === -1) return 'Select a course from the list.';
        return '';
    }

    /**
     * Password strength assessment.
     * Returns { checks, score, level (0-4), label, valid, message }.
     *   level 0 = empty, 1 = weak, 2 = fair, 3 = good, 4 = strong
     */
    function assessPassword(password, context) {
        password = String(password == null ? '' : password);
        context = context || {};
        var checks = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            digit: /[0-9]/.test(password),
            special: /[^A-Za-z0-9\s]/.test(password)
        };
        var met = Object.keys(checks).filter(function (k) { return checks[k]; }).length;
        var score = met + (password.length >= 12 ? 1 : 0);

        var level = 0;
        if (password.length > 0) {
            level = score <= 2 ? 1 : score <= 4 ? 2 : score === 5 ? 3 : 4;
        }

        var message = '';
        var missing = [];
        if (!checks.length) missing.push('at least 8 characters');
        if (!checks.upper) missing.push('an uppercase letter');
        if (!checks.lower) missing.push('a lowercase letter');
        if (!checks.digit) missing.push('a number');
        if (!checks.special) missing.push('a symbol');

        var roll = String(context.rollNumber || '').trim().toLowerCase();
        if (password === '') {
            message = 'Create a password.';
        } else if (password.length > 64) {
            message = 'Password must be 64 characters or fewer.';
        } else if (/\s/.test(password)) {
            message = 'Password must not contain spaces.';
        } else if (missing.length) {
            message = 'Password needs ' + joinList(missing) + '.';
        } else if (COMMON_PASSWORDS.indexOf(password.toLowerCase()) !== -1) {
            message = 'This password is too common. Choose something less predictable.';
            level = Math.min(level, 1);
        } else if (roll.length >= 5 && password.toLowerCase().indexOf(roll) !== -1) {
            message = 'Password must not contain your roll number.';
            level = Math.min(level, 2);
        }

        var labels = ['not entered', 'Weak', 'Fair', 'Good', 'Strong'];
        return {
            checks: checks,
            score: score,
            level: level,
            label: labels[level],
            valid: message === '',
            message: message
        };
    }

    function validatePassword(password, context) {
        return assessPassword(password, context).message;
    }

    function validateConfirmPassword(password, confirm) {
        if (String(confirm || '') === '') return 'Re-enter the password to confirm it.';
        if (password !== confirm) return 'Passwords do not match.';
        return '';
    }

    function fileExtension(name) {
        var match = /\.([A-Za-z0-9]+)$/.exec(String(name || ''));
        return match ? match[1].toLowerCase() : '';
    }

    function formatBytes(bytes) {
        if (bytes >= MB) return (bytes / MB).toFixed(1).replace(/\.0$/, '') + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
        return bytes + ' bytes';
    }

    /**
     * Validate a File-like object ({ name, size, type }) against a rule set.
     * Checks presence, extension, declared MIME type, empty file and size.
     */
    function validateFile(file, rules) {
        if (!file) return rules.required ? 'Choose a ' + rules.label.toLowerCase() + ' to upload.' : '';
        var ext = fileExtension(file.name);
        // Reject double extensions that hide executables, e.g. "photo.php.jpg"
        if (/\.(php\d?|phtml|phar|jsp|jspx|asp|aspx|exe|bat|cmd|sh|js|html?|svg)\./i.test(file.name)) {
            return 'This file name looks unsafe. Rename the file and try again.';
        }
        if (rules.extensions.indexOf(ext) === -1) {
            return rules.label + ' must be a ' + joinList(uniqueUpper(rules.extensions), 'or') + ' file.';
        }
        if (file.type && rules.mimeTypes.indexOf(file.type) === -1) {
            return rules.label + ' has an unsupported file type (' + file.type + ').';
        }
        if (file.size === 0) return 'The selected file is empty.';
        if (file.size > rules.maxBytes) {
            return rules.label + ' is ' + formatBytes(file.size) + '. The maximum size is ' + formatBytes(rules.maxBytes) + '.';
        }
        return '';
    }

    /**
     * Identify a file by its first bytes ("magic numbers") instead of trusting
     * its name.  Returns a MIME type string or null.
     */
    function detectSignature(bytes) {
        if (!bytes || bytes.length < 4) return null;
        var b = bytes;
        if (b[0] === 0xFF && b[1] === 0xD8 && b[2] === 0xFF) return 'image/jpeg';
        if (b[0] === 0x89 && b[1] === 0x50 && b[2] === 0x4E && b[3] === 0x47) return 'image/png';
        if (b[0] === 0x25 && b[1] === 0x50 && b[2] === 0x44 && b[3] === 0x46) return 'application/pdf'; // %PDF
        if (b.length >= 12 && b[0] === 0x52 && b[1] === 0x49 && b[2] === 0x46 && b[3] === 0x46 &&
            b[8] === 0x57 && b[9] === 0x45 && b[10] === 0x42 && b[11] === 0x50) return 'image/webp';   // RIFF....WEBP
        return null;
    }

    function uniqueUpper(list) {
        var out = [];
        list.forEach(function (item) {
            var v = item === 'jpeg' ? 'JPG' : item.toUpperCase();
            if (out.indexOf(v) === -1) out.push(v);
        });
        return out;
    }

    function joinList(items, conjunction) {
        conjunction = conjunction || 'and';
        if (items.length <= 1) return items.join('');
        return items.slice(0, -1).join(', ') + ' ' + conjunction + ' ' + items[items.length - 1];
    }

    return {
        FILE_RULES: FILE_RULES,
        validateName: validateName,
        validateRollNumber: validateRollNumber,
        validateEmail: validateEmail,
        validateCourse: validateCourse,
        assessPassword: assessPassword,
        validatePassword: validatePassword,
        validateConfirmPassword: validateConfirmPassword,
        validateFile: validateFile,
        detectSignature: detectSignature,
        fileExtension: fileExtension,
        formatBytes: formatBytes
    };
}));

/* ======================================================================
   PART B - DOM wiring (runs only in a browser)
   ====================================================================== */
(function () {
    'use strict';
    if (typeof document === 'undefined') return;

    document.addEventListener('DOMContentLoaded', function () {
        var V = window.RegistrationValidator;
        var form = document.getElementById('registration-form');
        if (!form) return;

        var submitButton = document.getElementById('submit-button');
        var errorSummary = document.getElementById('error-summary');
        var errorList = document.getElementById('error-list');
        var notice = document.getElementById('form-notice');
        var tokenInput = document.getElementById('csrf_token');
        var successCard = document.getElementById('success-card');

        var isSubmitting = false;         // guards against double submission
        var touched = {};                 // fields the user has interacted with
        var fileErrors = {};              // async results of file signature checks

        var courseSelect = form.elements.course;
        var allowedCourses = Array.prototype.slice.call(courseSelect.options)
            .map(function (o) { return o.value; })
            .filter(Boolean);

        // Field id -> function returning an error message ('' when valid)
        var validators = {
            full_name: function () { return V.validateName(form.elements.full_name.value); },
            roll_number: function () { return V.validateRollNumber(form.elements.roll_number.value); },
            course: function () { return V.validateCourse(courseSelect.value, allowedCourses); },
            email: function () { return V.validateEmail(form.elements.email.value); },
            password: function () {
                return V.validatePassword(form.elements.password.value, { rollNumber: form.elements.roll_number.value });
            },
            confirm_password: function () {
                return V.validateConfirmPassword(form.elements.password.value, form.elements.confirm_password.value);
            },
            profile_photo: function () { return fileErrors.profile_photo || ''; },
            id_proof: function () { return fileErrors.id_proof || ''; },
            declaration: function () {
                return form.elements.declaration.checked ? '' : 'Please confirm the declaration.';
            }
        };
        var fieldLabels = {
            full_name: 'Student name', roll_number: 'Roll number', course: 'Course', email: 'Email address',
            password: 'Password', confirm_password: 'Confirm password', profile_photo: 'Profile photo',
            id_proof: 'ID proof', declaration: 'Declaration'
        };

        /* ---------- helpers to show / clear a field's message ---------- */
        function showFieldError(id, message) {
            var input = document.getElementById(id);
            var errorEl = document.getElementById(id + '-error');
            if (errorEl) errorEl.textContent = message;
            if (input) {
                input.setAttribute('aria-invalid', message ? 'true' : 'false');
                if (input.type !== 'file' && input.type !== 'checkbox') {
                    input.dataset.valid = message ? 'false' : 'true';
                }
            }
            var drop = document.getElementById(id + '-drop');
            if (drop) {
                drop.classList.toggle('invalid', !!message);
                drop.classList.toggle('valid', !message && input && input.files && input.files.length > 0);
            }
        }

        function validateField(id) {
            var message = validators[id]();
            showFieldError(id, message);
            return message;
        }

        /* ---------- live validation: on blur first, then on every input ---------- */
        ['full_name', 'roll_number', 'email', 'password', 'confirm_password'].forEach(function (id) {
            var input = form.elements[id];
            input.addEventListener('blur', function () {
                if (input.value !== '') touched[id] = true;
                if (touched[id]) validateField(id);
            });
            input.addEventListener('input', function () {
                if (touched[id]) validateField(id);
                if (id === 'password') {
                    updateStrength();
                    if (touched.confirm_password) validateField('confirm_password');
                }
            });
        });
        courseSelect.addEventListener('change', function () { touched.course = true; validateField('course'); });
        form.elements.declaration.addEventListener('change', function () { validateField('declaration'); });

        // Roll numbers are stored in upper case - normalise as the user leaves the field.
        form.elements.roll_number.addEventListener('change', function (e) {
            e.target.value = e.target.value.trim().toUpperCase();
        });

        /* ---------- password strength meter ---------- */
        var strengthBox = document.getElementById('password-strength');
        var strengthText = document.getElementById('strength-text');
        var ruleItems = document.querySelectorAll('#password-rules li');

        function updateStrength() {
            var result = V.assessPassword(form.elements.password.value, { rollNumber: form.elements.roll_number.value });
            strengthBox.dataset.level = String(result.level);
            strengthText.textContent = result.label;
            Array.prototype.forEach.call(ruleItems, function (li) {
                li.classList.toggle('met', !!result.checks[li.dataset.rule]);
            });
        }

        /* ---------- show / hide password buttons ---------- */
        Array.prototype.forEach.call(document.querySelectorAll('.toggle-visibility'), function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.toggle);
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Hide' : 'Show';
                btn.setAttribute('aria-pressed', String(show));
                btn.setAttribute('aria-label', (show ? 'Hide ' : 'Show ') + fieldLabels[btn.dataset.toggle].toLowerCase());
            });
        });

        /* ---------- file inputs: name, preview, size/type/signature check ---------- */
        function checkFile(id) {
            var input = document.getElementById(id);
            var rules = V.FILE_RULES[id];
            var file = input.files && input.files[0];
            var nameEl = document.getElementById(id + '-name');
            var preview = document.getElementById(id + '-preview');

            nameEl.textContent = file ? file.name + ' · ' + V.formatBytes(file.size) : 'No file selected';
            if (preview) { preview.hidden = true; preview.removeAttribute('src'); }

            var basic = V.validateFile(file, rules);
            if (basic || !file) {
                fileErrors[id] = basic;
                return Promise.resolve(basic);
            }
            // Read the first 12 bytes and compare the real signature with the allowed types.
            return file.slice(0, 12).arrayBuffer().then(function (buf) {
                var detected = V.detectSignature(new Uint8Array(buf));
                var message = '';
                if (!detected || rules.mimeTypes.indexOf(detected) === -1) {
                    message = 'The contents of this file do not match an allowed ' + rules.label.toLowerCase() + ' format.';
                }
                fileErrors[id] = message;
                if (!message && preview && detected.indexOf('image/') === 0) {
                    preview.src = URL.createObjectURL(file);
                    preview.hidden = false;
                }
                return message;
            }).catch(function () {
                fileErrors[id] = 'The file could not be read. Please choose it again.';
                return fileErrors[id];
            });
        }

        ['profile_photo', 'id_proof'].forEach(function (id) {
            var input = document.getElementById(id);
            var drop = document.getElementById(id + '-drop');
            input.addEventListener('change', function () {
                checkFile(id).then(function () { validateField(id); });
            });
            // Drag-and-drop support (progressive enhancement)
            drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('dragover'); });
            drop.addEventListener('dragleave', function () { drop.classList.remove('dragover'); });
            drop.addEventListener('drop', function (e) {
                e.preventDefault();
                drop.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });

        /* ---------- error summary ---------- */
        function showSummary(errors) {
            errorList.innerHTML = '';
            Object.keys(errors).forEach(function (id) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = '#' + id;
                a.textContent = (fieldLabels[id] || 'Form') + ': ' + errors[id];   // textContent => no HTML injection
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    var target = document.getElementById(id);
                    if (target) target.focus();
                });
                li.appendChild(a);
                errorList.appendChild(li);
            });
            errorSummary.hidden = false;
            errorSummary.focus();
        }

        function showNotice(message, kind) {
            notice.className = 'alert ' + (kind === 'warning' ? 'alert-warning' : kind === 'error' ? 'alert-error' : 'alert-info');
            notice.textContent = message;
            notice.hidden = !message;
        }

        function setLoading(loading) {
            isSubmitting = loading;
            submitButton.disabled = loading;
            submitButton.classList.toggle('is-loading', loading);
            submitButton.querySelector('.btn-label').textContent = loading ? 'Submitting…' : 'Submit registration';
            form.setAttribute('aria-busy', String(loading));
        }

        /* ---------- anti-CSRF token from the PHP server ---------- */
        function loadToken() {
            if (location.protocol === 'file:') {
                showNotice('Opened directly from disk: validation works, but submission needs the PHP server (see README).', 'warning');
                return Promise.resolve();
            }
            return fetch(form.dataset.tokenUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (data) { tokenInput.value = data.token; })
                .catch(function () {
                    showNotice('The registration server is not reachable right now. You can still check your details; submission will be possible once it is online.', 'warning');
                });
        }
        loadToken();

        /* ---------- submit ---------- */
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;                  // duplicate click / Enter key ignored
            setLoading(true);
            errorSummary.hidden = true;

            Object.keys(touched).forEach(function (k) { touched[k] = true; });
            ['full_name', 'roll_number', 'email', 'password', 'confirm_password', 'course'].forEach(function (k) { touched[k] = true; });

            Promise.all([checkFile('profile_photo'), checkFile('id_proof')]).then(function () {
                var errors = {};
                Object.keys(validators).forEach(function (id) {
                    var message = validateField(id);
                    if (message) errors[id] = message;
                });
                if (Object.keys(errors).length) {
                    setLoading(false);
                    showSummary(errors);
                    return;
                }
                if (!tokenInput.value) {
                    setLoading(false);
                    showNotice('Cannot submit yet: the registration server is not reachable. Please try again shortly.', 'error');
                    return;
                }
                return sendRegistration();
            });
        });

        function sendRegistration() {
            var data = new FormData(form);
            return fetch(form.action, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.json().catch(function () { return { ok: false, message: 'Unexpected server response.' }; });
            }).then(function (result) {
                if (result.ok) {
                    showSuccess(result);
                    return;
                }
                setLoading(false);
                // The server may refresh the single-use token (e.g. after an expired session).
                if (result.token) tokenInput.value = result.token;
                if (result.errors && Object.keys(result.errors).length) {
                    Object.keys(result.errors).forEach(function (id) { showFieldError(id, result.errors[id]); });
                    showSummary(result.errors);
                } else {
                    showNotice(result.message || 'Registration failed. Please try again.', 'error');
                }
            }).catch(function () {
                setLoading(false);
                showNotice('Network error: the registration could not be sent. Check your connection and try again.', 'error');
            });
        }

        function showSuccess(result) {
            var details = document.getElementById('success-details');
            details.innerHTML = '';
            [['Name', result.student.full_name], ['Roll number', result.student.roll_number],
             ['Course', result.student.course], ['Login username', result.student.username]].forEach(function (pair) {
                var dt = document.createElement('dt'); dt.textContent = pair[0];
                var dd = document.createElement('dd'); dd.textContent = pair[1];
                details.appendChild(dt); details.appendChild(dd);
            });
            document.getElementById('success-message').textContent = result.message;
            form.hidden = true;
            successCard.hidden = false;
            successCard.focus();
            setLoading(false);
            form.reset();                 // the 'reset' listener below clears the UI state
        }

        // Clears messages, previews and the strength meter (called after the native reset).
        function clearFormUi() {
            touched = {};
            fileErrors = {};
            Object.keys(validators).forEach(function (id) {
                var input = document.getElementById(id);
                showFieldError(id, '');
                if (input) { input.removeAttribute('aria-invalid'); delete input.dataset.valid; }
            });
            ['profile_photo', 'id_proof'].forEach(function (id) {
                document.getElementById(id + '-name').textContent = 'No file selected';
                document.getElementById(id + '-drop').classList.remove('valid', 'invalid');
            });
            var preview = document.getElementById('profile_photo-preview');
            preview.hidden = true; preview.removeAttribute('src');
            errorSummary.hidden = true;
            updateStrength();
        }

        form.addEventListener('reset', function () { setTimeout(clearFormUi, 0); });

        document.getElementById('register-another').addEventListener('click', function () {
            successCard.hidden = true;
            form.hidden = false;
            showNotice('', 'info');
            loadToken();                                   // previous token was single-use
            form.elements.full_name.focus();
        });

        updateStrength();
    });
}());
