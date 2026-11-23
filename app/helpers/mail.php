<?php
/**
 * Heramba Fashion — Mail Helper
 * =================================
 * In local mode (APP_ENV=local): Logs emails to /logs/mail.log instead of sending.
 * In production mode (APP_ENV=production): Uses standard PHP mail() or configured transport.
 */

declare(strict_types=1);

if (!function_exists('send_email')) {
    /**
     * Send an email or log it to file in development.
     *
     * @param string $to Recipient email address
     * @param string $subject Subject line
     * @param string $body Plain text or HTML message body
     * @param array $headers Additional headers [Key => Value]
     * @return bool True if sent or successfully logged
     */
    function send_email(string $to, string $subject, string $body, array $headers = []): bool
    {
        $cleanTo = Sanitize::email($to);
        if (!$cleanTo) {
            error_log('[MAIL ERROR] Invalid recipient email: ' . $to);
            return false;
        }

        // Local development: log to /logs/mail.log
        if (APP_ENV === 'local') {
            $logDir = LOG_DIR;
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $mailLogFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : $logDir . 'mail.log';
            $logContent = sprintf(
                "============================================================\n" .
                "[%s] [DEV MAIL INTERCEPTED]\n" .
                "To: %s\n" .
                "Subject: %s\n" .
                "Headers: %s\n" .
                "Body:\n%s\n" .
                "============================================================\n\n",
                date('Y-m-d H:i:s'),
                $cleanTo,
                $subject,
                json_encode($headers, JSON_UNESCAPED_SLASHES),
                $body
            );

            @file_put_contents($mailLogFile, $logContent, FILE_APPEND | LOCK_EX);
            return true;
        }

        // Production: PHP mail()
        $headerLines = [];
        $hasFrom = false;
        foreach ($headers as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
            if (strcasecmp($k, 'From') === 0) {
                $hasFrom = true;
            }
        }
        if (!$hasFrom) {
            $headerLines[] = 'From: ' . SITE_NAME . ' <' . ADMIN_EMAIL . '>';
            $headerLines[] = 'Reply-To: ' . ADMIN_EMAIL;
        }
        $headerLines[] = 'X-Mailer: PHP/' . phpversion();

        return @mail($cleanTo, $subject, $body, implode("\r\n", $headerLines));
    }
}
