<?php
/**
 * CSRF (Cross-Site Request Forgery) protection.
 *
 * Every state-changing form (add, edit, delete, upload, logout) contains a
 * hidden random token stored in the user's session.  A forged request from
 * another website cannot know the token, so it is rejected.
 * hash_equals() compares in constant time (no timing leaks).
 */
declare(strict_types=1);

final class Csrf
{
    private const KEY = 'csrf_token';
    private const REG_KEY = 'registration_token';
    private const REG_TTL = 3600;          // registration token valid for 1 hour

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Hidden <input> to place inside every POST form. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    /** New token after login (prevents re-use of a token issued before login). */
    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }

    public static function isValid(mixed $submitted): bool
    {
        return is_string($submitted)
            && !empty($_SESSION[self::KEY])
            && hash_equals($_SESSION[self::KEY], $submitted);
    }

    /** Abort the request with HTTP 403 when the token is missing or wrong. */
    public static function requireValid(): void
    {
        // When a request exceeds PHP's post_max_size, PHP silently drops ALL
        // fields (including the token).  Report the real cause instead.
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            throw new UploadException('The submitted files are too large for the server to accept. Upload smaller files (photos up to 2 MB, documents up to 5 MB).');
        }
        if (!self::isValid($_POST['csrf_token'] ?? null)) {
            error_log('[csrf] rejected ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? ''));
            throw new ForbiddenException('Your form has expired or is invalid (security token mismatch). Please reload the page and try again.');
        }
    }

    // ---------------------------------------------------------------
    // Single-use token for the public registration form (Question 1).
    // The static HTML page fetches it from api/registration-token.php.
    // It is consumed after a successful registration, so the same form
    // cannot be submitted twice.
    // ---------------------------------------------------------------
    public static function issueRegistrationToken(): string
    {
        $_SESSION[self::REG_KEY] = ['value' => bin2hex(random_bytes(32)), 'issued' => time()];
        return $_SESSION[self::REG_KEY]['value'];
    }

    public static function isValidRegistrationToken(mixed $submitted): bool
    {
        $stored = $_SESSION[self::REG_KEY] ?? null;
        return is_string($submitted)
            && is_array($stored)
            && (time() - $stored['issued']) <= self::REG_TTL
            && hash_equals($stored['value'], $submitted);
    }

    public static function consumeRegistrationToken(): void
    {
        unset($_SESSION[self::REG_KEY]);
    }
}
