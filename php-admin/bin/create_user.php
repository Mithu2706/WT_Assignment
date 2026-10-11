<?php
/**
 * Command-line tool to create login accounts securely (no default passwords
 * exist anywhere in the source code or SQL files).
 *
 *   php php-admin/bin/create_user.php --username=admin --role=admin --name="Portal Admin"
 *   php php-admin/bin/create_user.php --username=staff1 --role=staff --name="Office Staff"
 *   php php-admin/bin/create_user.php --role=student --student-roll=23CS001
 *
 * The password is read from the SMP_NEW_PASSWORD environment variable or
 * typed at the prompt (entered twice).  It must satisfy the same policy as
 * the registration form, and is stored only as a bcrypt hash.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';

$opts = getopt('', ['username:', 'role:', 'name:', 'student-roll:', 'help']);
if (isset($opts['help']) || empty($opts['role'])) {
    fwrite(STDOUT, "Usage: php php-admin/bin/create_user.php --role=admin|staff|student [--username=NAME] [--name=\"Display Name\"] [--student-roll=ROLL]\n");
    exit(isset($opts['help']) ? 0 : 1);
}

$role = (string) $opts['role'];
if (!in_array($role, ['admin', 'staff', 'student'], true)) {
    fwrite(STDERR, "Role must be admin, staff or student.\n");
    exit(1);
}

$studentId = null;
if ($role === 'student') {
    $roll = strtoupper((string) ($opts['student-roll'] ?? ''));
    $stmt = Database::connection()->prepare('SELECT id, full_name, roll_number FROM students WHERE roll_number = ?');
    $stmt->execute([$roll]);
    $student = $stmt->fetch() ?: null;
    if ($student === null) {
        fwrite(STDERR, "No student with roll number '$roll'.\n");
        exit(1);
    }
    $studentId = (int) $student['id'];
    $username = $student['roll_number'];
    $displayName = $student['full_name'];
} else {
    $username = trim((string) ($opts['username'] ?? ''));
    $displayName = trim((string) ($opts['name'] ?? $username));
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        fwrite(STDERR, "Username must be 3-50 characters: letters, digits, _ . -\n");
        exit(1);
    }
}

if (UserRepository::usernameExists($username)) {
    fwrite(STDERR, "User '$username' already exists.\n");
    exit(1);
}

/** Read a line from the terminal (password input is not echoed on Windows PowerShell 5 - typed text may be visible). */
$prompt = static function (string $label): string {
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return $line === false ? '' : rtrim($line, "\r\n");
};

$password = (string) getenv('SMP_NEW_PASSWORD');
if ($password === '') {
    fwrite(STDOUT, "Password rules: 8-64 chars, upper + lower case letter, digit and symbol.\n");
    $password = $prompt('New password: ');
    $confirm = $prompt('Repeat password: ');
    if (!hash_equals($password, $confirm)) {
        fwrite(STDERR, "Passwords do not match.\n");
        exit(1);
    }
}
if ($problem = PasswordPolicy::check($password, $role === 'student' ? $username : '')) {
    fwrite(STDERR, $problem . "\n");
    exit(1);
}

$id = UserRepository::create($username, $displayName, $password, $role, $studentId);
fwrite(STDOUT, "Created $role account '$username' (user id $id).\n");
