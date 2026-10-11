<?php
/**
 * Server-side handler for the public registration form (frontend/register.html).
 *
 * The browser already validated the form, but EVERY rule is checked again
 * here because client-side JavaScript can be bypassed (curl, dev tools ...).
 * On success it creates, in one transaction:
 *   - the student record   (registration_source = 'self')
 *   - a 'student' login     (username = roll number, bcrypt password hash)
 *   - the profile photo and optional ID proof (validated + stored safely)
 *
 * Responds with JSON for the JavaScript form, or a small HTML page otherwise.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

/** Send the result in the format the client asked for. */
function respond(array $payload, int $status): never
{
    if (wants_json()) {
        json_response($payload, $status);
    }
    http_response_code($status);
    $title = $payload['ok'] ? 'Registration received' : 'Registration not completed';
    $message = $payload['message'] . (!empty($payload['errors']) ? ' ' . implode(' ', $payload['errors']) : '');
    render_error_page($status, $title, $message);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond(['ok' => false, 'message' => 'Please use the registration form to register.'], 405);
}

// Requests larger than post_max_size arrive with an empty $_POST.
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(['ok' => false, 'message' => 'The uploaded files are too large. Photos may be up to 2 MB and documents up to 5 MB.'], 413);
}

// Same-origin check (defence in depth alongside the token).
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '') !== ($_SERVER['HTTP_HOST'] ?? '')) {
    respond(['ok' => false, 'message' => 'Cross-site submissions are not accepted.'], 403);
}

// Single-use anti-CSRF / anti-duplicate token.
if (!Csrf::isValidRegistrationToken($_POST['csrf_token'] ?? null)) {
    respond([
        'ok'      => false,
        'message' => 'This form has expired or was already submitted. Please try submitting again.',
        'token'   => Csrf::issueRegistrationToken(),     // lets the page retry once with a fresh token
    ], 403);
}

// ---------- 1. text fields ----------
[$clean, $errors] = StudentValidator::validate($_POST, CourseRepository::codeMap());

$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
if ($msg = PasswordPolicy::check($password, $clean['roll_number'])) {
    $errors['password'] = $msg;
}
if ($confirm === '') {
    $errors['confirm_password'] = 'Re-enter the password to confirm it.';
} elseif (!hash_equals($password, $confirm)) {
    $errors['confirm_password'] = 'Passwords do not match.';
}
if (($_POST['declaration'] ?? '') !== '1') {
    $errors['declaration'] = 'Please confirm the declaration.';
}

// ---------- 2. files ----------
$service = new StudentService(new FileStorage());
$uploads = [];
$photo = StudentService::uploadedFile('profile_photo');
if ($photo === null) {
    $errors['profile_photo'] = 'Choose a profile photo to upload.';
} else {
    $uploads[] = ['field' => 'profile_photo', 'category' => 'profile_photo', 'file' => $photo];
}
if ($proof = StudentService::uploadedFile('id_proof')) {
    $uploads[] = ['field' => 'id_proof', 'category' => 'id_proof', 'file' => $proof];
}
[$validUploads, $uploadErrors] = $service->validateUploads($uploads);
$errors += $uploadErrors;

if ($errors) {
    respond(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
}

// ---------- 3. save ----------
try {
    $service->create($clean, 'self', $validUploads, null, ['password' => $password]);
} catch (ValidationException $e) {
    respond(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $e->errors], 422);
}

Csrf::consumeRegistrationToken();       // the same form cannot be submitted twice

$courseName = '';
foreach (CourseRepository::all() as $c) {
    if ($c['code'] === $clean['course']) {
        $courseName = $c['name'];
    }
}
respond([
    'ok'      => true,
    'message' => 'Thank you, ' . $clean['full_name'] . '. Your registration has been saved. You can sign in to the profile portal with your roll number and password.',
    'student' => [
        'full_name'   => $clean['full_name'],
        'roll_number' => $clean['roll_number'],
        'course'      => $courseName,
        'username'    => $clean['roll_number'],
    ],
], 201);
