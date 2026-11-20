<?php
/**
 * ContactModel — Herbama Fashions
 * =================================
 * Handles newsletter subscriptions and contact inquiries.
 */

declare(strict_types=1);

class ContactModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Subscribe an email address to the newsletter.
     */
    public function subscribe(string $email): array
    {
        $cleanEmail = Sanitize::email($email);
        if (!$cleanEmail) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO subscribers (email, created_at)
                 VALUES (:email, NOW())
                 ON DUPLICATE KEY UPDATE id = id'
            );
            $stmt->execute([':email' => $cleanEmail]);
            return ['ok' => true, 'message' => 'Thank you for subscribing to Herbama Fashions!'];
        } catch (PDOException $e) {
            error_log('[ContactModel] subscribe error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Unable to save subscription. Please try again.'];
        }
    }

    /**
     * Submit an inquiry via Contact Us form.
     */
    public function sendMessage(string $name, string $email, ?string $phone, ?string $subject, string $message): array
    {
        $cleanName    = Sanitize::string($name, 120);
        $cleanEmail   = Sanitize::email($email);
        $cleanPhone   = Sanitize::phone($phone ?? '');
        $cleanSubject = Sanitize::string($subject ?? 'General Inquiry', 200);
        $cleanMessage = Sanitize::string($message, 3000);

        if (empty($cleanName) || !$cleanEmail || empty($cleanMessage)) {
            return ['ok' => false, 'error' => 'Please fill in all required fields with valid details.'];
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO messages (name, email, phone, subject, message, is_read, created_at)
                 VALUES (:name, :email, :phone, :subject, :msg, 0, NOW())'
            );
            $stmt->execute([
                ':name'    => $cleanName,
                ':email'   => $cleanEmail,
                ':phone'   => $cleanPhone,
                ':subject' => $cleanSubject,
                ':msg'     => $cleanMessage,
            ]);
            return ['ok' => true, 'message' => 'Thank you! Your message has been received. Our team will get back to you shortly.'];
        } catch (PDOException $e) {
            error_log('[ContactModel] sendMessage error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Unable to send your message right now. Please message us on WhatsApp for immediate help!'];
        }
    }
}
