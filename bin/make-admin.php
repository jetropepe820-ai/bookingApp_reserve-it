<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/helpers.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$email = mb_strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Usage: php bin/make-admin.php user@example.com\n"); exit(2); }
$statement = Database::connection()->prepare("UPDATE users SET role='admin', updated_at=NOW() WHERE email=?");
$statement->execute([$email]);
if ($statement->rowCount() < 1) { fwrite(STDERR, "No account found, or it already has admin access. Register first.\n"); exit(1); }
fwrite(STDOUT, "Administrator role granted to {$email}.\n");
