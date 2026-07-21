<?php

declare(strict_types=1);

namespace App\Services;

use Core\Config\Config;
use Core\Database\QueryBuilder;

/**
 * Pluggable outbound email service.
 *
 * The default 'log' mailer records messages to the communication_logs table and
 * the application log without sending. To add a real provider (SMTP, SES, etc.)
 * implement a driver and switch on config('mail.mailer'). This keeps the
 * notification layer provider-agnostic as required.
 */
final class MailService
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $mailer = (string) Config::get('mail.mailer', 'log');

        try {
            $status = match ($mailer) {
                'smtp'  => self::sendSmtp($to, $subject, $body),
                default => self::sendLog($to, $subject, $body),
            };
        } catch (\Throwable $e) {
            self::record($to, $subject, $body, 'failed', $e->getMessage());
            logger()->error('Mail send failed: ' . $e->getMessage());
            return false;
        }

        self::record($to, $subject, $body, $status ? 'sent' : 'failed');
        return $status;
    }

    private static function sendLog(string $to, string $subject, string $body): bool
    {
        logger()->info("[MAIL:log] To: {$to} | Subject: {$subject}", ['body' => $body]);
        return true;
    }

    /**
     * Minimal SMTP send via PHP's mail() as a portable fallback. For production,
     * integrate a full SMTP client or transactional API here.
     */
    private static function sendSmtp(string $to, string $subject, string $body): bool
    {
        $from = (string) Config::get('mail.from.address', 'no-reply@schoolerp.test');
        $name = (string) Config::get('mail.from.name', 'School ERP');
        $headers = "From: {$name} <{$from}>\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        return @mail($to, $subject, $body, $headers);
    }

    private static function record(string $to, string $subject, string $body, string $status, ?string $error = null): void
    {
        try {
            QueryBuilder::table('communication_logs')->insert([
                'channel'    => 'email',
                'recipient'  => $to,
                'subject'    => $subject,
                'body'       => $body,
                'status'     => $status,
                'error'      => $error,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Table may not exist yet (pre-migration); ignore.
        }
    }
}
