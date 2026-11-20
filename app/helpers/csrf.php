<?php
/**
 * CSRF Helper
 * ===========
 * Per-session CSRF token with constant-time comparison.
 * Every POST form must include <?= csrf_field() ?> and validate with csrf_verify().
 */

declare(strict_types=1);

class CSRF
{
    private const TOKEN_KEY = '_csrf_token';

    /** Generate (or return existing) CSRF token for the current session. */
    public static function token(): string
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    /** Render a hidden input field containing the CSRF token. */
    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf" value="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Validate the CSRF token from POST data.
     * Uses hash_equals() for constant-time comparison (prevents timing attacks).
     * Dies with 403 on failure.
     */
    public static function verify(): void
    {
        $submitted = $_POST['_csrf'] ?? '';
        $expected  = self::token();

        if (
            !is_string($submitted) ||
            strlen($submitted) === 0 ||
            !hash_equals($expected, $submitted)
        ) {
            http_response_code(403);
            error_log('[CSRF] Token mismatch from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            // Generic error — no information leakage
            die('Invalid request. Please go back and try again.');
        }

        // Rotate the token after successful verification (double-submit protection)
        self::rotate();
    }

    /** Rotate the token (call after each successful form submission). */
    public static function rotate(): void
    {
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
}

// ── Global convenience functions ─────────────────────────────────────────────

/** Echo a hidden CSRF input field. */
function csrf_field(): string
{
    return CSRF::field();
}

/** Verify CSRF on POST requests; call at top of every POST handler. */
function csrf_verify(): void
{
    CSRF::verify();
}
