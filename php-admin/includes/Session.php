<?php
/**
 * Secure session start + idle timeout.
 *
 *  - Custom cookie name so it never clashes with other apps on localhost
 *  - HttpOnly (JavaScript cannot read the cookie), SameSite=Lax, strict mode
 *    (PHP refuses session IDs it did not create -> blocks session fixation)
 *  - Idle timeout from SESSION_TIMEOUT_MINUTES in .env
 */
declare(strict_types=1);

final class Session
{
    public const NAME = 'SMP_ADMIN_SESSION';

    /** Set to true when this request found an expired session. */
    public static bool $expired = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) app_config()['session_timeout']);

        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,                 // until the browser closes
            'path'     => base_path(),       // only sent to the PHP admin pages
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // ---- idle timeout ----
        $now = time();
        $last = $_SESSION['last_activity'] ?? null;
        if ($last !== null && ($now - (int) $last) > app_config()['session_timeout']) {
            $wasLoggedIn = isset($_SESSION[Auth::SESSION_KEY]);
            self::destroy();
            session_start();                 // fresh, empty session for the login page
            self::$expired = $wasLoggedIn;
        }
        $_SESSION['last_activity'] = $now;
    }

    /** Empty the session, expire the cookie and destroy server-side data. */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?: 'Lax',
            ]);
        }
        session_destroy();
    }
}
