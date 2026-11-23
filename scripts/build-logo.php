<?php
/**
 * scripts/build-logo.php
 * One-time CLI script to build cropped circular logo assets with transparent background.
 * Blocked from direct web execution.
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['argv'])) {
    http_response_code(403);
    exit("Access denied: CLI script only.\n");
}

$sourceFile = __DIR__ . '/../public_html/assets/images/brand/heramba-fashion-logo.png';
if (!file_exists($sourceFile)) {
    // Check if jpeg exists
    $jpeg = __DIR__ . '/../public_html/assets/images/brand/heramba-fashion-logo.jpeg';
    if (file_exists($jpeg)) {
        $srcJpg = imagecreatefromjpeg($jpeg);
        imagepng($srcJpg, $sourceFile, 9);
        imagedestroy($srcJpg);
    } else {
        fwrite(STDERR, "Source file not found: $sourceFile\n");
        exit(1);
    }
}

$brandDir = __DIR__ . '/../public_html/assets/images/brand/';
$iconDir  = __DIR__ . '/../public_html/assets/images/icons/';
$imgDir   = __DIR__ . '/../public_html/assets/images/';
$rootPub  = __DIR__ . '/../public_html/';

foreach ([$brandDir, $iconDir, $imgDir, $rootPub] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

echo "Loading original logo from {$sourceFile}...\n";
$master = imagecreatefrompng($sourceFile);
if (!$master) {
    fwrite(STDERR, "Failed to load master PNG\n");
    exit(1);
}

// 1. Crop to perfect circle with transparent background (remove white corners)
// Center at (625, 625), radius 618px (diameter 1236px)
$cx = 625.0;
$cy = 625.0;
$r  = 618.0;
$dia = (int)(2 * $r);

echo "Cropping to circle ({$dia}x{$dia}) with transparent background...\n";
$cropped = imagecreatetruecolor($dia, $dia);
imagealphablending($cropped, false);
imagesavealpha($cropped, true);
$transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
imagefilledrectangle($cropped, 0, 0, $dia - 1, $dia - 1, $transparent);

for ($y = 0; $y < $dia; $y++) {
    for ($x = 0; $x < $dia; $x++) {
        $dx = $x - $r;
        $dy = $y - $r;
        $dist = sqrt($dx * $dx + $dy * $dy);

        if ($dist <= $r) {
            $srcX = (int)round($cx - $r + $x);
            $srcY = (int)round($cy - $r + $y);
            $color = imagecolorat($master, $srcX, $srcY);

            if ($dist > $r - 1.0) {
                // Sub-pixel antialiased circle border
                $frac = $r - $dist;
                $alpha = (int)round(127 * (1.0 - $frac));
                $rgba = imagecolorallocatealpha(
                    $cropped,
                    ($color >> 16) & 0xFF,
                    ($color >> 8) & 0xFF,
                    $color & 0xFF,
                    $alpha
                );
                imagesetpixel($cropped, $x, $y, $rgba);
            } else {
                imagesetpixel($cropped, $x, $y, $color);
            }
        }
    }
}

// Helper function to create resized transparent image
function createResizedTransparent(GdImage $src, int $srcDia, int $targetSize): GdImage {
    $dst = imagecreatetruecolor($targetSize, $targetSize);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $targetSize - 1, $targetSize - 1, $trans);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetSize, $targetSize, $srcDia, $srcDia);
    return $dst;
}

// 2. Generate logo-512, logo-256, logo-128, logo-64 in PNG & WebP
$sizes = [512, 256, 128, 64];
foreach ($sizes as $sz) {
    echo "Generating logo-{$sz}.png and logo-{$sz}.webp...\n";
    $res = createResizedTransparent($cropped, $dia, $sz);

    $pngPath  = "{$brandDir}logo-{$sz}.png";
    $webpPath = "{$brandDir}logo-{$sz}.webp";
    imagepng($res, $pngPath, 9);
    if (function_exists('imagewebp')) {
        imagewebp($res, $webpPath, 90);
    }

    // Mirror to assets/images/
    imagepng($res, "{$imgDir}logo-{$sz}.png", 9);
    if (function_exists('imagewebp')) {
        imagewebp($res, "{$imgDir}logo-{$sz}.webp", 90);
    }

    // General logo.png / logo.webp alias at 128px
    if ($sz === 128) {
        imagepng($res, "{$brandDir}logo.png", 9);
        imagepng($res, "{$imgDir}logo.png", 9);
        if (function_exists('imagewebp')) {
            imagewebp($res, "{$brandDir}logo.webp", 90);
            imagewebp($res, "{$imgDir}logo.webp", 90);
        }
    }

    imagedestroy($res);
}

// 3. Generate favicon set (16, 32, 48, 180, 512 px)
$favSizes = [16, 32, 48, 180, 512];
foreach ($favSizes as $sz) {
    echo "Generating favicon-{$sz}.png...\n";
    $res = createResizedTransparent($cropped, $dia, $sz);

    $favPath = "{$iconDir}favicon-{$sz}.png";
    imagepng($res, $favPath, 9);
    imagepng($res, "{$imgDir}favicon-{$sz}.png", 9);
    imagepng($res, "{$rootPub}favicon-{$sz}.png", 9);

    if ($sz === 180) {
        imagepng($res, "{$iconDir}apple-touch-icon.png", 9);
        imagepng($res, "{$imgDir}apple-touch-icon.png", 9);
        imagepng($res, "{$rootPub}apple-touch-icon.png", 9);
    }
    if ($sz === 32) {
        imagepng($res, "{$iconDir}favicon.ico", 9);
        imagepng($res, "{$imgDir}favicon.ico", 9);
        imagepng($res, "{$rootPub}favicon.ico", 9);
        imagepng($res, "{$imgDir}favicon.png", 9);
        imagepng($res, "{$rootPub}favicon.png", 9);
    }

    imagedestroy($res);
}

// 4. Generate Open Graph image (1200x630, logo centered on cream #FFF9F3 background)
echo "Generating Open Graph image (1200x630, cream #FFF9F3 background)...\n";
$ogW = 1200;
$ogH = 630;
$ogImg = imagecreatetruecolor($ogW, $ogH);
// Cream #FFF9F3: R=255, G=249, B=243
$cream = imagecolorallocate($ogImg, 255, 249, 243);
imagefilledrectangle($ogImg, 0, 0, $ogW - 1, $ogH - 1, $cream);

// Circular logo size on OG image: 420x420
$ogLogoSz = 420;
$ogLogo = createResizedTransparent($cropped, $dia, $ogLogoSz);
$ogX = (int)(($ogW - $ogLogoSz) / 2);
$ogY = (int)(($ogH - $ogLogoSz) / 2);

imagecopy($ogImg, $ogLogo, $ogX, $ogY, 0, 0, $ogLogoSz, $ogLogoSz);
imagedestroy($ogLogo);

// Save OG image as both JPEG and PNG
imagejpeg($ogImg, "{$iconDir}og-image.jpg", 94);
imagepng($ogImg, "{$iconDir}og-image.png", 9);
imagejpeg($ogImg, "{$imgDir}og-image.jpg", 94);
imagepng($ogImg, "{$imgDir}og-image.png", 9);
imagejpeg($ogImg, "{$rootPub}og-image.jpg", 94);
imagedestroy($ogImg);

// Also generate SVG fallback embedding the 256px png for any old cached SVG requests
$b64 = base64_encode(file_get_contents("{$brandDir}logo-256.png"));
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256"><image href="data:image/png;base64,' . $b64 . '" width="256" height="256"/></svg>';
file_put_contents("{$imgDir}logo.svg", $svg);
file_put_contents("{$imgDir}favicon.svg", $svg);

imagedestroy($cropped);
imagedestroy($master);

echo "Logo build completed successfully!\n";
