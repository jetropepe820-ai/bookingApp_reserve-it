<?php

declare(strict_types=1);

final class BookingService
{
    public static function availability(int $courtId, string $date): array
    {
        $courtQuery = db()->prepare('SELECT id, name, opens_at, closes_at, hourly_rate FROM courts WHERE id = ? AND is_active = 1 LIMIT 1');
        $courtQuery->execute([$courtId]);
        $court = $courtQuery->fetch();
        if (!$court) return ['court' => null, 'bookings' => [], 'maintenance' => []];
        $from = $date . ' 00:00:00'; $to = $date . ' 23:59:59';
        $bookingsQuery = db()->prepare("SELECT start_at, end_at FROM bookings WHERE court_id = ? AND status IN ('pending','confirmed') AND start_at <= ? AND end_at > ? ORDER BY start_at");
        $bookingsQuery->execute([$courtId, $to, $from]);
        $maintenanceQuery = db()->prepare('SELECT start_at, end_at, reason FROM maintenance_blocks WHERE court_id = ? AND start_at <= ? AND end_at > ? ORDER BY start_at');
        $maintenanceQuery->execute([$courtId, $to, $from]);
        return ['court' => $court, 'bookings' => $bookingsQuery->fetchAll(), 'maintenance' => $maintenanceQuery->fetchAll()];
    }

    public static function create(int $userId, int $courtId, string $startValue, string $endValue): array
    {
        $timezone = new DateTimeZone(config('timezone'));
        try { $start = new DateTimeImmutable($startValue, $timezone); $end = new DateTimeImmutable($endValue, $timezone); }
        catch (Throwable) { throw new DomainException('Choose a valid date and time.'); }
        if ($start->format('Y-m-d H:i:s') !== $startValue || $end->format('Y-m-d H:i:s') !== $endValue) throw new DomainException('Choose a valid date and time.');
        if ($start < new DateTimeImmutable('now', $timezone)) throw new DomainException('Bookings must start in the future.');
        if ($start->format('Y-m-d') !== $end->format('Y-m-d') || $end <= $start) throw new DomainException('Choose a start and end time on the same day.');
        $minutes = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
        if ($minutes < 60 || $minutes % 60 !== 0) throw new DomainException('Reservations must be at least one hour in whole-hour increments.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $courtQuery = $pdo->prepare('SELECT * FROM courts WHERE id = ? AND is_active = 1 FOR UPDATE');
            $courtQuery->execute([$courtId]);
            $court = $courtQuery->fetch();
            if (!$court) throw new DomainException('That court is not available for booking.');
            if ($start->format('H:i:s') < $court['opens_at'] || $end->format('H:i:s') > $court['closes_at']) throw new DomainException('Choose a time within court operating hours.');

            $conflicts = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court_id = ? AND status IN ('pending','confirmed') AND start_at < ? AND end_at > ?");
            $conflicts->execute([$courtId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
            $blocks = $pdo->prepare('SELECT COUNT(*) FROM maintenance_blocks WHERE court_id = ? AND start_at < ? AND end_at > ?');
            $blocks->execute([$courtId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
            if ((int) $conflicts->fetchColumn() > 0 || (int) $blocks->fetchColumn() > 0) throw new DomainException('That time is already booked or blocked. Choose another slot.');

            $hours = $minutes / 60;
            $reference = strtoupper(bin2hex(random_bytes(4)));
            $insert = $pdo->prepare("INSERT INTO bookings (reference_code, user_id, court_id, start_at, end_at, hourly_rate, total_price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $insert->execute([$reference, $userId, $courtId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $court['hourly_rate'], number_format($hours * (float) $court['hourly_rate'], 2, '.', '')]);
            $bookingId = (int) $pdo->lastInsertId();
            $pdo->commit();
            return self::find($bookingId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public static function find(int $id): array
    {
        $query = db()->prepare('SELECT b.*, c.name AS court_name, c.sport, u.name AS customer_name, u.email AS customer_email FROM bookings b JOIN courts c ON c.id=b.court_id JOIN users u ON u.id=b.user_id WHERE b.id=?');
        $query->execute([$id]);
        return $query->fetch() ?: throw new RuntimeException('Reservation not found.');
    }

    public static function cancel(int $id, array $user): array
    {
        $pdo = db(); $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE'); $query->execute([$id]); $booking = $query->fetch();
            if (!$booking) throw new DomainException('Reservation not found.');
            if ($user['role'] !== 'admin' && (int) $booking['user_id'] !== (int) $user['id']) throw new DomainException('You cannot cancel someone else’s reservation.');
            if (!in_array($booking['status'], ['pending', 'confirmed'], true) || new DateTimeImmutable($booking['start_at']) <= new DateTimeImmutable('now', new DateTimeZone(config('timezone')))) throw new DomainException('This reservation can no longer be cancelled.');
            $pdo->prepare("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
            $pdo->commit();
            return self::find($id);
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
    }

    public static function setStatus(int $id, string $status, string $note = ''): array
    {
        $allowed = ['pending' => ['confirmed', 'rejected', 'cancelled'], 'confirmed' => ['cancelled', 'completed']];
        $pdo = db(); $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT status FROM bookings WHERE id=? FOR UPDATE'); $query->execute([$id]); $current = $query->fetchColumn();
            if ($current === false) throw new DomainException('Reservation not found.');
            if (!in_array($status, $allowed[$current] ?? [], true)) throw new DomainException('That reservation status change is not allowed.');
            $pdo->prepare('UPDATE bookings SET status=?, admin_note=?, updated_at=NOW() WHERE id=?')->execute([$status, mb_substr($note, 0, 1000), $id]);
            $pdo->commit();
            return self::find($id);
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
    }
}
