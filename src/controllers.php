<?php

declare(strict_types=1);

function dispatch_request(): void
{
    $route = (string) ($_GET['r'] ?? 'home');
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($route === 'photo') { serve_photo((string) ($_GET['file'] ?? '')); return; }
    if ($route === 'api.availability') { availability_api(); return; }
    if ($method === 'POST') verify_csrf();

    try {
        switch ($route) {
            case 'asset': asset_url((string) ($_GET['file'] ?? '')); return;
            case 'home': render('home', ['courts' => db()->query('SELECT * FROM courts WHERE is_active=1 ORDER BY sport,name')->fetchAll()]); return;
            case 'login':
                if ($method === 'POST') {
                    $email = mb_strtolower(request_string('email')); $limitKey = 'login|' . $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                    if (!rate_limit_allow($limitKey, 8, 300)) { render('auth/login', ['errors' => ['Too many sign-in attempts. Wait a few minutes and try again.'], 'email' => $email]); return; }
                    if (Auth::login($email, (string) ($_POST['password'] ?? ''))) { rate_limit_clear($limitKey); flash('success', 'Welcome back.'); redirect('dashboard'); }
                    render('auth/login', ['errors' => ['Email or password is incorrect, or this account is disabled.'], 'email' => $email]); return;
                }
                render('auth/login'); return;
            case 'register':
                if ($method === 'POST') {
                    $registerKey = 'register|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                    if (!rate_limit_allow($registerKey, 5, 600)) { render('auth/register', ['errors' => ['Too many accounts have been created from this network. Try again later.']]); return; }
                    $password = (string) ($_POST['password'] ?? '');
                    $errors = $password !== (string) ($_POST['password_confirmation'] ?? '') ? ['The password confirmation does not match.'] : Auth::register(request_string('name', 100), request_string('email'), $password);
                    if (!$errors) { flash('success', 'Your account is ready.'); redirect('dashboard'); }
                    render('auth/register', ['errors' => $errors, 'name' => request_string('name', 100), 'email' => request_string('email')]); return;
                }
                render('auth/register'); return;
            case 'logout': require_login(); Auth::logout(); session_start(); session_regenerate_id(true); flash('success', 'You have signed out.'); redirect('home');
            case 'forgot': render('auth/forgot'); return;
            case 'password.request':
                if (!rate_limit_allow('password-reset|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 4, 3600)) { flash('error', 'Too many reset requests. Try again later.'); redirect('forgot'); }
                Auth::requestPasswordReset(mb_strtolower(request_string('email')));
                flash('success', 'If an active account uses that email, a reset link has been sent. Check your configured mailbox or local mail outbox.'); redirect('login');
            case 'reset':
                render('auth/reset', ['email' => (string) ($_GET['email'] ?? ''), 'token' => (string) ($_GET['token'] ?? '')]); return;
            case 'password.reset':
                $email = mb_strtolower(request_string('email')); $token = (string) ($_POST['token'] ?? '');
                $password = (string) ($_POST['password'] ?? '');
                if ($password !== (string) ($_POST['password_confirmation'] ?? '')) { render('auth/reset', ['email' => $email, 'token' => $token, 'errors' => ['The password confirmation does not match.']]); return; }
                if (!Auth::resetPassword($email, $token, $password)) { render('auth/reset', ['email' => $email, 'token' => $token, 'errors' => ['That reset link is invalid or expired. Passwords need at least 10 characters.']]); return; }
                flash('success', 'Password updated. You can now sign in.'); redirect('login');
            case 'dashboard':
                $user = require_login();
                $sql = 'SELECT b.*,c.name AS court_name,c.sport FROM bookings b JOIN courts c ON c.id=b.court_id WHERE b.user_id=?'; $values = [$user['id']];
                if (isset($_GET['status']) && in_array($_GET['status'], ['pending','confirmed','rejected','cancelled','completed'], true)) { $sql .= ' AND b.status=?'; $values[] = $_GET['status']; }
                $sql .= ' ORDER BY b.start_at DESC LIMIT 100'; $query = db()->prepare($sql); $query->execute($values);
                render('dashboard', ['user' => $user, 'bookings' => $query->fetchAll()]); return;
            case 'booking.create':
                $user = require_login();
                try { $booking = BookingService::create((int) $user['id'], (int) ($_POST['court_id'] ?? 0), (string) ($_POST['start_at'] ?? ''), (string) ($_POST['end_at'] ?? '')); }
                catch (DomainException $exception) { flash('error', $exception->getMessage()); redirect('home'); }
                Mailer::booking($booking, 'submitted'); flash('success', 'Reservation submitted as pending. Reference: ' . $booking['reference_code']); redirect('dashboard');
            case 'booking.cancel':
                $user = require_login(); $booking = BookingService::cancel((int) ($_POST['booking_id'] ?? 0), $user);
                Mailer::booking($booking, 'cancelled'); flash('success', 'Reservation cancelled. Its time is available again.'); redirect('dashboard');
            case 'admin':
                require_admin();
                $metrics = [
                    'today' => (int) db()->query('SELECT COUNT(*) FROM bookings WHERE DATE(start_at)=CURDATE()')->fetchColumn(),
                    'pending' => (int) db()->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
                    'confirmed' => (int) db()->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetchColumn(),
                    'revenue' => (float) db()->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status IN ('confirmed','completed')")->fetchColumn(),
                ];
                $filters = []; $values = [];
                if (!empty($_GET['status']) && in_array($_GET['status'], ['pending','confirmed','rejected','cancelled','completed'], true)) { $filters[] = 'b.status=?'; $values[] = $_GET['status']; }
                if (!empty($_GET['date']) && preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['date'])) { $filters[] = 'DATE(b.start_at)=?'; $values[] = $_GET['date']; }
                if (!empty($_GET['search'])) { $filters[] = '(b.reference_code LIKE ? OR u.email LIKE ? OR u.name LIKE ?)'; $needle = '%' . mb_substr($_GET['search'], 0, 80) . '%'; array_push($values, $needle, $needle, $needle); }
                $sql = 'SELECT b.*,c.name AS court_name,c.sport,u.name AS customer_name,u.email AS customer_email FROM bookings b JOIN courts c ON c.id=b.court_id JOIN users u ON u.id=b.user_id' . ($filters ? ' WHERE ' . implode(' AND ', $filters) : '') . ' ORDER BY b.start_at DESC LIMIT 100';
                $query = db()->prepare($sql); $query->execute($values); render('admin/dashboard', compact('metrics') + ['bookings' => $query->fetchAll()]); return;
            case 'admin.booking.status':
                require_admin();
                $booking = BookingService::setStatus((int) ($_POST['booking_id'] ?? 0), request_string('status', 20), request_string('admin_note', 1000));
                Mailer::booking($booking, $booking['status']); flash('success', 'Reservation updated.'); redirect('admin');
            case 'admin.courts':
                require_admin(); render('admin/courts', ['courts' => db()->query('SELECT * FROM courts ORDER BY is_active DESC,sport,name')->fetchAll()]); return;
            case 'admin.court.form':
                require_admin(); $court = null;
                if (!empty($_GET['id'])) { $q = db()->prepare('SELECT * FROM courts WHERE id=?'); $q->execute([(int) $_GET['id']]); $court = $q->fetch(); if (!$court) throw new DomainException('Court not found.'); }
                render('admin/court-form', ['court' => $court]); return;
            case 'admin.court.save': save_court(); flash('success', 'Court saved.'); redirect('admin.courts');
            case 'admin.court.deactivate': require_admin(); db()->prepare('UPDATE courts SET is_active=0,updated_at=NOW() WHERE id=?')->execute([(int) ($_POST['court_id'] ?? 0)]); flash('success', 'Court deactivated. Booking history is retained.'); redirect('admin.courts');
            case 'admin.maintenance.add': add_maintenance(); flash('success', 'Maintenance block added.'); redirect('admin.courts');
            case 'admin.maintenance.delete': require_admin(); db()->prepare('DELETE FROM maintenance_blocks WHERE id=?')->execute([(int) ($_POST['block_id'] ?? 0)]); flash('success', 'Maintenance block removed.'); redirect('admin.courts');
            case 'admin.users':
                require_admin(); $search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 80); $query = db()->prepare('SELECT id,name,email,role,is_active,created_at FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 200'); $query->execute(['%' . $search . '%', '%' . $search . '%']);
                render('admin/users', ['users' => $query->fetchAll(), 'search' => $search]); return;
            case 'admin.user.update':
                $admin = require_admin(); $id = (int) ($_POST['user_id'] ?? 0); $role = request_string('role', 20); $active = isset($_POST['is_active']) ? 1 : 0;
                if (!in_array($role, ['customer','admin'], true)) throw new DomainException('Choose a valid role.');
                if ($id === (int) $admin['id'] && (!$active || $role !== 'admin')) throw new DomainException('You cannot remove your own admin access.');
                $target = db()->prepare('SELECT role FROM users WHERE id=?'); $target->execute([$id]); $targetRole = $target->fetchColumn();
                if ($targetRole === 'admin' && (!$active || $role !== 'admin') && (int) db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn() <= 1) throw new DomainException('Promote another active administrator before changing this account.');
                db()->prepare('UPDATE users SET role=?,is_active=?,updated_at=NOW() WHERE id=?')->execute([$role, $active, $id]); flash('success', 'User account updated.'); redirect('admin.users');
            default: http_response_code(404); render('errors/404'); return;
        }
    } catch (DomainException $exception) {
        if (str_starts_with($route, 'api.')) json_response(['success' => false, 'message' => $exception->getMessage()], 422);
        flash('error', $exception->getMessage()); redirect(str_starts_with($route, 'admin') ? 'admin' : 'home');
    } catch (Throwable $exception) {
        log_message(get_class($exception) . ': ' . $exception->getMessage());
        http_response_code(500);
        render('errors/500');
    }
}

function availability_api(): never
{
    $courtId = filter_input(INPUT_GET, 'court_id', FILTER_VALIDATE_INT) ?: 0;
    $date = (string) ($_GET['date'] ?? '');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(config('timezone')));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$courtId || !$parsed || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $parsed->format('Y-m-d') !== $date || $date < date('Y-m-d')) json_response(['success' => false, 'message' => 'Choose a valid current or future date.'], 422);
    $result = BookingService::availability($courtId, $date);
    if (!$result['court']) json_response(['success' => false, 'message' => 'Court not found.'], 404);
    json_response(['success' => true, 'data' => $result]);
}

function save_court(): int
{
    require_admin();
    $id = (int) ($_POST['court_id'] ?? 0);
    $name = request_string('name', 120); $sport = request_string('sport', 60); $description = request_string('description', 2000);
    $rate = filter_var($_POST['hourly_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    $opens = (string) ($_POST['opens_at'] ?? ''); $closes = (string) ($_POST['closes_at'] ?? '');
    $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
    if (!$name || !$sport || $rate === false || $rate < 0 || !preg_match('/^\d\d:\d\d$/', $opens) || !preg_match('/^\d\d:\d\d$/', $closes) || $closes <= $opens) throw new DomainException('Enter a name, sport, valid rate, and operating hours.');
    if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) throw new DomainException('Enter a valid image URL.');
    $photoPath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['image']; $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > config('uploads')['max_bytes'] || !isset($extensions[$mime])) throw new DomainException('Upload a JPEG, PNG, or WebP image no larger than 5 MB.');
        $photoPath = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], config('uploads')['court_photos'] . '/' . $photoPath)) throw new RuntimeException('Could not save the court photo.');
    }
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($id) {
        $query = db()->prepare('UPDATE courts SET name=?,sport=?,description=?,hourly_rate=?,image_url=?,photo_path=COALESCE(?,photo_path),opens_at=?,closes_at=?,is_active=?,updated_at=NOW() WHERE id=?');
        $query->execute([$name, $sport, $description ?: null, $rate, $imageUrl ?: null, $photoPath, $opens . ':00', $closes . ':00', $active, $id]);
    } else {
        $query = db()->prepare('INSERT INTO courts (name,sport,description,hourly_rate,image_url,photo_path,opens_at,closes_at,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
        $query->execute([$name, $sport, $description ?: null, $rate, $imageUrl ?: null, $photoPath, $opens . ':00', $closes . ':00', $active]);
    }
    return $id ?: (int) db()->lastInsertId();
}

function add_maintenance(): int
{
    require_admin(); $courtId = (int) ($_POST['court_id'] ?? 0); $start = request_string('start_at', 30); $end = request_string('end_at', 30); $reason = request_string('reason', 180);
    $startAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $start, new DateTimeZone(config('timezone')));
    $endAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $end, new DateTimeZone(config('timezone')));
    if (!$startAt || !$endAt || $startAt <= new DateTimeImmutable('now', new DateTimeZone(config('timezone'))) || $endAt <= $startAt || !$reason) throw new DomainException('Choose a future maintenance period and add a reason.');
    $pdo = db(); $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM courts WHERE id=? FOR UPDATE'); $lock->execute([$courtId]);
        if (!$lock->fetchColumn()) throw new DomainException('Court not found.');
        $check = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court_id=? AND status IN ('pending','confirmed') AND start_at < ? AND end_at > ?");
        $check->execute([$courtId, $endAt->format('Y-m-d H:i:s'), $startAt->format('Y-m-d H:i:s')]);
        if ((int) $check->fetchColumn()) throw new DomainException('This maintenance block overlaps a reservation.');
        $pdo->prepare('INSERT INTO maintenance_blocks (court_id,start_at,end_at,reason,created_at) VALUES (?,?,?,?,NOW())')->execute([$courtId, $startAt->format('Y-m-d H:i:s'), $endAt->format('Y-m-d H:i:s'), $reason]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

function serve_photo(string $filename): never
{
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) { http_response_code(404); exit; }
    $query = db()->prepare('SELECT photo_path FROM courts WHERE photo_path=? AND is_active=1 LIMIT 1'); $query->execute([$filename]);
    if (!$query->fetchColumn()) { http_response_code(404); exit; }
    $path = config('uploads')['court_photos'] . '/' . $filename;
    if (!is_file($path)) { http_response_code(404); exit; }
    $finfo = new finfo(FILEINFO_MIME_TYPE); header('Content-Type: ' . $finfo->file($path)); header('X-Content-Type-Options: nosniff'); header('Cache-Control: public, max-age=3600');
    readfile($path); exit;
}
