<?php
/**
 * includes/functions.php
 * ---------------------------------------------------------------------
 * Small helper functions shared by every page of the admin panel:
 *   e()                     – escape output with htmlspecialchars (prevents XSS)
 *   redirect()              – send a Location header and stop the script
 *   set_flash()/get_flashes – one-time success/error messages stored in the session
 *   csrf_field()/verify_csrf() – protect POST forms against CSRF attacks
 *   format_bytes()          – 1048576 → "1.00 MB"
 *   get_id_param()          – safely read a positive integer id from GET/POST
 *   validate_student_data() – server-side validation of the student form
 *   find_duplicate_errors() – check roll number / e-mail are not already used
 *   render_error_page()     – friendly error page (no stack traces shown)
 */

declare(strict_types=1);

/** Escape any user-controlled value before printing it inside HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect to another page of the admin panel and stop executing. */
function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

/** Store a message that will be shown once on the next page. */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Read and remove all pending flash messages. */
function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Return (and create on first use) the CSRF token of the current session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden <input> that must be placed inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Check the token sent with a POST request (hash_equals = timing-safe compare). */
function verify_csrf(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Human-readable file size. */
function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' bytes';
}

/** Read a positive integer id from GET or POST; returns null if missing/invalid. */
function get_id_param(string $name = 'id', int $source = INPUT_GET): ?int
{
    $id = filter_input($source, $name, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($id) ? $id : null;
}

/** Load one student row by primary key (prepared statement). */
function find_student(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $student = $stmt->fetch();
    return $student ?: null;
}

/**
 * Server-side validation of the student form.
 * NEVER trust the browser – JavaScript can be switched off or bypassed,
 * so every rule from the frontend is checked again here.
 *
 * @param array $input            usually $_POST
 * @param bool  $passwordRequired true when adding, false when editing (blank = keep old)
 * @return array{0: array, 1: array}  [cleaned data, errors keyed by field name]
 */
function validate_student_data(array $input, bool $passwordRequired): array
{
    $data = [
        'name'          => trim((string) ($input['name'] ?? '')),
        'roll_number'   => strtoupper(trim((string) ($input['roll_number'] ?? ''))),
        'email'         => strtolower(trim((string) ($input['email'] ?? ''))),
        'course'        => trim((string) ($input['course'] ?? '')),
        'phone'         => trim((string) ($input['phone'] ?? '')),
        'date_of_birth' => trim((string) ($input['date_of_birth'] ?? '')),
        'address'       => trim((string) ($input['address'] ?? '')),
        'password'      => (string) ($input['password'] ?? ''),
    ];
    $confirm = (string) ($input['confirm_password'] ?? '');
    $errors = [];

    // Name: required, 3–100 chars, letters/spaces/dot/apostrophe
    if ($data['name'] === '') {
        $errors['name'] = 'Name is required.';
    } elseif (mb_strlen($data['name']) < 3 || mb_strlen($data['name']) > 100) {
        $errors['name'] = 'Name must be between 3 and 100 characters.';
    } elseif (!preg_match("/^[A-Za-z][A-Za-z .']*$/", $data['name'])) {
        $errors['name'] = 'Name may contain only letters, spaces, dots and apostrophes.';
    }

    // Roll number: required, 5–15 letters/digits
    if ($data['roll_number'] === '') {
        $errors['roll_number'] = 'Roll number is required.';
    } elseif (!preg_match('/^[A-Z0-9]{5,15}$/', $data['roll_number'])) {
        $errors['roll_number'] = 'Roll number must be 5–15 letters or digits (e.g. 23CS101).';
    }

    // Email: required + valid format (PHP's built-in email filter)
    if ($data['email'] === '') {
        $errors['email'] = 'Email is required.';
    } elseif (strlen($data['email']) > 150 || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Course: must be one of the allowed values (prevents tampered <select> values)
    if (!in_array($data['course'], COURSES, true)) {
        $errors['course'] = 'Please select a valid course.';
    }

    // Phone: optional, 10-digit Indian mobile number
    if ($data['phone'] !== '' && !preg_match('/^[6-9][0-9]{9}$/', $data['phone'])) {
        $errors['phone'] = 'Enter a valid 10-digit mobile number.';
    }

    // Date of birth: optional, real date, student must be 15–60 years old
    if ($data['date_of_birth'] !== '') {
        $dob = DateTime::createFromFormat('!Y-m-d', $data['date_of_birth']);
        if (!$dob || $dob->format('Y-m-d') !== $data['date_of_birth']) {
            $errors['date_of_birth'] = 'Enter a valid date of birth.';
        } else {
            $age = $dob->diff(new DateTime('today'))->y;
            if ($dob > new DateTime('today') || $age < 15 || $age > 60) {
                $errors['date_of_birth'] = 'Date of birth must give an age between 15 and 60 years.';
            }
        }
    }

    // Address: optional, max 255 characters
    if (mb_strlen($data['address']) > 255) {
        $errors['address'] = 'Address must not exceed 255 characters.';
    }

    // Password: required when adding; when editing, blank means "keep current password"
    if ($data['password'] !== '' || $passwordRequired) {
        $pw = $data['password'];
        if ($pw === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($pw) < 8 || strlen($pw) > 64
            || !preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw)
            || !preg_match('/[0-9]/', $pw) || !preg_match('/[^A-Za-z0-9]/', $pw)) {
            $errors['password'] = 'Password must be 8–64 characters with upper-case, lower-case, a number and a special character.';
        } elseif ($pw !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
    }

    return [$data, $errors];
}

/**
 * Check that no OTHER student already uses this roll number or e-mail.
 * $excludeId is the student being edited (so it does not clash with itself).
 */
function find_duplicate_errors(string $rollNumber, string $email, ?int $excludeId = null): array
{
    $errors = [];
    $sql = 'SELECT roll_number, email FROM students
            WHERE (roll_number = :roll OR email = :email) AND id <> :exclude';
    $stmt = db()->prepare($sql);
    $stmt->execute([':roll' => $rollNumber, ':email' => $email, ':exclude' => $excludeId ?? 0]);

    foreach ($stmt->fetchAll() as $row) {
        if (strcasecmp($row['roll_number'], $rollNumber) === 0) {
            $errors['roll_number'] = 'Roll number ' . $rollNumber . ' is already registered.';
        }
        if (strcasecmp($row['email'], $email) === 0) {
            $errors['email'] = 'This email address is already registered.';
        }
    }
    return $errors;
}

/**
 * Add "already registered" errors to $errors, checking only the fields
 * whose format is valid (so a bad e-mail does not hide a duplicate roll number).
 */
function add_duplicate_errors(array $errors, array $data, ?int $excludeId = null): array
{
    $roll = isset($errors['roll_number']) ? '' : $data['roll_number'];
    $email = isset($errors['email']) ? '' : $data['email'];
    if ($roll === '' && $email === '') {
        return $errors;
    }
    return $errors + find_duplicate_errors($roll, $email, $excludeId);
}

/** Empty optional form values are stored as NULL in MySQL. */
function null_if_empty(string $value): ?string
{
    return $value === '' ? null : $value;
}

/**
 * Validate the optional file inputs of a student form.
 * Returns [list of uploads ready to store, errors keyed by input name].
 */
function collect_student_uploads(string $photoField, string $documentField, bool $photoRequired, string $documentLabel): array
{
    $uploads = [];
    $errors = [];

    $photo = $_FILES[$photoField] ?? null;
    if (upload_was_attempted($photo)) {
        $result = validate_upload($photo, 'profile');
        if ($result['ok']) {
            $uploads[] = ['file' => $photo, 'category' => 'profile', 'validated' => $result, 'label' => null];
        } else {
            $errors[$photoField] = $result['error'];
        }
    } elseif ($photoRequired) {
        $errors[$photoField] = 'Profile photo is required.';
    }

    $document = $_FILES[$documentField] ?? null;
    if (upload_was_attempted($document)) {
        $result = validate_upload($document, 'document');
        if ($result['ok']) {
            $uploads[] = ['file' => $document, 'category' => 'document', 'validated' => $result, 'label' => $documentLabel];
        } else {
            $errors[$documentField] = $result['error'];
        }
    }

    return [$uploads, $errors];
}

/**
 * CREATE: insert a new student and their uploaded files inside ONE
 * database transaction. If anything fails, the transaction is rolled back
 * and files already moved to disk are deleted again, so the database and
 * the uploads folder never get out of sync.
 *
 * @return int the new student's id
 * @throws RuntimeException with a user-friendly message
 */
function create_student_with_files(array $data, array $uploads): int
{
    $pdo = db();
    $storedPaths = [];
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO students (name, roll_number, email, course, phone, date_of_birth, address, password_hash)
             VALUES (:name, :roll, :email, :course, :phone, :dob, :address, :hash)'
        );
        $stmt->execute([
            ':name'    => $data['name'],
            ':roll'    => $data['roll_number'],
            ':email'   => $data['email'],
            ':course'  => $data['course'],
            ':phone'   => null_if_empty($data['phone']),
            ':dob'     => null_if_empty($data['date_of_birth']),
            ':address' => null_if_empty($data['address']),
            ':hash'    => password_hash($data['password'], PASSWORD_DEFAULT),   // bcrypt – never plain text
        ]);
        $studentId = (int) $pdo->lastInsertId();

        foreach ($uploads as $upload) {
            $stored = store_upload($studentId, $upload['file'], $upload['category'], $upload['validated'], $upload['label']);
            $storedPaths[] = $stored['file_path'];
        }

        $pdo->commit();
        return $studentId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($storedPaths as $path) {
            delete_physical_file($path);
        }
        // PDOException is checked first because it is also a RuntimeException
        if ($e instanceof PDOException) {
            error_log('Create student failed: ' . $e->getMessage());
            throw new RuntimeException($e->getCode() === '23000'
                ? 'A student with this roll number or email already exists.'
                : 'The student could not be saved because of a database error.');
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }
        error_log('Create student failed: ' . $e->getMessage());
        throw new RuntimeException('The student could not be saved. Please try again.');
    }
}

/**
 * Show a complete, friendly error page and stop the script.
 * Technical details are written to the PHP error log, never to the browser.
 */
function render_error_page(string $title, string $message, int $statusCode = 500): never
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($title) . '</title><link rel="stylesheet" href="css/admin.css"></head>'
        . '<body class="auth-page"><div class="auth-card error-card">'
        . '<div class="error-code">' . $statusCode . '</div>'
        . '<h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<a class="btn btn-primary" href="dashboard.php">Back to Dashboard</a>'
        . '</div></body></html>';
    exit;
}
