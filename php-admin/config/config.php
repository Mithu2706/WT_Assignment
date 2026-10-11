<?php
/**
 * Loads application settings from the shared ".env" file in the project root
 * (the Java module reads the same file).  Real environment variables, when
 * set, take precedence over the file - useful on a server.
 *
 * Returns an associative array; nothing secret is ever echoed.
 */
declare(strict_types=1);

return (function (): array {
    $projectRoot = dirname(__DIR__, 2);
    $envFile = getenv('SMP_CONFIG') ?: $projectRoot . DIRECTORY_SEPARATOR . '.env';

    $values = [];
    if (is_file($envFile) && is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            // Strip optional surrounding quotes: KEY="value"
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            $values[$key] = $value;
        }
    }

    $get = static function (string $key, string $default = '') use ($values): string {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return $values[$key] ?? $default;
    };

    // A relative STORAGE_DIR is resolved against the folder that holds .env
    $storage = $get('STORAGE_DIR', 'storage/uploads');
    $isAbsolute = preg_match('#^([A-Za-z]:)?[\\\\/]#', $storage) === 1;
    if (!$isAbsolute) {
        $storage = dirname($envFile) . DIRECTORY_SEPARATOR . $storage;
    }

    return [
        'env_file'        => $envFile,
        'env_loaded'      => $values !== [],
        'app_env'         => $get('APP_ENV', 'production'),
        'db' => [
            'host' => $get('DB_HOST', '127.0.0.1'),
            'port' => (int) $get('DB_PORT', '3306'),
            'name' => $get('DB_NAME', 'wt_student_portal'),
            'user' => $get('DB_USER', 'smp_app'),
            'pass' => $get('DB_PASS', ''),
        ],
        'storage_dir'     => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storage),
        'log_dir'         => $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
        'session_timeout' => max(1, (int) $get('SESSION_TIMEOUT_MINUTES', '30')) * 60,
    ];
})();
