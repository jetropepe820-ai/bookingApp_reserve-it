<?php

declare(strict_types=1);

function api_dispatch(string $route, string $method): never
{
    header('Cache-Control: no-store');
    try {
        $body = $_POST;
        if (str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            if (!is_array($decoded)) json_response(['success' => false, 'message' => 'Send a valid JSON object.'], 400);
            $body = $decoded;
        }
        $user = null;
        if (!in_array($route, ['api.v1.auth.login', 'api.v1.auth.register', 'api.v1.courts', 'api.v1.court.availability'], true)) {
            $user = api_authenticated_user();
            if (!$user) json_response(['success' => false, 'message' => 'A valid bearer token is required.'], 401);
            $GLOBALS['request_api_user'] = $user;
        }

        if ($route === 'api.v1.auth.register' && $method === 'POST') {
            if (!rate_limit_allow('api-register|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 600)) json_response(['success'=>false,'message'=>'Too many registration attempts. Try again later.'],429);
            $name = trim((string) ($body['name'] ?? '')); $email = mb_strtolower(trim((string) ($body['email'] ?? ''))); $password = (string) ($body['password'] ?? '');
            if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || $password !== (string) ($body['password_confirmation'] ?? '')) json_response(['success' => false, 'message' => 'Provide a name, valid email, and matching password of at least 10 characters.'], 422);
            $exists = db()->prepare('SELECT id FROM users WHERE email=?'); $exists->execute([$email]);
            if ($exists->fetch()) json_response(['success' => false, 'message' => 'That email is already registered.'], 422);
            $insert = db()->prepare("INSERT INTO users (name,email,password_hash,role,is_active,created_at) VALUES (?,?,?,'customer',1,NOW())"); $insert->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            $newUser = ['id'=>(int)db()->lastInsertId(),'name'=>$name,'email'=>$email,'role'=>'customer'];
            json_response(['success'=>true,'data'=>['user'=>$newUser,'token'=>api_issue_token((int)$newUser['id'])]],201);
        }
        if ($route === 'api.v1.auth.login' && $method === 'POST') {
            $limitKey='api-login|'.mb_strtolower(trim((string)($body['email']??''))).'|'.($_SERVER['REMOTE_ADDR']??'unknown');
            if (!rate_limit_allow($limitKey,8,300)) json_response(['success'=>false,'message'=>'Too many sign-in attempts. Try again later.'],429);
            $email = mb_strtolower(trim((string) ($body['email'] ?? ''))); $query = db()->prepare('SELECT id,name,email,role,password_hash,is_active FROM users WHERE email=?'); $query->execute([$email]); $found = $query->fetch();
            if (!$found || !(int)$found['is_active'] || !password_verify((string)($body['password']??''),$found['password_hash'])) json_response(['success'=>false,'message'=>'The supplied credentials are incorrect.'],422);
            rate_limit_clear($limitKey);
            $publicUser = array_intersect_key($found, array_flip(['id','name','email','role']));
            json_response(['success'=>true,'data'=>['user'=>$publicUser,'token'=>api_issue_token((int)$found['id'])]]);
        }
        if ($route === 'api.v1.auth.logout' && $method === 'POST') {
            $token = api_bearer_token(); db()->prepare('DELETE FROM api_tokens WHERE token_hash=?')->execute([hash('sha256',$token)]); json_response(['success'=>true]);
        }
        if ($route === 'api.v1.courts' && $method === 'GET') {
            $query = db()->prepare('SELECT id,name,sport,description,hourly_rate,image_url,photo_path,opens_at,closes_at FROM courts WHERE is_active=1 AND (? = \'\' OR sport=?) AND (? = \'\' OR name LIKE ? OR sport LIKE ?) ORDER BY sport,name LIMIT 100');
            $sport = trim((string)($_GET['sport']??'')); $search=trim((string)($_GET['search']??'')); $like='%'.mb_substr($search,0,80).'%'; $query->execute([$sport,$sport,$search,$like,$like]);
            json_response(['success'=>true,'data'=>$query->fetchAll()]);
        }
        if ($route === 'api.v1.court.availability' && $method === 'GET') {
            $courtId=(int)($_GET['court_id']??0); $date=(string)($_GET['date']??''); $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone(config('timezone')));
            if(!$courtId||!$parsed||$parsed->format('Y-m-d')!==$date||$date<date('Y-m-d')) json_response(['success'=>false,'message'=>'Choose a valid current or future date.'],422);
            $availability=BookingService::availability($courtId,$date); if(!$availability['court']) json_response(['success'=>false,'message'=>'Court not found.'],404);
            json_response(['success'=>true,'data'=>$availability]);
        }
        if ($route === 'api.v1.bookings' && $method === 'GET') {
            $sql='SELECT b.*,c.name AS court_name,c.sport FROM bookings b JOIN courts c ON c.id=b.court_id WHERE 1=1'; $values=[];
            if($user['role']!=='admin') {$sql.=' AND b.user_id=?';$values[]=$user['id'];}
            if(!empty($_GET['status'])&&in_array($_GET['status'],['pending','confirmed','rejected','cancelled','completed'],true)) {$sql.=' AND b.status=?';$values[]=$_GET['status'];}
            if(!empty($_GET['date'])&&preg_match('/^\d{4}-\d\d-\d\d$/',$_GET['date'])) {$sql.=' AND DATE(b.start_at)=?';$values[]=$_GET['date'];}
            $sql.=' ORDER BY b.start_at DESC LIMIT 100';$query=db()->prepare($sql);$query->execute($values);json_response(['success'=>true,'data'=>$query->fetchAll()]);
        }
        if ($route === 'api.v1.bookings' && $method === 'POST') {
            $booking=BookingService::create((int)$user['id'],(int)($body['court_id']??0),(string)($body['start_at']??''),(string)($body['end_at']??''));Mailer::booking($booking,'submitted');json_response(['success'=>true,'message'=>'Booking created successfully.','data'=>$booking],201);
        }
        if ($route === 'api.v1.booking.item' && $method === 'GET') {
            $booking=BookingService::find((int)($_GET['booking_id']??0));if($user['role']!=='admin'&&(int)$booking['user_id']!==(int)$user['id'])json_response(['success'=>false,'message'=>'Forbidden.'],403);json_response(['success'=>true,'data'=>$booking]);
        }
        if ($route === 'api.v1.booking.cancel' && $method === 'POST') {
            $booking=BookingService::cancel((int)($_GET['booking_id']??0),$user);Mailer::booking($booking,'cancelled');json_response(['success'=>true,'message'=>'Booking cancelled.','data'=>$booking]);
        }
        if ($route === 'api.v1.booking.item' && $method === 'PATCH') {
            if($user['role']!=='admin')json_response(['success'=>false,'message'=>'Administrator access required.'],403);$booking=BookingService::setStatus((int)($_GET['booking_id']??0),(string)($body['status']??''),(string)($body['admin_note']??''));Mailer::booking($booking,$booking['status']);json_response(['success'=>true,'data'=>$booking]);
        }
        if ($route === 'api.v1.admin.courts' && $method === 'GET') {
            if($user['role']!=='admin')json_response(['success'=>false,'message'=>'Administrator access required.'],403);json_response(['success'=>true,'data'=>db()->query('SELECT * FROM courts ORDER BY name')->fetchAll()]);
        }
        if ($route === 'api.v1.admin.courts' && $method === 'POST') {
            if($user['role']!=='admin')json_response(['success'=>false,'message'=>'Administrator access required.'],403);$_POST=$body;$id=save_court();$q=db()->prepare('SELECT * FROM courts WHERE id=?');$q->execute([$id]);json_response(['success'=>true,'data'=>$q->fetch()],201);
        }
        if ($route === 'api.v1.admin.court.update' && in_array($method,['POST','PUT'],true)) {
            if($user['role']!=='admin')json_response(['success'=>false,'message'=>'Administrator access required.'],403);$_POST=$body;$_POST['court_id']=(int)($_GET['court_id']??0);$id=save_court();$q=db()->prepare('SELECT * FROM courts WHERE id=?');$q->execute([$id]);json_response(['success'=>true,'data'=>$q->fetch()]);
        }
        if ($route === 'api.v1.admin.court.deactivate' && $method === 'DELETE') {
            if($user['role']!=='admin')json_response(['success'=>false,'message'=>'Administrator access required.'],403);db()->prepare('UPDATE courts SET is_active=0,updated_at=NOW() WHERE id=?')->execute([(int)($_GET['court_id']??0)]);json_response(['success'=>true,'message'=>'Court deactivated.']);
        }
        if ($route === 'api.v1.admin.maintenance.add' && $method === 'POST') {
            if($user['role']!=='admin')json_response(['success'=>false,'message'=>'Administrator access required.'],403);$_POST=$body;$id=add_maintenance();$q=db()->prepare('SELECT * FROM maintenance_blocks WHERE id=?');$q->execute([$id]);json_response(['success'=>true,'data'=>$q->fetch()],201);
        }
        if ($route === 'api.v1.admin.maintenance.delete' && $method === 'DELETE') {
            if($user['role']!=='admin')json_response(['success'=>false,'message'=>'Administrator access required.'],403);db()->prepare('DELETE FROM maintenance_blocks WHERE id=?')->execute([(int)($_GET['block_id']??0)]);json_response(['success'=>true,'message'=>'Maintenance block removed.']);
        }
        json_response(['success'=>false,'message'=>'API endpoint not found.'],404);
    } catch (DomainException $exception) {
        $status = str_contains(strtolower($exception->getMessage()), 'already booked') || str_contains(strtolower($exception->getMessage()), 'overlaps a reservation') ? 409 : 422;
        json_response(['success'=>false,'message'=>$exception->getMessage()],$status);
    } catch (Throwable $exception) {
        log_message('API ' . $route . ': ' . get_class($exception) . ': ' . $exception->getMessage());
        json_response(['success'=>false,'message'=>'The server could not complete the request.'],500);
    }
}

function api_bearer_token(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim((string)$authorization), $matches) ? $matches[1] : '';
}

function api_authenticated_user(): ?array
{
    $token = api_bearer_token(); if (!$token) return null;
    $query=db()->prepare('SELECT u.id,u.name,u.email,u.role,u.is_active FROM api_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.expires_at>NOW() LIMIT 1');$query->execute([hash('sha256',$token)]);$user=$query->fetch();
    return $user && (int)$user['is_active'] ? $user : null;
}

function api_issue_token(int $userId): string
{
    $token=bin2hex(random_bytes(32));db()->prepare("INSERT INTO api_tokens (user_id,token_hash,label,expires_at,created_at) VALUES (?,?,'booking-api',DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())")->execute([$userId,hash('sha256',$token)]);return $token;
}
