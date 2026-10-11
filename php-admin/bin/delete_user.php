<?php
/**
 * Deletes a login account (not the student record).
 *   php php-admin/bin/delete_user.php --username=staff1
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';

$opts = getopt('', ['username:']);
$username = trim((string) ($opts['username'] ?? ''));
if ($username === '') {
    fwrite(STDERR, "Usage: php php-admin/bin/delete_user.php --username=NAME\n");
    exit(1);
}
$stmt = Database::connection()->prepare('DELETE FROM users WHERE username = ?');
$stmt->execute([$username]);
fwrite(STDOUT, $stmt->rowCount() ? "Deleted user '$username'.\n" : "No user named '$username'.\n");
exit($stmt->rowCount() ? 0 : 1);
