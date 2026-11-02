<?php

declare(strict_types=1);

final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        $from = (string) config('mail')['from'];
        $plain = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $message = "To: {$to}\nSubject: {$subject}\nFrom: {$from}\n\n{$plain}\n\nHTML:\n{$html}\n\n" . str_repeat('-', 72) . "\n";
        if (config('mail')['mode'] !== 'mail') {
            file_put_contents(dirname(__DIR__) . '/storage/mail/outbox.log', '[' . date(DATE_ATOM) . "]\n" . $message, FILE_APPEND | LOCK_EX);
            return true;
        }

        $boundary = 'reserveit_' . bin2hex(random_bytes(12));
        $headers = "From: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n";
        $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n--{$boundary}--";
        $sent = mail($to, $subject, $body, $headers);
        if (!$sent) log_message('mail() returned false for ' . $to . ' (' . $subject . ')');
        return $sent;
    }

    public static function booking(array $booking, string $event): void
    {
        try {
            $when = format_datetime($booking['start_at']) . ' – ' . format_datetime($booking['end_at'], 'g:i A');
            $subject = 'Reservation ' . $event . ' — ' . $booking['reference_code'];
            $html = '<p>Hi ' . h($booking['customer_name']) . ',</p><p>Your reservation has been <strong>' . h($event) . '</strong>.</p>'
                . '<ul><li>Court: ' . h($booking['court_name']) . '</li><li>When: ' . h($when) . '</li><li>Reference: ' . h($booking['reference_code']) . '</li>'
                . '<li>Status: ' . h(ucfirst($booking['status'])) . '</li><li>Total: ₱' . number_format((float) $booking['total_price'], 2) . '</li></ul>'
                . '<p>Sign in to your Reserve-it account to view your reservations.</p>';
            self::send($booking['customer_email'], $subject, $html);
        } catch (Throwable $exception) {
            log_message('booking notification failed for ' . ($booking['reference_code'] ?? 'unknown') . ': ' . $exception->getMessage());
        }
    }
}
