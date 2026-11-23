<?php
/**
 * bulk-rename-brand.php
 * Replaces all old brand-name strings with the correct "Heramba Fashion" variants.
 * Safe to run multiple times (idempotent after first run).
 */

// ── Replacement map (order matters: longest/most-specific first) ─────────────
$replacements = [
    // Exact display names
    'Heramba Fashion'              => 'Heramba Fashion',
    'Heramba Fashion'             => 'Heramba Fashion',
    'Heramba Fashion'             => 'Heramba Fashion',
    'Heramba Fashion'              => 'Heramba Fashion',
    'Heramba Fashion'               => 'Heramba Fashion',   // in case some already half-fixed
    'HERAMBA FASHION'              => 'HERAMBA FASHION',
    // Domains / handles / slugs
    'yourdomain.com'           => 'yourdomain.com',
    'admin@yourdomain.com'     => 'admin@yourdomain.com',
    'contact@yourdomain.com'   => 'admin@yourdomain.com',
    'heramba-fashion.onrender.com' => 'heramba-fashion.onrender.com',
    '@herambaFashion'              => '@herambaFashion',
    'herambaFashion'               => 'herambaFashion',  // YouTube/Pinterest handle slug
    'heramba-fashion'              => 'heramba-fashion',
    // UPI placeholder
    'yourname@upi'                => 'yourname@upi',
    // DB / folder names in comments / README only (not actual DB names)
    // We only replace in strings — exact case
    'heramba_db'                    => 'heramba_db',
    'heramba_user'                  => 'heramba_user',
    // WhatsApp text refs
    'Heramba Fashion!'             => 'Heramba Fashion!',
    'Hello Heramba'                 => 'Hello Heramba',
    // Comment-only doc strings
    'Heramba Fashion —'            => 'Heramba Fashion —',
    'Heramba Fashion·'             => 'Heramba Fashion ·',
    '— Heramba Fashion'            => '— Heramba Fashion',
    '— Heramba Admin'               => '— Heramba Admin',
    'Heramba Admin'                 => 'Heramba Admin',
    // Remaining standalone "Heramba" (About Heramba, Why Heramba, etc.)
    'About Heramba'                 => 'About Heramba',
    'Why Heramba'                   => 'Why Heramba',
    'why-heramba'                   => 'why-heramba',   // URL slug & filename ref
    'why-heramba.php'               => 'why-heramba.php',
    'Heramba'                       => 'Heramba',       // catch-all for remaining
    'HERAMBA'                       => 'HERAMBA',
    'heramba'                      => 'heramba',       // lowercase variants
    'Heramba'                      => 'Heramba',
    'Heramba'                      => 'Heramba',
];

// ── File extensions to scan ───────────────────────────────────────────────────
$extensions = ['php', 'js', 'css', 'html', 'md', 'json', 'xml', 'txt', 'svg', 'sql'];

// ── Directories / files to SKIP ───────────────────────────────────────────────
$skipPatterns = [
    'rename-brand.sql',    // the migration file itself — search strings are intentional
    '.git',
    'node_modules',
    'build-logo.php',      // script uses logo filenames not brand text
    'heramba-fashion-logo', // actual asset filename — keep
    'vendor',
];

function shouldSkip(string $path, array $patterns): bool {
    foreach ($patterns as $p) {
        if (str_contains($path, $p)) return true;
    }
    return false;
}

// ── Collect files ─────────────────────────────────────────────────────────────
$root = dirname(__DIR__);  // project root (one level up from scripts/)
$changed = [];
$scanned = 0;

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iter as $file) {
    $path = $file->getRealPath();
    if (shouldSkip($path, $skipPatterns)) continue;

    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $extensions, true)) continue;

    $scanned++;
    $original = file_get_contents($path);
    $modified = $original;

    foreach ($replacements as $old => $new) {
        $modified = str_replace($old, $new, $modified);
    }

    if ($modified !== $original) {
        file_put_contents($path, $modified);
        $changed[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }
}

echo "Scanned : $scanned files\n";
echo "Changed : " . count($changed) . " files\n\n";
foreach ($changed as $f) {
    echo "  CHANGED: $f\n";
}
echo "\nDone.\n";
