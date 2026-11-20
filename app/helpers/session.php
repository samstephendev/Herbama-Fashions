<?php
/**
 * Session Helper
 * ==============
 * Secure session management: httpOnly, Secure, SameSite=Lax cookies,
 * idle timeout, session fixation prevention, and role helpers.
 */

declare(strict_types=1);

class Session
{
    private static bool $started = false;

    /** Configure and start the session once. */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        // Cookie security flags
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        );
        $secureCookie = (APP_ENV === 'production') ? true : $isHttps;

        session_set_cookie_params([
            'lifetime' => 0,                     // Browser-session cookie
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secureCookie,
            'httponly' => true,                  // No JS access
            'samesite' => 'Lax',
        ]);

        session_name(SESSION_NAME);
        session_start();
        self::$started = true;

        // ── Idle timeout check ───────────────────────────────────────────────
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > SESSION_LIFETIME) {
                self::destroy();
                return;
            }
        }
        $_SESSION['_last_activity'] = time();

        // ── Session fixation: regenerate ID periodically ─────────────────────
        // We also call session_regenerate_id() explicitly on login/privilege change.
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 300) {
            // Rotate session ID every 5 minutes even without login
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    // ── Authentication helpers ────────────────────────────────────────────────

    /** Log a user in: set session data and regenerate session ID. */
    public static function login(array $user): void
    {
        // Regenerate session ID on privilege change (prevents session fixation)
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];        // 'customer' | 'admin'
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email']= $user['email'];
        $_SESSION['_created']  = time();
        $_SESSION['_last_activity'] = time();
    }

    /** Log the user out and destroy the session. */
    public static function logout(): void
    {
        self::destroy();
    }

    /** Is someone logged in? */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    /** Is the logged-in user an admin? */
    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /** Require the user to be logged in; redirect if not. */
    public static function requireLogin(string $redirect = '/login.php'): void
    {
        if (!self::isLoggedIn()) {
            $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '/';
            self::flash('error', 'Please log in to continue.');
            header('Location: ' . SITE_URL . $redirect);
            exit;
        }
    }

    /** Require admin role; redirect with error if not. */
    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            http_response_code(403);
            self::flash('error', 'Access denied.');
            header('Location: ' . SITE_URL . '/');
            exit;
        }
    }

    /** Get the current user's ID or null. */
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** Get the current user's role or null. */
    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    // ── Flash messages ────────────────────────────────────────────────────────

    /** Store a one-time flash message. */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }

    /** Retrieve and clear flash messages of a given type. */
    public static function getFlash(string $type): array
    {
        $messages = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $messages;
    }

    /** Destroy session cleanly. */
    private static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }
}
