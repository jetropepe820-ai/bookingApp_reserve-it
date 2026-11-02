<?php

declare(strict_types=1);

final class Auth
{
    public static function register(string $name, string $email, string $password): array
    {
        $errors = [];
        if (mb_strlen($name) < 2) $errors[] = 'Enter your name (at least 2 characters).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (strlen($password) < 10) $errors[] = 'Choose a password with at least 10 characters.';
        if ($errors) return $errors;

        $statement = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        if ($statement->fetch()) return ['An account with that email already exists.'];
        $insert = db()->prepare('INSERT INTO users (name, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, \'customer\', 1, NOW())');
        $insert->execute([$name, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        return [];
    }

    public static function login(string $email, string $password): bool
    {
        $statement = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
        $statement->execute([mb_strtolower($email)]);
        $user = $statement->fetch();
        if (!$user || !(int) $user['is_active'] || !password_verify($password, $user['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
        }
        session_destroy();
    }

    public static function requestPasswordReset(string $email): void
    {
        $statement = db()->prepare('SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $statement->execute([mb_strtolower($email)]);
        $user = $statement->fetch();
        if (!$user) return; // Do not disclose whether an email is registered.
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        db()->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$user['email']]);
        db()->prepare('INSERT INTO password_resets (email, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())')->execute([$user['email'], $hash]);
        $resetUrl = absolute_url('reset', ['email' => $user['email'], 'token' => $token]);
        Mailer::send($user['email'], 'Reset your Reserve-it password', '<p>Hi ' . h($user['name']) . ',</p><p>Use this link within one hour to choose a new password:</p><p><a href="' . h($resetUrl) . '">Reset password</a></p><p>If you did not request this, ignore this email.</p>');
    }

    public static function resetPassword(string $email, string $token, string $password): bool
    {
        if (strlen($password) < 10 || strlen($token) !== 64) return false;
        $statement = db()->prepare('SELECT token_hash FROM password_resets WHERE email = ? AND expires_at > NOW() LIMIT 1');
        $statement->execute([mb_strtolower($email)]);
        $reset = $statement->fetch();
        if (!$reset || !hash_equals($reset['token_hash'], hash('sha256', $token))) return false;
        db()->beginTransaction();
        try {
            db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?')->execute([password_hash($password, PASSWORD_DEFAULT), mb_strtolower($email)]);
            db()->prepare('DELETE FROM password_resets WHERE email = ?')->execute([mb_strtolower($email)]);
            db()->commit();
        } catch (Throwable $exception) { db()->rollBack(); throw $exception; }
        return true;
    }
}
