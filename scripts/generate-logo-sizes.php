<?php
/**
 * generate-logo-sizes.php
 * Generates all required logo / favicon / og-image sizes from the master logo.
 * Run: php scripts/generate-logo-sizes.php
 */

$srcPng  = __DIR__ . '/../public_html/assets/images/brand/heramba-logo.png';
$srcJpeg = __DIR__ . '/../public_html/assets/images/brand/heramba-fashion-logo.jpeg';
$src = file_exists($srcPng) ? $srcPng : $srcJpeg;

$brandDir = __DIR__ . '/../public_html/assets/images/brand/';
$imgDir   = __DIR__ . '/../public_html/assets/images/';
$iconDir  = __DIR__ . '/../public_html/assets/images/icons/';
$rootPub  = __DIR__ . '/../public_html/';

foreach ([$brandDir, $imgDir, $iconDir, $rootPub] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// Load source
$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
if ($ext === 'jpeg' || $ext === 'jpg') {
    $master = imagecreatefromjpeg($src);
} elseif ($ext === 'png') {
    $master = imagecreatefrompng($src);
} else {
    exit("Unsupported image format: $ext\n");
}
imagealphablending($master, true);
imagesavealpha($master, true);

$srcW = imagesx($master);
$srcH = imagesy($master);

// Ensure heramba-logo.png exists at full resolution
if (!file_exists($srcPng)) {
    imagepng($master, $srcPng, 9);
}

function resample(GdImage $src, int $srcW, int $srcH, int $w, int $h): GdImage {
    $dst = imagecreatetruecolor($w, $h);
    // Transparent or white background - for badge with white background:
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $w - 1, $h - 1, $white);
    imagealphablending($dst, true);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
    return $dst;
}

// --- Logo display sizes (64, 128, 256, 512px PNG and WebP) ---
$logoSizes = [64, 128, 256, 512];
foreach ($logoSizes as $sz) {
    $img = resample($master, $srcW, $srcH, $sz, $sz);
    
    // Save to brandDir with both naming conventions
    $paths = [
        "{$brandDir}logo-{$sz}.png",
        "{$brandDir}heramba-logo-{$sz}.png",
        "{$imgDir}logo-{$sz}.png",
    ];
    foreach ($paths as $p) {
        imagepng($img, $p, 9);
    }
    
    if (function_exists('imagewebp')) {
        $webps = [
            "{$brandDir}logo-{$sz}.webp",
            "{$brandDir}heramba-logo-{$sz}.webp",
            "{$imgDir}logo-{$sz}.webp",
        ];
        foreach ($webps as $w) {
            imagewebp($img, $w, 90);
        }
    }
    
    // Default logo.png in assets/images/
    if ($sz === 128) {
        imagepng($img, "{$imgDir}logo.png", 9);
        if (function_exists('imagewebp')) imagewebp($img, "{$imgDir}logo.webp", 90);
    }

    echo "  Generated {$sz}x{$sz} PNG & WebP\n";
    imagedestroy($img);
}

// --- Favicon set (16, 32, 180, 512px) ---
$faviconSizes = [16, 32, 48, 180, 512];
foreach ($faviconSizes as $sz) {
    $img = resample($master, $srcW, $srcH, $sz, $sz);
    
    imagepng($img, "{$iconDir}favicon-{$sz}.png", 9);
    imagepng($img, "{$imgDir}favicon-{$sz}.png", 9);
    imagepng($img, "{$rootPub}favicon-{$sz}.png", 9);
    
    if ($sz === 32) {
        // Also save generic favicon.ico and favicon.png
        imagepng($img, "{$rootPub}favicon.ico", 9);
        imagepng($img, "{$rootPub}favicon.png", 9);
        imagepng($img, "{$imgDir}favicon.png", 9);
        imagepng($img, "{$imgDir}favicon.ico", 9);
    }
    if ($sz === 180) {
        // Apple touch icon
        imagepng($img, "{$iconDir}apple-touch-icon.png", 9);
        imagepng($img, "{$imgDir}apple-touch-icon.png", 9);
        imagepng($img, "{$rootPub}apple-touch-icon.png", 9);
    }
    
    echo "  Generated favicon {$sz}x{$sz}\n";
    imagedestroy($img);
}

// --- Open Graph Image: 1200x630 logo centred on white ---
$ogW = 1200; $ogH = 630;
$ogImg = imagecreatetruecolor($ogW, $ogH);
$white = imagecolorallocate($ogImg, 255, 255, 255);
imagefilledrectangle($ogImg, 0, 0, $ogW - 1, $ogH - 1, $white);
$logoSz = 400;
$logoImg = resample($master, $srcW, $srcH, $logoSz, $logoSz);
imagecopy($ogImg, $logoImg, (int)(($ogW - $logoSz) / 2), (int)(($ogH - $logoSz) / 2), 0, 0, $logoSz, $logoSz);

imagejpeg($ogImg, "{$iconDir}og-image.jpg", 92);
imagepng($ogImg, "{$iconDir}og-image.png", 9);
imagejpeg($ogImg, "{$imgDir}og-image.jpg", 92);
imagepng($ogImg, "{$imgDir}og-image.png", 9);
imagejpeg($ogImg, "{$rootPub}og-image.jpg", 92);

echo "  Generated Open Graph 1200x630 image (JPG & PNG)\n";

// --- Update logo.svg and favicon.svg as SVG wrappers around client logo ---
$b64 = base64_encode(file_get_contents("{$brandDir}logo-256.png"));
$svgContent = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256"><image href="data:image/png;base64,' . $b64 . '" width="256" height="256"/></svg>';
file_put_contents("{$imgDir}logo.svg", $svgContent);
file_put_contents("{$imgDir}favicon.svg", $svgContent);

imagedestroy($logoImg);
imagedestroy($ogImg);
imagedestroy($master);

echo "\nAll assets generated successfully!\n";
