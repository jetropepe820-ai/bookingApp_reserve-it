<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/helpers.php';
require dirname(__DIR__) . '/src/Auth.php';
require dirname(__DIR__) . '/src/Mailer.php';
require dirname(__DIR__) . '/src/BookingService.php';
require dirname(__DIR__) . '/src/controllers.php';
require dirname(__DIR__) . '/src/api.php';

date_default_timezone_set(config('timezone'));
session_name(config('session_name'));
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
session_start();

if (str_starts_with((string) ($_GET['r'] ?? ''), 'api.v1.')) { api_dispatch((string) $_GET['r'], strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET')); exit; }

try { dispatch_request(); }
catch (Throwable $exception) {
    log_message(get_class($exception) . ': ' . $exception->getMessage());
    http_response_code(500);
    render('errors/500');
}
