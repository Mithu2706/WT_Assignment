<?php
/**
 * Secure file storage for student photos and documents.
 *
 * Upload flow (see docs/CODE_EXPLANATION.md):
 *   1. validate()  - PHP upload error code, real upload check, size limit,
 *                    REAL type detected from the file bytes (finfo), image
 *                    decode check, PDF header check, embedded-code check.
 *                    The client's filename / extension / MIME are NEVER trusted.
 *   2. store()     - moves the file to  STORAGE_DIR/students/<id>/<category>/
 *                    with a random 32-hex-character name + an extension chosen
 *                    from the DETECTED type, so files can never overwrite each
 *                    other and a ".php" file can never be created.
 *   3. resolve()   - turns a DB relative path back into an absolute path,
 *                    refusing anything that could escape the storage folder.
 *
 * The storage folder is outside public/ (and protected by .htaccess), so
 * uploaded files can only be reached through the authenticated file.php
 * (PHP) or FileServlet (Java) endpoints.
 */
declare(strict_types=1);

final class FileStorage
{
    public const CATEGORY_LABELS = [
        'profile_photo' => 'Profile photo',
        'id_proof'      => 'ID proof',
        'certificate'   => 'Certificate',
        'marksheet'     => 'Marksheet',
        'other'         => 'Other document',
    ];

    /** Allowed detected MIME type => extension used for the stored file. */
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const DOCUMENT_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public const MAX_PHOTO_BYTES = 2 * 1024 * 1024;      // 2 MB
    public const MAX_DOCUMENT_BYTES = 5 * 1024 * 1024;   // 5 MB
    private const MAX_IMAGE_PIXELS = 40_000_000;         // guards against "decompression bomb" images

    /** Strict shape of every path we generate - used again when reading. */
    private const PATH_PATTERN = '#^students/([1-9][0-9]*)/(profile_photo|id_proof|certificate|marksheet|other)/([a-f0-9]{32})\.(jpg|png|webp|pdf)$#';

    private string $root;

    public function __construct(?string $root = null)
    {
        $root = $root ?? app_config()['storage_dir'];
        if (!is_dir($root) && !@mkdir($root, 0750, true) && !is_dir($root)) {
            error_log('[storage] cannot create storage directory: ' . $root);
            throw new AppException('File storage is not available. Please contact the administrator.');
        }
        $this->root = rtrim((string) realpath($root), '\\/');
    }

    public function root(): string
    {
        return $this->root;
    }

    public static function isValidCategory(string $category): bool
    {
        return isset(self::CATEGORY_LABELS[$category]);
    }

    public static function allowedTypesFor(string $category): array
    {
        return $category === 'profile_photo' ? self::IMAGE_TYPES : self::DOCUMENT_TYPES;
    }

    public static function maxBytesFor(string $category): int
    {
        return $category === 'profile_photo' ? self::MAX_PHOTO_BYTES : self::MAX_DOCUMENT_BYTES;
    }

    /** Human description for form hints, e.g. "JPG, PNG or WebP · max 2 MB". */
    public static function hintFor(string $category): string
    {
        $types = $category === 'profile_photo' ? 'JPG, PNG or WebP' : 'PDF, JPG, PNG or WebP';
        return $types . ' · max ' . format_bytes(self::maxBytesFor($category));
    }

    /**
     * Validate one entry of $_FILES.
     *
     * @param array  $file          one $_FILES[...] entry
     * @param string $category      file category (decides allowed types / size)
     * @param string $field         form field name (for error messages)
     * @param bool   $requireHttpUpload  false only for the CLI seeding script
     * @return array{tmp:string, mime:string, ext:string, size:int, original:string, sha256:string}
     */
    public function validate(array $file, string $category, string $field = 'file', bool $requireHttpUpload = true): array
    {
        if (!self::isValidCategory($category)) {
            throw new UploadException('Choose a valid document category.', 'category');
        }
        $label = self::CATEGORY_LABELS[$category];

        // Arrays here would mean "name[]" tricks - only single files are accepted.
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new UploadException('Invalid upload request.', $field);
        }
        switch ((int) $file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new UploadException('Choose a file to upload.', $field);
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new UploadException("$label is too large. The maximum size is " . format_bytes(self::maxBytesFor($category)) . '.', $field);
            case UPLOAD_ERR_PARTIAL:
                throw new UploadException('The upload was interrupted. Please try again.', $field);
            default:
                // NO_TMP_DIR, CANT_WRITE, EXTENSION: server problems
                error_log('[upload] PHP upload error code ' . (int) $file['error']);
                throw new UploadException('The server could not receive the file. Please try again later.', $field);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        // is_uploaded_file() proves the path really came from this HTTP upload
        // (stops tricks like tmp_name = "C:/xampp/.../config.php").
        if ($tmp === '' || !is_file($tmp) || ($requireHttpUpload && !is_uploaded_file($tmp))) {
            throw new UploadException('The upload could not be verified. Please try again.', $field);
        }

        $size = (int) filesize($tmp);
        if ($size <= 0) {
            throw new UploadException('The selected file is empty.', $field);
        }
        if ($size > self::maxBytesFor($category)) {
            throw new UploadException("$label is " . format_bytes($size) . '. The maximum size is ' . format_bytes(self::maxBytesFor($category)) . '.', $field);
        }

        // Detect the REAL type from the file contents ("magic bytes").
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
        $allowed = self::allowedTypesFor($category);
        if (!isset($allowed[$mime])) {
            $what = $category === 'profile_photo' ? 'a JPG, PNG or WebP image' : 'a PDF, JPG, PNG or WebP file';
            throw new UploadException("$label must be $what. The uploaded file's contents are not an allowed type.", $field);
        }

        if (str_starts_with($mime, 'image/')) {
            // getimagesize() must be able to parse the image header.
            $info = @getimagesize($tmp);
            if ($info === false || ($info['mime'] ?? '') !== $mime) {
                throw new UploadException('The image appears to be damaged or is not a real image.', $field);
            }
            if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_IMAGE_PIXELS) {
                throw new UploadException('The image dimensions are not acceptable.', $field);
            }
        } elseif ($mime === 'application/pdf') {
            $head = (string) file_get_contents($tmp, false, null, 0, 5);
            if ($head !== '%PDF-') {
                throw new UploadException('The PDF file appears to be damaged.', $field);
            }
        }

        // Defence in depth: refuse files that carry embedded server-side code
        // (e.g. a valid JPEG with "<?php ..." appended - a "polyglot").
        // Only long, distinctive markers are checked so that random bytes in
        // compressed image/PDF data cannot trigger a false rejection.
        $content = (string) file_get_contents($tmp);
        if (preg_match('/<\?php|<script\b/i', $content)) {
            throw new UploadException('The file contains embedded code and was rejected for security reasons.', $field);
        }

        return [
            'tmp'      => $tmp,
            'mime'     => $mime,
            'ext'      => $allowed[$mime],
            'size'     => $size,
            'original' => self::cleanOriginalName((string) ($file['name'] ?? ''), $allowed[$mime]),
            'sha256'   => hash('sha256', $content),
        ];
    }

    /**
     * Move a validated file into STORAGE_DIR/students/<id>/<category>/<random>.<ext>.
     * @return array{stored_name:string, relative_path:string, absolute_path:string}
     */
    public function store(array $validated, int $studentId, string $category, bool $isHttpUpload = true): array
    {
        if ($studentId < 1 || !self::isValidCategory($category)) {
            throw new InvalidArgumentException('Invalid student id or category');
        }
        $dir = $this->root . DIRECTORY_SEPARATOR . 'students' . DIRECTORY_SEPARATOR . $studentId . DIRECTORY_SEPARATOR . $category;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            error_log('[storage] cannot create ' . $dir);
            throw new AppException('The file could not be saved. Please try again later.');
        }

        // Random name -> no overwrite, no guessing.  Loop is a belt-and-braces
        // check; a collision of 128 random bits is practically impossible.
        do {
            $storedName = bin2hex(random_bytes(16)) . '.' . $validated['ext'];
            $absolute = $dir . DIRECTORY_SEPARATOR . $storedName;
        } while (file_exists($absolute));

        $moved = $isHttpUpload
            ? move_uploaded_file($validated['tmp'], $absolute)
            : copy($validated['tmp'], $absolute);
        if (!$moved) {
            error_log('[storage] failed to move upload to ' . $absolute);
            throw new AppException('The file could not be saved. Please try again later.');
        }
        @chmod($absolute, 0640);

        return [
            'stored_name'   => $storedName,
            'relative_path' => "students/$studentId/$category/$storedName",
            'absolute_path' => $absolute,
        ];
    }

    /**
     * Convert a relative path from the database to an absolute path, or
     * return null if it is malformed, escapes the storage root, or is missing.
     */
    public function resolve(string $relativePath, ?int $expectedStudentId = null): ?string
    {
        if (!preg_match(self::PATH_PATTERN, $relativePath, $m)) {
            return null;                                   // "../", "C:\", "%2e%2e" ... all fail here
        }
        if ($expectedStudentId !== null && (int) $m[1] !== $expectedStudentId) {
            return null;                                   // file belongs to another student
        }
        $candidate = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return null;
        }
        // Final containment check after symlinks/junctions are resolved.
        if (!str_starts_with($real, $this->root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $real;
    }

    /**
     * Delete one stored file, but only if it belongs to $studentId.
     * Returns true when the file is gone (deleted now or already missing).
     */
    public function delete(string $relativePath, int $studentId): bool
    {
        if (!preg_match(self::PATH_PATTERN, $relativePath, $m) || (int) $m[1] !== $studentId) {
            error_log('[storage] refused to delete foreign/malformed path for student ' . $studentId . ': ' . $relativePath);
            return false;
        }
        $absolute = $this->resolve($relativePath, $studentId);
        if ($absolute === null) {
            return true;                                   // already missing
        }
        if (!@unlink($absolute)) {
            error_log('[storage] could not delete ' . $absolute);
            return false;
        }
        return true;
    }

    /** Remove the (now empty) folders of one student after deleting the student. */
    public function removeEmptyStudentDirectory(int $studentId): void
    {
        $base = $this->root . DIRECTORY_SEPARATOR . 'students' . DIRECTORY_SEPARATOR . $studentId;
        if (!is_dir($base)) {
            return;
        }
        foreach (array_keys(self::CATEGORY_LABELS) as $category) {
            $dir = $base . DIRECTORY_SEPARATOR . $category;
            if (is_dir($dir) && count(scandir($dir)) === 2) {    // only "." and ".."
                @rmdir($dir);
            }
        }
        if (count(scandir($base)) === 2) {
            @rmdir($base);
        }
    }

    /**
     * Keep the user's filename for DISPLAY only: strip any directory part,
     * control characters and odd symbols; cap the length; make sure the shown
     * extension matches the real type.
     */
    public static function cleanOriginalName(string $name, string $ext): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = preg_replace('/[^\p{L}\p{N} ._()\-]/u', '_', $name) ?? '';
        $name = trim($name, " .");
        $stem = pathinfo($name, PATHINFO_FILENAME);
        if ($stem === '') {
            $stem = 'file';
        }
        $stem = mb_substr($stem, 0, 120);
        return $stem . '.' . $ext;
    }
}
