<?php
/**
 * view_file.php – serves an uploaded file to the logged-in admin.
 * ---------------------------------------------------------------------
 * The uploads/ folder is blocked for direct web access (.htaccess), so
 * every photo/document is delivered through this script:
 *   - requires login
 *   - looks the file up by its numeric id (prepared statement)
 *   - resolves the path safely inside uploads/ (no path traversal)
 *   - sends the stored MIME type + nosniff header and streams the bytes
 *   - ?download=1 forces "Save as…" instead of showing it in the browser
 * The real filesystem path is never revealed to the browser.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$fileId = get_id_param('id');
if ($fileId === null) {
    render_error_page('Invalid file ID', 'The file ID is missing or not a valid number.', 400);
}

try {
    $stmt = db()->prepare('SELECT original_name, file_path, file_type FROM student_files WHERE id = :id');
    $stmt->execute([':id' => $fileId]);
    $file = $stmt->fetch();
} catch (PDOException $e) {
    error_log('view_file lookup failed: ' . $e->getMessage());
    render_error_page('Database error', 'The file could not be loaded.');
}

$path = $file ? resolve_upload_path($file['file_path']) : null;
if ($path === null) {
    render_error_page('File not found', 'The requested file does not exist on the server.', 404);
}

// Only ever send one of the whitelisted types
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$mime = in_array($file['file_type'], $allowedTypes, true) ? $file['file_type'] : 'application/octet-stream';
$disposition = isset($_GET['download']) ? 'attachment' : 'inline';

// Safe filename for the header: ASCII fallback + UTF-8 version (RFC 6266)
$asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file['original_name']);

session_write_close();   // release the session lock while streaming
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header(sprintf(
    "Content-Disposition: %s; filename=\"%s\"; filename*=UTF-8''%s",
    $disposition,
    $asciiName,
    rawurlencode($file['original_name'])
));
readfile($path);
exit;
