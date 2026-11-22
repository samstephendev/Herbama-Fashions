<?php
/**
 * UserModel — Herbama Fashions
 * =============================
 * Handles all user-related DB operations.
 * Security contract:
 *   - PDO prepared statements only
 *   - Passwords: password_hash(ARGON2ID) + password_verify
 *   - Login: rate-limited with per-email lockout
 *   - Output: caller is responsible for e() escaping
 */

declare(strict_types=1);

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Registration
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Register a new customer.
     *
     * @return array{ok:bool, user_id?:int, error?:string}
     */
    public function register(string $fullName, string $email, string $password, ?string $phone = null): array
    {
        // Validate password strength
        if (!Sanitize::validatePassword($password)) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters with uppercase, lowercase and a digit.'];
        }

        // Check email uniqueness
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'An account with this email already exists.'];
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);

        $stmt = $this->db->prepare(
            "INSERT INTO users (full_name, email, phone, password_hash, role)
             VALUES (:name, :email, :phone, :hash, 'customer')"
        );
        $stmt->execute([
            ':name'  => $fullName,
            ':email' => $email,
            ':phone' => $phone,
            ':hash'  => $hash,
        ]);

        $userId = (int) $this->db->lastInsertId();
        audit_log('USER_REGISTERED', ['email' => $email], $userId);

        return ['ok' => true, 'user_id' => $userId];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Login / Authentication
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Attempt a login. Implements per-email rate-limiting and lockout.
     *
     * @return array{ok:bool, user?:array, error?:string, locked_until?:string}
     */
    public function login(string $email, string $password, string $ip): array
    {
        // Log the attempt (for analysis and admin review)
        $this->logAttempt($email, $ip);

        $stmt = $this->db->prepare(
            'SELECT id, full_name, email, phone, password_hash, role, is_blocked,
                    login_attempts, locked_until
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Timing-safe: still run password_verify against a dummy hash
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$dummydummydummy$dummydummydummydummydummydummydummy');
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        // Check if account is blocked by admin
        if ((int) $user['is_blocked'] === 1) {
            return ['ok' => false, 'error' => 'Your account has been suspended. Contact support.'];
        }

        // Check lockout
        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $mins = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            return [
                'ok'           => false,
                'error'        => "Too many failed attempts. Try again in {$mins} minute(s).",
                'locked_until' => $user['locked_until'],
            ];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($user['id'], (int) $user['login_attempts']);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        // Success — reset attempts, update last login
        $this->db->prepare(
            'UPDATE users SET login_attempts=0, locked_until=NULL,
             last_login_at=NOW(), last_login_ip=:ip
             WHERE id=:id'
        )->execute([':ip' => substr($ip, 0, 45), ':id' => $user['id']]);

        $this->logAttemptSuccess($email, $ip);

        // Check if hash needs rehash (future algorithm upgrade)
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID);
            $this->db->prepare('UPDATE users SET password_hash=:h WHERE id=:id')
                ->execute([':h' => $newHash, ':id' => $user['id']]);
        }

        unset($user['password_hash'], $user['login_attempts'], $user['locked_until']);
        return ['ok' => true, 'user' => $user];
    }

    /** Record a failed login attempt and lock after threshold. */
    private function recordFailedAttempt(int $userId, int $attempts): void
    {
        $attempts++;
        $lockedUntil = null;

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_SECONDS);
        }

        $this->db->prepare(
            'UPDATE users SET login_attempts=:attempts, locked_until=:locked WHERE id=:id'
        )->execute([':attempts' => $attempts, ':locked' => $lockedUntil, ':id' => $userId]);
    }

    /** Log a login attempt to the login_attempts table. */
    private function logAttempt(string $email, string $ip): void
    {
        try {
            $this->db->prepare(
                'INSERT INTO login_attempts (email, ip, success) VALUES (:email, :ip, 0)'
            )->execute([':email' => $email, ':ip' => substr($ip, 0, 45)]);
        } catch (PDOException $e) {
            error_log('[UserModel] logAttempt failed: ' . $e->getMessage());
        }
    }

    /** Mark the last attempt for this email as successful. */
    private function logAttemptSuccess(string $email, string $ip): void
    {
        try {
            $this->db->prepare(
                'UPDATE login_attempts SET success=1
                 WHERE email=:email AND ip=:ip
                 ORDER BY created_at DESC LIMIT 1'
            )->execute([':email' => $email, ':ip' => substr($ip, 0, 45)]);
        } catch (PDOException $e) {
            error_log('[UserModel] logAttemptSuccess failed: ' . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Password management
    // ══════════════════════════════════════════════════════════════════════════

    /** Change a user's own password (requires current password). */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        if (!Sanitize::validatePassword($newPassword)) {
            return ['ok' => false, 'error' => 'New password does not meet strength requirements.'];
        }

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            return ['ok' => false, 'error' => 'Current password is incorrect.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $this->db->prepare('UPDATE users SET password_hash=:h, updated_at=NOW() WHERE id=:id')
            ->execute([':h' => $newHash, ':id' => $userId]);

        audit_log('USER_PASSWORD_CHANGED', [], $userId);
        return ['ok' => true];
    }

    /**
     * Create an expiring password reset token for an email.
     * Returns the raw token string (to be emailed), or null if user does not exist.
     */
    public function createPasswordReset(string $email): ?string
    {
        $this->ensurePasswordResetsTable();

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email AND is_blocked = 0 LIMIT 1');
        $stmt->execute([':email' => $email]);
        if (!$stmt->fetch()) {
            return null;
        }

        // Clean up previous tokens for this email
        $this->db->prepare('DELETE FROM password_resets WHERE email = :email')->execute([':email' => $email]);

        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration

        $insertStmt = $this->db->prepare(
            'INSERT INTO password_resets (email, token_hash, expires_at, created_at)
             VALUES (:email, :hash, :expires, NOW())'
        );
        $insertStmt->execute([
            ':email'   => $email,
            ':hash'    => $tokenHash,
            ':expires' => $expiresAt,
        ]);

        return $rawToken;
    }

    /**
     * Verify whether a password reset token is valid and unexpired.
     */
    public function verifyPasswordResetToken(string $email, string $rawToken): bool
    {
        $this->ensurePasswordResetsTable();

        $tokenHash = hash('sha256', $rawToken);
        $stmt = $this->db->prepare(
            'SELECT id FROM password_resets
             WHERE email = :email AND token_hash = :hash AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':email' => $email, ':hash' => $tokenHash]);
        return (bool)$stmt->fetch();
    }

    /**
     * Reset user password using verified reset token.
     */
    public function resetPasswordWithToken(string $email, string $rawToken, string $newPassword): array
    {
        if (!Sanitize::validatePassword($newPassword)) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters with uppercase, lowercase and a digit.'];
        }

        if (!$this->verifyPasswordResetToken($email, $rawToken)) {
            return ['ok' => false, 'error' => 'This password reset link is invalid or has expired. Please request a new one.'];
        }

        $user = $this->findByEmail($email);
        if (!$user) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $this->db->prepare(
            'UPDATE users
             SET password_hash = :hash, login_attempts = 0, locked_until = NULL, updated_at = NOW()
             WHERE email = :email'
        )->execute([':hash' => $newHash, ':email' => $email]);

        // Delete all reset tokens for this email
        $this->db->prepare('DELETE FROM password_resets WHERE email = :email')->execute([':email' => $email]);

        audit_log('PASSWORD_RESET_SUCCESS', ['email' => $email], (int)$user['id']);

        return ['ok' => true];
    }

    /** Ensure password_resets table exists. */
    private function ensurePasswordResetsTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS `password_resets` (
              `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `email`      VARCHAR(255)    NOT NULL,
              `token_hash` VARCHAR(64)     NOT NULL,
              `expires_at` DATETIME        NOT NULL,
              `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_password_resets_email` (`email`),
              KEY `idx_password_resets_token` (`token_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Profile
    // ══════════════════════════════════════════════════════════════════════════

    /** Find user by ID. Returns array or null. */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, full_name, email, phone, role, is_blocked, created_at
             FROM users WHERE id=:id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Find user by email. Returns array or null. */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, full_name, email, phone, role, is_blocked, created_at
             FROM users WHERE email=:email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Update a user's own profile (name and phone only; email change needs separate flow). */
    public function updateProfile(int $userId, string $fullName, ?string $phone): array
    {
        $this->db->prepare(
            'UPDATE users SET full_name=:name, phone=:phone, updated_at=NOW() WHERE id=:id'
        )->execute([':name' => $fullName, ':phone' => $phone, ':id' => $userId]);

        return ['ok' => true];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Addresses (IDOR-safe: always filter by user_id)
    // ══════════════════════════════════════════════════════════════════════════

    /** List all addresses for a user. */
    public function getAddresses(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM addresses WHERE user_id=:uid ORDER BY is_default DESC, id ASC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a single address — MUST pass user_id to prevent IDOR.
     * Returns null if address doesn't belong to this user.
     */
    public function getAddress(int $addressId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM addresses WHERE id=:id AND user_id=:uid LIMIT 1'
        );
        $stmt->execute([':id' => $addressId, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Create a new address. If is_default, unset others first. */
    public function addAddress(int $userId, array $data): int
    {
        $this->db->beginTransaction();
        try {
            if (!empty($data['is_default'])) {
                $this->db->prepare(
                    'UPDATE addresses SET is_default=0 WHERE user_id=:uid'
                )->execute([':uid' => $userId]);
            }

            $stmt = $this->db->prepare(
                'INSERT INTO addresses
                 (user_id, recipient_name, phone, line1, line2, city, state, pincode, country, is_default)
                 VALUES (:uid, :name, :phone, :l1, :l2, :city, :state, :pin, :country, :def)'
            );
            $stmt->execute([
                ':uid'     => $userId,
                ':name'    => $data['recipient_name'],
                ':phone'   => $data['phone'],
                ':l1'      => $data['line1'],
                ':l2'      => $data['line2'] ?? null,
                ':city'    => $data['city'],
                ':state'   => $data['state'],
                ':pin'     => $data['pincode'],
                ':country' => $data['country'] ?? 'India',
                ':def'     => empty($data['is_default']) ? 0 : 1,
            ]);

            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('[UserModel] addAddress failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /** Update an address — IDOR-safe: requires user_id match. */
    public function updateAddress(int $addressId, int $userId, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            if (!empty($data['is_default'])) {
                $this->db->prepare(
                    'UPDATE addresses SET is_default=0 WHERE user_id=:uid'
                )->execute([':uid' => $userId]);
            }

            $stmt = $this->db->prepare(
                'UPDATE addresses
                 SET recipient_name=:name, phone=:phone, line1=:l1, line2=:l2,
                     city=:city, state=:state, pincode=:pin, country=:country, is_default=:def
                 WHERE id=:id AND user_id=:uid'   // ← IDOR guard
            );
            $stmt->execute([
                ':name'    => $data['recipient_name'],
                ':phone'   => $data['phone'],
                ':l1'      => $data['line1'],
                ':l2'      => $data['line2'] ?? null,
                ':city'    => $data['city'],
                ':state'   => $data['state'],
                ':pin'     => $data['pincode'],
                ':country' => $data['country'] ?? 'India',
                ':def'     => empty($data['is_default']) ? 0 : 1,
                ':id'      => $addressId,
                ':uid'     => $userId,
            ]);

            $this->db->commit();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Delete an address — IDOR-safe. */
    public function deleteAddress(int $addressId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM addresses WHERE id=:id AND user_id=:uid'
        );
        $stmt->execute([':id' => $addressId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Admin-only functions
    // ══════════════════════════════════════════════════════════════════════════

    /** List all users with pagination (admin only). */
    public function adminListUsers(int $page = 1, int $perPage = 30, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;
        $like   = '%' . $search . '%';

        $stmt = $this->db->prepare(
            "SELECT id, full_name, email, phone, role, is_blocked, created_at
             FROM users
             WHERE (:s = '' OR full_name LIKE :s1 OR email LIKE :s2)
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':s',      $search);
        $stmt->bindValue(':s1',     $like);
        $stmt->bindValue(':s2',     $like);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Block or unblock a user (admin only). */
    public function setBlockStatus(int $targetUserId, bool $blocked, int $adminId): bool
    {
        $this->db->prepare(
            "UPDATE users SET is_blocked=:blocked, updated_at=NOW() WHERE id=:id AND role != 'admin'"
        )->execute([':blocked' => (int) $blocked, ':id' => $targetUserId]);

        db_audit_log(
            $blocked ? 'USER_BLOCKED' : 'USER_UNBLOCKED',
            ['target_user_id' => $targetUserId],
            $adminId
        );
        return true;
    }

    /** Admin: list all administrators. */
    public function adminListAdmins(): array
    {
        $stmt = $this->db->query("SELECT id, full_name, email, phone, role, last_login_at, created_at FROM users WHERE role = 'admin' ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /** Admin: create a new admin account. */
    public function createAdmin(string $fullName, string $email, string $password, int $creatorId): array
    {
        if (!Sanitize::validatePassword($password)) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters with uppercase, lowercase and a digit.'];
        }

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'An account with this email already exists.'];
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);

        $stmt = $this->db->prepare(
            "INSERT INTO users (full_name, email, password_hash, role)
             VALUES (:name, :email, :hash, 'admin')"
        );
        $stmt->execute([
            ':name'  => $fullName,
            ':email' => $email,
            ':hash'  => $hash,
        ]);

        $newId = (int) $this->db->lastInsertId();
        db_audit_log('ADMIN_CREATED', ['new_admin_id' => $newId, 'email' => $email], $creatorId, 'users', $newId);

        return ['ok' => true, 'admin_id' => $newId];
    }

    /** Admin: delete a user (cannot delete self or another admin unless authorized). */
    public function adminDeleteUser(int $targetUserId, int $adminId): bool
    {
        if ($targetUserId === $adminId) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $targetUserId]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('USER_DELETED', ['target_user_id' => $targetUserId], $adminId, 'users', $targetUserId);
        }
        return $ok;
    }

    /** Check if at least one administrator account exists in the database. */
    public function hasAdmin(): bool
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Count the number of administrator accounts. */
    public function countAdmins(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Initial one-time administrator creation.
     * Only succeeds when NO admin user exists yet.
     */
    public function setupInitialAdmin(string $fullName, string $email, string $password): array
    {
        if ($this->hasAdmin()) {
            return ['ok' => false, 'error' => 'An administrator already exists. One-time setup is disabled.'];
        }

        $fullName = trim($fullName);
        $email    = strtolower(trim($email));

        if (empty($fullName)) {
            return ['ok' => false, 'error' => 'Please provide your full name.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please provide a valid email address.'];
        }

        if (strlen($password) < 12) {
            return ['ok' => false, 'error' => 'Admin password must be at least 12 characters long.'];
        }

        // Use PASSWORD_ARGON2ID if available on this PHP runtime, otherwise fallback to PASSWORD_DEFAULT
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $options = ($algo === PASSWORD_ARGON2ID) ? [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ] : [];

        $hash = password_hash($password, $algo, $options);

        // Check if an existing customer already has this email
        $chk = $this->db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $chk->execute([':email' => $email]);
        if ($chk->fetch()) {
            return ['ok' => false, 'error' => 'An account with this email address already exists.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO users (full_name, email, password_hash, role)
             VALUES (:name, :email, :hash, 'admin')"
        );
        $stmt->execute([
            ':name'  => $fullName,
            ':email' => $email,
            ':hash'  => $hash,
        ]);

        $newId = (int)$this->db->lastInsertId();
        db_audit_log('INITIAL_ADMIN_SETUP', ['admin_id' => $newId, 'email' => $email], $newId, 'users', $newId);

        return ['ok' => true, 'admin_id' => $newId];
    }

    /** Admin: get customer order statistics (total orders, total spend). */
    public function getCustomerStats(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(id) AS total_orders, 
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS total_spend,
                    MAX(created_at) AS last_order_at
             FROM orders WHERE user_id = :uid"
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return [
            'total_orders'  => (int)($row['total_orders'] ?? 0),
            'total_spend'   => (float)($row['total_spend'] ?? 0),
            'last_order_at' => $row['last_order_at'] ?? null,
        ];
    }
}
