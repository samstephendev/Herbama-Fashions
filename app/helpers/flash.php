<?php
/**
 * Flash Message Helper
 * ====================
 * Thin wrapper around Session flash; renders Bootstrap-compatible alerts.
 */

declare(strict_types=1);

/**
 * Store a flash message.
 *
 * @param string $type    'success' | 'error' | 'warning' | 'info'
 * @param string $message Plain text (will be HTML-escaped on render)
 */
function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

/**
 * Render all flash messages as HTML <div> alerts and clear them.
 * Call once in the header or layout.
 */
function render_flash(): void
{
    $types = ['success', 'error', 'warning', 'info'];
    foreach ($types as $type) {
        $messages = Session::getFlash($type);
        foreach ($messages as $msg) {
            // Map 'error' → CSS class 'alert-error' (or 'danger' for Bootstrap)
            $cssClass = ($type === 'error') ? 'alert-error' : 'alert-' . $type;
            // Message is already HTML-escaped by Session::flash()
            echo '<div class="alert ' . $cssClass . '" role="alert">' . $msg . '</div>';
        }
    }
}
