<?php
/**
 * Sanitize & Validation Helper
 * =============================
 * Server-side input sanitization and validation utilities.
 * Never trust any client-supplied data.
 */

declare(strict_types=1);

class Sanitize
{
    /**
     * Sanitize a string: strip tags, trim whitespace.
     * Does NOT make the value "safe for HTML output" — use e() / htmlspecialchars for that.
     */
    public static function string(mixed $value, int $maxLen = 1000): string
    {
        if (!is_string($value)) {
            return '';
        }
        return mb_substr(trim(strip_tags($value)), 0, $maxLen, 'UTF-8');
    }

    /** Cast to positive integer; returns null if invalid / non-positive. */
    public static function positiveInt(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return ($int === false) ? null : (int) $int;
    }

    /** Cast to non-negative integer (0 allowed). */
    public static function nonNegativeInt(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return ($int === false) ? null : (int) $int;
    }

    /** Validate and return an email address string, or null. */
    public static function email(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $clean = filter_var(trim($value), FILTER_VALIDATE_EMAIL);
        return ($clean !== false) ? mb_strtolower($clean, 'UTF-8') : null;
    }

    /** Return a sanitized phone number (digits, +, spaces, hyphens only). */
    public static function phone(mixed $value, int $maxLen = 20): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $clean = preg_replace('/[^\d\+\s\-]/', '', trim($value));
        if (!$clean || strlen($clean) < 7 || strlen($clean) > $maxLen) {
            return null;
        }
        return $clean;
    }

    /** Validate a decimal price value (non-negative). Returns null on failure. */
    public static function price(mixed $value): ?float
    {
        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($float === false || $float < 0) {
            return null;
        }
        return round($float, 2);
    }

    /** Sanitize a URL slug: lowercase alphanumeric and hyphens only. */
    public static function slug(mixed $value, int $maxLen = 200): string
    {
        if (!is_string($value)) {
            return '';
        }
        $slug = mb_strtolower(trim($value), 'UTF-8');
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return mb_substr(trim($slug, '-'), 0, $maxLen, 'UTF-8');
    }

    /**
     * Validate a password:
     * - Min 8 characters
     * - At least one uppercase, one lowercase, one digit
     */
    public static function validatePassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password);
    }

    /** Validate that a string is one of an allowed set of values. */
    public static function enum(mixed $value, array $allowed): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        return in_array($value, $allowed, true) ? $value : null;
    }
}

// ── Global convenience functions ──────────────────────────────────────────────

/**
 * HTML-escape a value for safe output.
 * Always use e($var) when echoing user-supplied or database data.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Return sanitized string from $_POST or default. */
function post(string $key, string $default = '', int $maxLen = 1000): string
{
    return Sanitize::string($_POST[$key] ?? $default, $maxLen);
}

/** Return sanitized string from $_GET or default. */
function get(string $key, string $default = '', int $maxLen = 500): string
{
    return Sanitize::string($_GET[$key] ?? $default, $maxLen);
}

/** Return positive int from $_GET or null. */
function getInt(string $key): ?int
{
    return Sanitize::positiveInt($_GET[$key] ?? null);
}

/** Return positive int from $_POST or null. */
function postInt(string $key): ?int
{
    return Sanitize::positiveInt($_POST[$key] ?? null);
}
