<?php
/**
 * Heramba Fashion — Dynamic XML Sitemap Generator
 * ===================================================
 * Generates valid sitemap.xml with live URLs for all products, categories, and static pages.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = SITE_URL;
$prodModel = new ProductModel();

// Static & Core Pages
$staticUrls = [
    '/',
    '/collection.php?cat=girls',
    '/collection.php?cat=boys',
    '/collection.php?cat=infant',
    '/collection.php?cat=festive',
    '/new-arrivals.php',
    '/offers.php',
    '/about.php',
    '/why-heramba.php',
    '/quality-fabric.php',
    '/contact.php',
    '/faq.php',
    '/shipping.php',
    '/returns.php',
    '/size-guide.php',
    '/privacy.php',
    '/terms.php',
];

// Fetch active products
$db = db();
$stmt = $db->query('SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY updated_at DESC');
$products = $stmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($staticUrls as $rel): ?>
        <url>
            <loc><?= e($baseUrl . $rel) ?></loc>
            <changefreq>daily</changefreq>
            <priority><?= $rel === '/' ? '1.0' : '0.8' ?></priority>
        </url>
    <?php endforeach; ?>

    <?php foreach ($products as $p): ?>
        <url>
            <loc><?= e($baseUrl . '/product.php?slug=' . urlencode($p['slug'])) ?></loc>
            <lastmod><?= date('Y-m-d', strtotime($p['updated_at'])) ?></lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.9</priority>
        </url>
    <?php endforeach; ?>
</urlset>
