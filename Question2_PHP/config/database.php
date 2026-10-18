<?php
/**
 * config/database.php
 * ---------------------------------------------------------------------
 * Central configuration for the PHP admin panel (Question 2).
 *
 *  - MySQL connection settings      → change DB_USER / DB_PASS for your PC
 *  - Upload folder + size limits    → used by includes/upload_handler.php
 *  - db() helper                    → returns ONE shared PDO connection
 *
 * Values can also be supplied through environment variables
 * (SP_DB_HOST, SP_DB_NAME, SP_DB_USER, SP_DB_PASS) so that no real
 * password has to be written into the source code.
 */

declare(strict_types=1);

// ---------- MySQL settings (XAMPP default: user "root", empty password) ----------
define('DB_HOST', getenv('SP_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('SP_DB_PORT') ?: '3306');
define('DB_NAME', getenv('SP_DB_NAME') ?: 'student_portal');
define('DB_USER', getenv('SP_DB_USER') ?: 'root');
define('DB_PASS', getenv('SP_DB_PASS') !== false ? (string) getenv('SP_DB_PASS') : '');

// ---------- Upload settings ----------
// ADMIN_ROOT  = the Question2_PHP folder. File paths in the database are stored
//               RELATIVE to this folder, e.g. "uploads/profiles/9f3c...e1.jpg".
define('ADMIN_ROOT', dirname(__DIR__));
define('UPLOAD_BASE_DIR', ADMIN_ROOT . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_PROFILE_SIZE', 2 * 1024 * 1024);    // 2 MB for profile photos
define('MAX_DOCUMENT_SIZE', 5 * 1024 * 1024);   // 5 MB for documents

// ---------- Lists used by forms and validation ----------
const COURSES = [
    'B.E. Computer Science and Engineering',
    'B.E. Electronics and Communication Engineering',
    'B.E. Electrical and Electronics Engineering',
    'B.E. Mechanical Engineering',
    'B.E. Civil Engineering',
    'B.Tech. Information Technology',
    'B.Tech. Artificial Intelligence and Data Science',
];

const DOCUMENT_LABELS = ['ID Proof', 'Certificate', 'Mark Sheet', 'Other'];

/**
 * Returns a shared PDO connection to MySQL.
 *  - ERRMODE_EXCEPTION  : every SQL error throws a PDOException we can catch
 *  - EMULATE_PREPARES=false : real server-side prepared statements
 *  - FETCH_ASSOC        : rows come back as ['column' => value] arrays
 * If the connection fails we log the technical reason and show a friendly
 * error page instead of a stack trace.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            render_error_page(
                'Database connection failed',
                'The application could not connect to the MySQL database. Please make sure MySQL is running '
                . 'and the settings in config/database.php are correct.',
                500
            );
        }
    }

    return $pdo;
}
