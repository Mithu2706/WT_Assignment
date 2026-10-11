<?php
/**
 * Included at the top of every PHP page.
 *  - loads configuration and classes
 *  - hides errors from users (logged to storage/logs/php-error.log instead)
 *  - sends security headers
 *  - starts the secure session
 *  - converts uncaught exceptions into friendly error pages
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));          // .../php-admin

require APP_ROOT . '/includes/exceptions.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/Database.php';
require APP_ROOT . '/includes/Session.php';
require APP_ROOT . '/includes/Csrf.php';
require APP_ROOT . '/includes/Auth.php';
require APP_ROOT . '/includes/Validator.php';
require APP_ROOT . '/includes/FileStorage.php';
require APP_ROOT . '/includes/Repositories.php';
require APP_ROOT . '/includes/StudentService.php';

// ---------- error handling: log everything, display nothing ----------
$logDir = app_config()['log_dir'];
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'php-error.log');
mb_internal_encoding('UTF-8');

// PHP warnings become exceptions so they are never silently ignored.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;                           // suppressed with @
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof AppException) {
        $status = $e->httpStatus();
        $message = $e->getMessage();
    } else {
        // Unexpected error: full details to the log, generic text to the user.
        error_log('[uncaught] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $status = 500;
        $message = 'Something went wrong on our side. The problem has been logged; please try again.';
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Error: $message\n");
        exit(1);
    }
    if (wants_json()) {
        $payload = ['ok' => false, 'message' => $message];
        if ($e instanceof ValidationException) {
            $payload['errors'] = $e->errors;
        }
        json_response($payload, $status);
    }
    $titles = [403 => 'Access denied', 404 => 'Not found', 422 => 'Could not complete the request', 503 => 'Service unavailable'];
    render_error_page($status, $titles[$status] ?? 'Unexpected error', $message);
});

if (PHP_SAPI !== 'cli') {
    // ---------- security headers ----------
    header_remove('X-Powered-By');                       // do not advertise the PHP version
    header('X-Content-Type-Options: nosniff');           // browsers must not guess content types
    header('X-Frame-Options: DENY');                     // no clickjacking via iframes
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; "
        . "object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

    Session::start();
}
