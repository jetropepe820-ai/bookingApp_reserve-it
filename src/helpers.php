<?php

declare(strict_types=1);

function config(?string $key = null): mixed
{
    static $settings;
    $settings ??= require dirname(__DIR__) . '/config.php';
    if ($key === null) return $settings;
    return $settings[$key] ?? null;
}

function db(): PDO { return Database::connection(); }
function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function url(string $route = 'home', array $parameters = []): string
{
    return config('app_path') . '/?r=' . rawurlencode($route) . ($parameters ? '&' . http_build_query($parameters) : '');
}

function absolute_url(string $route, array $parameters = []): string { return config('app_url') . url($route, $parameters); }

function redirect(string $route, array $parameters = []): never
{
    header('Location: ' . url($route, $parameters), true, 303);
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'; }

function verify_csrf(): void
{
    $submitted = $_POST['_csrf'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your form expired. Go back, reload the page, and try again.');
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) return $user;
    $loaded = true;
    if (empty($_SESSION['user_id'])) return null;
    $statement = db()->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$_SESSION['user_id']]);
    $user = $statement->fetch() ?: null;
    if (!$user || !(int) $user['is_active']) { unset($_SESSION['user_id']); return null; }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) { flash('error', 'Please sign in to continue.'); redirect('login'); }
    return $user;
}

function require_admin(): array
{
    $user = $GLOBALS['request_api_user'] ?? require_login();
    if ($user['role'] !== 'admin') { http_response_code(403); exit('Administrator access required.'); }
    return $user;
}

function render(string $view, array $data = []): void
{
    $title = $data['title'] ?? ucwords(str_replace(['/', '.', '-'], ' ', $view));
    extract($data, EXTR_SKIP);
    $viewFile = dirname(__DIR__) . '/views/' . $view . '.php';
    if (!is_file($viewFile)) { http_response_code(500); exit('Page template not found.'); }
    require dirname(__DIR__) . '/views/layout.php';
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function request_string(string $key, int $max = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return mb_substr($value, 0, $max);
}

function format_datetime(string $value, string $format = 'M j, Y g:i A'): string
{
    return (new DateTimeImmutable($value, new DateTimeZone(config('timezone'))))->format($format);
}

function court_photo_url(array $court): string
{
    if (!empty($court['photo_path'])) return url('photo', ['file' => $court['photo_path']]);
    if (!empty($court['image_url'])) return (string) $court['image_url'];
    return 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1000&q=85';
}

function asset_url(string $name): never
{
    $types = ['app.js' => 'text/javascript; charset=utf-8', 'site.css' => 'text/css; charset=utf-8'];
    if (!isset($types[$name])) { http_response_code(404); exit; }
    $path = dirname(__DIR__) . '/public/assets/' . $name;
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: ' . $types[$name]); header('Cache-Control: public, max-age=3600'); readfile($path); exit;
}

function active_status(string $status): string { return '<span class="rounded-full px-3 py-1 text-xs font-semibold ' . match ($status) {
    'pending' => 'bg-amber-100 text-amber-800', 'confirmed', 'completed' => 'bg-lime-100 text-lime-800',
    'cancelled', 'rejected' => 'bg-rose-100 text-rose-700', default => 'bg-slate-100 text-slate-600',
} . '">' . h(ucfirst($status)) . '</span>'; }

function log_message(string $message): void
{
    $file = dirname(__DIR__) . '/storage/app.log';
    file_put_contents($file, '[' . date(DATE_ATOM) . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function rate_limit_allow(string $key, int $maximum, int $windowSeconds): bool
{
    $directory = dirname(__DIR__) . '/storage/throttle';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) return false;
    $file = $directory . '/' . hash('sha256', $key) . '.json';
    $handle = fopen($file, 'c+');
    if (!$handle) return false;
    try {
        if (!flock($handle, LOCK_EX)) return false;
        $raw = stream_get_contents($handle);
        $attempts = json_decode($raw ?: '[]', true);
        if (!is_array($attempts)) $attempts = [];
        $attempts = array_values(array_filter($attempts, fn ($stamp) => is_int($stamp) && $stamp > time() - $windowSeconds));
        if (count($attempts) >= $maximum) { ftruncate($handle, 0); rewind($handle); fwrite($handle, json_encode($attempts)); fflush($handle); return false; }
        $attempts[] = time(); ftruncate($handle, 0); rewind($handle); fwrite($handle, json_encode($attempts)); fflush($handle);
        return true;
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function rate_limit_clear(string $key): void
{
    $file = dirname(__DIR__) . '/storage/throttle/' . hash('sha256', $key) . '.json';
    if (is_file($file)) @unlink($file);
}
