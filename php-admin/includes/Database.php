<?php
/**
 * PDO connection factory (one connection per request).
 *
 * Security notes
 *  - ATTR_EMULATE_PREPARES = false  -> real server-side prepared statements,
 *    so user input is always sent separately from the SQL text.
 *  - ERRMODE_EXCEPTION -> every SQL error throws; we catch and log it and
 *    show only a generic message to the user.
 */
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $db = app_config()['db'];
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
            try {
                self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                ]);
            } catch (PDOException $e) {
                // Log the real reason (wrong password, server down ...) but never show it.
                error_log('[db] connection failed: ' . $e->getMessage());
                throw new DatabaseUnavailableException(
                    'The database is not available right now. Please try again later or contact the administrator.'
                );
            }
        }
        return self::$pdo;
    }

    /** True when the PDOException is a UNIQUE / duplicate key violation. */
    public static function isDuplicateKey(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    /**
     * Run $work inside a transaction; commit on success, roll back on any
     * exception and re-throw it.
     */
    public static function transaction(callable $work): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $work($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
