<?php
/**
 * Authentication for the PHP admin panel.
 *
 * Passwords are stored as bcrypt hashes (password_hash) in the shared
 * `users` table.  Only accounts with role = 'admin' may use this panel.
 * The Java portal has its OWN session and login; sessions are not shared.
 */
declare(strict_types=1);

final class Auth
{
    public const SESSION_KEY = 'auth_user';

    /**
     * Check a username + password.
     * @return array{ok:bool, user?:array, error?:string}
     */
    public static function attempt(string $username, string $password): array
    {
        $username = trim($username);
        $generic = 'Invalid username or password.';
        if ($username === '' || $password === '' || strlen($username) > 50 || strlen($password) > 72) {
            return ['ok' => false, 'error' => $generic];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, username, display_name, password_hash, role, is_active
               FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Always run password_verify (even for unknown users) so response
        // time does not reveal whether a username exists.
        // (the fallback is the hash of a random, discarded string)
        $hash = $user['password_hash'] ?? '$2y$12$cekMoviHRWlUA5DO/qQji.STqZKzOAnhee8z6V56HMXW/usEabYwu';
        $passwordOk = password_verify($password, $hash);

        if (!$user || !$passwordOk || !(int) $user['is_active']) {
            usleep(random_int(150000, 350000));     // slow down brute-force attempts
            return ['ok' => false, 'error' => $generic];
        }
        if ($user['role'] !== 'admin') {
            return ['ok' => false, 'error' => 'Your account does not have access to the admin panel. Use the profile portal instead.'];
        }

        // Upgrade old hashes transparently if the cost factor changes.
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $upd = Database::connection()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
        }
        return ['ok' => true, 'user' => $user];
    }

    /** Mark the session as logged in. */
    public static function login(array $user): void
    {
        // New session ID after login -> an attacker who planted/knew the old
        // ID (session fixation) cannot use it.
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id'           => (int) $user['id'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'role'         => $user['role'],
        ];
        $_SESSION['login_at'] = time();
        Csrf::rotate();

        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$user['id']]);
    }

    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Guard for every protected page: redirects to the login page when not
     * logged in, and re-checks the account in the database on every request
     * (a deactivated or demoted admin loses access immediately).
     */
    public static function requireAdmin(): array
    {
        $user = self::user();
        if ($user === null) {
            if (wants_json()) {
                json_response(['ok' => false, 'message' => 'Authentication required.'], 401);
            }
            $query = Session::$expired ? ['expired' => 1] : [];
            $target = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if ($target !== '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $query['next'] = $target . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
            }
            redirect('login.php', $query);
        }

        $stmt = Database::connection()->prepare('SELECT role, is_active FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row || !(int) $row['is_active'] || $row['role'] !== 'admin') {
            Session::destroy();
            throw new ForbiddenException('Your account no longer has access to the admin panel.');
        }
        return $user;
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}
