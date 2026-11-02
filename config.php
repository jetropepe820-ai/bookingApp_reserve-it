<?php

declare(strict_types=1);

$scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
$scriptDirectory = $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/');
$hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
$hostOnly = preg_replace('/:\d+$/', '', $hostHeader) ?: '';
$isKnownLocalHost = $hostHeader !== '' && preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $hostHeader)
    && (strtolower($hostOnly) === 'localhost' || str_ends_with(strtolower($hostOnly), '.local') || filter_var(trim($hostOnly, '[]'), FILTER_VALIDATE_IP));
$requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$inferredLocalUrl = $isKnownLocalHost ? $requestScheme . '://' . $hostHeader . $scriptDirectory : 'http://127.0.0.1:8001';

/* APP_URL can override the safe localhost/LAN URL detection, especially behind a proxy. */
return [
    'app_name' => getenv('APP_NAME') ?: 'Reserve-it',
    'app_url' => rtrim(getenv('APP_URL') ?: $inferredLocalUrl, '/'),
    'app_path' => $scriptDirectory,
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Manila',
    'session_name' => 'courtly_raw_session',
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_DATABASE') ?: 'courtly_raw_php',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        // `log` safely stores messages locally; use `mail` only when PHP mail transport is configured.
        'mode' => getenv('MAIL_MODE') ?: 'log',
        'from' => getenv('MAIL_FROM') ?: 'no-reply@example.test',
    ],
    'uploads' => [
        'court_photos' => __DIR__ . '/storage/court-photos',
        'max_bytes' => 5 * 1024 * 1024,
    ],
];
