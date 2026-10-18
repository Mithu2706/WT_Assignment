<?php
/**
 * includes/upload_handler.php
 * ---------------------------------------------------------------------
 * Secure file-upload handling for profile photos and documents.
 *
 * Security checks performed on every upload (validate_upload):
 *   1. PHP upload error code (UPLOAD_ERR_*) is checked first
 *   2. is_uploaded_file()     – the file really came from an HTTP upload
 *   3. size > 0 and ≤ limit   – 2 MB photos, 5 MB documents
 *   4. extension whitelist    – only jpg/jpeg/png/webp (photos), pdf/jpg/jpeg/png (documents)
 *   5. dangerous extensions   – "shell.php.jpg" style double extensions are rejected
 *   6. MIME type from CONTENT – finfo reads the real file bytes; the browser-supplied
 *                               type and the original filename are never trusted
 *   7. getimagesize()         – images must really decode as images
 *
 * Storing (store_upload):
 *   - a random 32-hex-character name is generated (random_bytes) so two
 *     uploads can NEVER overwrite each other, even with the same original name
 *   - move_uploaded_file() places it in uploads/profiles or uploads/documents
 *   - metadata (student id, path, type, size, date…) is inserted into MySQL
 *   - the uploads folder has an .htaccess that blocks direct web access and
 *     script execution; files are only served through view_file.php
 */

declare(strict_types=1);

/** Rules for each upload category: sub-folder, size limit, allowed extension → MIME types. */
const UPLOAD_RULES = [
    'profile' => [
        'folder'   => 'profiles',
        'max_size' => MAX_PROFILE_SIZE,
        'types'    => [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
        ],
    ],
    'document' => [
        'folder'   => 'documents',
        'max_size' => MAX_DOCUMENT_SIZE,
        'types'    => [
            'pdf'  => ['application/pdf'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
        ],
    ],
];

/** Extensions that could be executed by a web server – always rejected. */
const DANGEROUS_EXTENSIONS = [
    'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
    'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'jspx', 'sh', 'bat', 'cmd', 'exe',
    'dll', 'js', 'html', 'htm', 'shtml', 'svg', 'htaccess',
];

/** True when the user actually selected a file in this <input type="file">. */
function upload_was_attempted(mixed $file): bool
{
    return is_array($file) && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;
}

/** Turn PHP's numeric upload error codes into readable messages. */
function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'Please choose a file to upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server error: temporary upload folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server error: could not write the file to disk.',
        UPLOAD_ERR_EXTENSION  => 'The upload was stopped by a server extension.',
        default               => 'Unknown upload error.',
    };
}

/**
 * Validate one uploaded file from $_FILES.
 *
 * @return array{ok: bool, error?: string, extension?: string, mime?: string, original_name?: string}
 */
function validate_upload(mixed $file, string $category): array
{
    $rules = UPLOAD_RULES[$category] ?? null;
    if ($rules === null) {
        return ['ok' => false, 'error' => 'Unknown file category.'];
    }

    // Reject malformed input (e.g. name="file[]" arrays sent by a tampered form)
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])
        || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Invalid file upload request.'];
    }

    // 1. PHP upload error code
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message((int) $file['error'])];
    }

    // 2. Must be a genuine HTTP upload (protects against path tricks)
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Possible file upload attack detected.'];
    }

    // 3. Size limits
    $size = (int) $file['size'];
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'The uploaded file is empty.'];
    }
    if ($size > $rules['max_size']) {
        return ['ok' => false, 'error' => sprintf(
            'File is too large (%s). Maximum allowed size is %s.',
            format_bytes($size),
            format_bytes($rules['max_size'])
        )];
    }

    // Clean the original name – used ONLY for display, never as the stored name
    $originalName = basename(str_replace('\\', '/', (string) $file['name']));
    $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? '';
    $originalName = mb_substr(trim($originalName), 0, 200);
    if ($originalName === '' || !str_contains($originalName, '.')) {
        return ['ok' => false, 'error' => 'The file must have a valid name and extension.'];
    }

    // 4 + 5. Extension checks (every part after a dot, to catch "photo.php.jpg")
    $parts = explode('.', strtolower($originalName));
    $extension = (string) end($parts);
    foreach (array_slice($parts, 1) as $part) {
        if (in_array($part, DANGEROUS_EXTENSIONS, true)) {
            return ['ok' => false, 'error' => 'This file type is not allowed for security reasons.'];
        }
    }
    $allowedExtensions = array_keys($rules['types']);
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['ok' => false, 'error' => sprintf(
            'Unsupported file type ".%s". Allowed types: %s.',
            $extension,
            strtoupper(implode(', ', $allowedExtensions))
        )];
    }

    // 6. Detect the REAL MIME type from the file's bytes (magic numbers)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!in_array($mime, $rules['types'][$extension], true)) {
        return ['ok' => false, 'error' => sprintf(
            'The file content (%s) does not match the .%s extension.',
            $mime,
            $extension
        )];
    }

    // 7. Images must be decodable images
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'The image file is corrupted or not a real image.'];
    }

    return ['ok' => true, 'extension' => $extension, 'mime' => $mime, 'original_name' => $originalName];
}

/**
 * Move a validated upload into the uploads folder and save its metadata.
 * Returns ['id' => new student_files.id, 'file_path' => relative path].
 * Throws RuntimeException on failure (the caller shows a friendly message).
 */
function store_upload(int $studentId, array $file, string $category, array $validated, ?string $label = null): array
{
    $folder = UPLOAD_BASE_DIR . DIRECTORY_SEPARATOR . UPLOAD_RULES[$category]['folder'];
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Upload folder could not be created.');
    }
    if (!is_writable($folder)) {
        throw new RuntimeException('Upload folder is not writable.');
    }

    // Random, unguessable name – loop is a safety net so we NEVER overwrite a file
    do {
        $storedName = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        $destination = $folder . DIRECTORY_SEPARATOR . $storedName;
    } while (file_exists($destination));

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The uploaded file could not be saved.');
    }
    @chmod($destination, 0644);   // read/write for owner, read-only for others – never executable

    // Relative path (forward slashes) so both PHP and the Java servlet can resolve it
    $relativePath = 'uploads/' . UPLOAD_RULES[$category]['folder'] . '/' . $storedName;

    try {
        $stmt = db()->prepare(
            'INSERT INTO student_files
                 (student_id, file_category, document_label, original_name, stored_name, file_path, file_type, file_size)
             VALUES (:student_id, :category, :label, :original_name, :stored_name, :file_path, :file_type, :file_size)'
        );
        $stmt->execute([
            ':student_id'    => $studentId,
            ':category'      => $category,
            ':label'         => $category === 'document' ? ($label ?? 'Other') : null,
            ':original_name' => $validated['original_name'],
            ':stored_name'   => $storedName,
            ':file_path'     => $relativePath,
            ':file_type'     => $validated['mime'],
            ':file_size'     => filesize($destination),
        ]);
    } catch (PDOException $e) {
        // Database insert failed → remove the file so disk and DB stay consistent
        @unlink($destination);
        error_log('File metadata insert failed: ' . $e->getMessage());
        throw new RuntimeException('The file information could not be saved to the database.');
    }

    return ['id' => (int) db()->lastInsertId(), 'file_path' => $relativePath];
}

/** True when the request body was bigger than PHP's post_max_size (PHP then empties $_POST and $_FILES). */
function post_body_too_large(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

/**
 * Convert a relative path from the database into a safe absolute path.
 * Returns null if the file is missing OR the path points outside uploads/
 * (protects against "../../" path-traversal tricks).
 */
function resolve_upload_path(string $relativePath): ?string
{
    $baseDir = realpath(UPLOAD_BASE_DIR);
    $fullPath = realpath(ADMIN_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

    if ($baseDir === false || $fullPath === false || !is_file($fullPath)) {
        return null;
    }
    if (!str_starts_with($fullPath, $baseDir . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $fullPath;
}

/** Delete a stored file from disk (if it still exists). */
function delete_physical_file(string $relativePath): void
{
    $path = resolve_upload_path($relativePath);
    if ($path !== null && !@unlink($path)) {
        error_log('Could not delete file: ' . $relativePath);
    }
}

/**
 * A student has only ONE current profile photo. After a new photo is stored,
 * remove the older ones (both the database rows and the files on disk).
 */
function remove_old_profile_photos(int $studentId, int $keepFileId): void
{
    $stmt = db()->prepare(
        "SELECT id, file_path FROM student_files
         WHERE student_id = :sid AND file_category = 'profile' AND id <> :keep"
    );
    $stmt->execute([':sid' => $studentId, ':keep' => $keepFileId]);
    $oldPhotos = $stmt->fetchAll();

    $delete = db()->prepare('DELETE FROM student_files WHERE id = :id');
    foreach ($oldPhotos as $photo) {
        $delete->execute([':id' => $photo['id']]);
        delete_physical_file($photo['file_path']);
    }
}

/** Latest profile photo id of a student (or null) – used for <img> thumbnails. */
function get_profile_photo_id(int $studentId): ?int
{
    $stmt = db()->prepare(
        "SELECT id FROM student_files WHERE student_id = :sid AND file_category = 'profile'
         ORDER BY upload_date DESC, id DESC LIMIT 1"
    );
    $stmt->execute([':sid' => $studentId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}
