<?php
/**
 * Streams an uploaded file to a logged-in admin.
 *   file.php?id=12             -> shown inline (images, PDFs)
 *   file.php?id=12&download=1  -> "Save as" download
 *
 * Files live outside the web root; this script is the ONLY way to read them.
 * The path comes from the database (never from the URL) and is re-validated
 * by FileStorage::resolve() so it cannot escape the storage folder.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

Auth::requireAdmin();

$id = input_id($_GET);
if ($id === null) {
    throw new NotFoundException('Invalid file ID.');
}
$file = FileRepository::find($id) ?? throw new NotFoundException('That file does not exist or was deleted.');

$path = (new FileStorage())->resolve($file['relative_path'], (int) $file['student_id']);
if ($path === null) {
    error_log("[file] metadata $id exists but file is missing: " . $file['relative_path']);
    throw new NotFoundException('The file record exists but the file itself is missing from storage. Please upload it again.');
}

// Only types we accepted at upload time are ever served.
$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$mime = in_array($file['mime_type'], $allowedMime, true) ? $file['mime_type'] : 'application/octet-stream';
$download = isset($_GET['download']) || $mime === 'application/octet-stream';

// Safe Content-Disposition: ASCII fallback + RFC 5987 UTF-8 name; quotes/CR/LF removed.
$name = $file['original_name'];
$ascii = preg_replace('/[^A-Za-z0-9 ._()\-]/', '_', $name) ?: 'file';
$disposition = ($download ? 'attachment' : 'inline')
    . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);

// Release the session lock so other requests (e.g. thumbnails) are not blocked while streaming.
session_write_close();

// The page CSP is removed here: a CSP on a PDF response can stop the browser's
// built-in PDF viewer.  Safety comes from the fixed MIME whitelist + nosniff.
header_remove('Content-Security-Policy');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . $disposition);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
