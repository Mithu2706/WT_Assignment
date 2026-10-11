<?php
/**
 * Small helper functions used by every page.
 */
declare(strict_types=1);

/** Returns the loaded configuration array (see config/config.php). */
function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/config.php';
    }
    return $config;
}

/**
 * HTML-escape a value for safe output (prevents XSS).
 * EVERY dynamic value printed in a template goes through e().
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * URL path of the public/ folder, e.g. "/wt-portal/php-admin/public/".
 * Worked out from the running script, so the app works in any sub-folder.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $publicDir = realpath(APP_ROOT . '/public');
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $relative = '';
    if ($publicDir && str_starts_with($script, $publicDir)) {
        $relative = str_replace('\\', '/', substr($script, strlen($publicDir) + 1));
    }
    $base = ($relative !== '' && str_ends_with($scriptName, $relative))
        ? substr($scriptName, 0, -strlen($relative))
        : rtrim(dirname($scriptName), '/') . '/';
    return $base;
}

/** Build a URL to a page inside public/, with an optional query string. */
function url(string $page, array $query = []): string
{
    $url = base_path() . ltrim($page, '/');
    return $query ? $url . '?' . http_build_query($query) : $url;
}

/** Redirect (303 so a POST is followed by a GET - the PRG pattern) and stop. */
function redirect(string $page, array $query = []): never
{
    header('Location: ' . url($page, $query), true, 303);
    exit;
}

/** Store a one-time message shown on the next page ("flash" message). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Keep submitted form values so a form can be re-displayed after an error. */
function remember_input(array $input): void
{
    unset($input['csrf_token'], $input['password'], $input['confirm_password']);
    $_SESSION['old_input'] = $input;
}

function take_old_input(): array
{
    $old = $_SESSION['old_input'] ?? [];
    unset($_SESSION['old_input']);
    return $old;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        render_error_page(405, 'Method not allowed', 'This action can only be performed by submitting a form.');
        exit;
    }
}

/** Read a positive integer id from GET/POST; null when missing or malformed. */
function input_id(array $source, string $key = 'id'): ?int
{
    $value = $source[$key] ?? null;
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/', $value)) {
        return null;
    }
    return (int) $value;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}

function format_date(?string $datetime, string $format = 'd M Y'): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '—';
}

/** Two-letter initials used for avatar placeholders. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters ?: '?';
}

/** Human label for a file category key. */
function category_label(string $category): string
{
    return FileStorage::CATEGORY_LABELS[$category] ?? ucfirst(str_replace('_', ' ', $category));
}

/** Render the shared error page (used by the exception handler too). */
function render_error_page(int $status, string $title, string $message): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }
    $pageTitle = $title;
    require APP_ROOT . '/includes/views/error.php';
}
