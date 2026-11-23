<?php
/**
 * Test all public and admin pages via php -S against the database.
 */

declare(strict_types=1);

$host = '127.0.0.1';
$port = 8999;
$docroot = realpath(__DIR__ . '/../public_html');
$logFile = sys_get_temp_dir() . '/php_test_server.log';
$cookieJar = tempnam(sys_get_temp_dir(), 'cookie_');

// Start built-in server with stderr/stdout redirected to file to avoid buffer deadlocks
$cmd = sprintf(
    'C:\\php\\php.exe -d extension_dir="C:\\php\\ext" -d extension=pdo_mysql -d extension=openssl -d extension=curl -S %s:%d -t %s',
    $host,
    $port,
    escapeshellarg($docroot)
);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $logFile, 'w'],
    2 => ['file', $logFile, 'w'],
];

$process = proc_open($cmd, $descriptors, $pipes, dirname(__DIR__));
if (!is_resource($process)) {
    die("Failed to start php -S\n");
}

// Wait for server to bind
usleep(600000); // 600ms

// ── 1. Log in as admin using curl cookie jar ─────────────────────────────────
$loginUrl = sprintf('http://%s:%d/login.php', $host, $port);

// GET /login.php to obtain CSRF token & session cookie
$ch = curl_init($loginUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_COOKIEFILE     => $cookieJar,
    CURLOPT_HTTPHEADER     => [
        'X-Forwarded-Proto: https',
        'Connection: close',
    ],
]);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', (string)$html, $csrfMatches);
$csrfToken = $csrfMatches[1] ?? '';

if (!empty($csrfToken)) {
    // POST /login.php with admin credentials
    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'csrf_token' => $csrfToken,
            'email'      => 'admin@yourdomain.com',
            'password'   => 'AdminSecret1234!',
            'action'     => 'login',
        ]),
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => [
            'X-Forwarded-Proto: https',
            'Connection: close',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ── 2. Test every public and admin page ───────────────────────────────────────
$pages = [
    // ── Public Pages ─────────────────────────────────────────────────────────
    'Home (/)'                                         => '/',
    'Home (/index.php)'                                => '/index.php',
    'Collection (/collection.php)'                     => '/collection.php',
    'Collection Girls (/collection.php?cat=girls)'     => '/collection.php?cat=girls',
    'Collection Boys (/collection.php?cat=boys)'       => '/collection.php?cat=boys',
    'Collection Infant (/collection.php?cat=infant)'   => '/collection.php?cat=infant',
    'Collection Festive (/collection.php?cat=festive)' => '/collection.php?cat=festive',
    'Product Page (/product.php?slug=floral-frock)'    => '/product.php?slug=floral-frock',
    'Search (/search.php?q=frock)'                     => '/search.php?q=frock',
    'New Arrivals (/new-arrivals.php)'                 => '/new-arrivals.php',
    'Offers (/offers.php)'                             => '/offers.php',
    'Cart (/cart.php)'                                 => '/cart.php',
    'Account (/account.php)'                           => '/account.php',
    'Wishlist API (/api/wishlist.php)'                 => '/api/wishlist.php',
    'Checkout (/checkout.php)'                         => '/checkout.php',
    'About (/about.php)'                               => '/about.php',
    'Contact (/contact.php)'                           => '/contact.php',
    'FAQ (/faq.php)'                                   => '/faq.php',
    'Shipping (/shipping.php)'                         => '/shipping.php',
    'Returns (/returns.php)'                           => '/returns.php',
    'Privacy (/privacy.php)'                           => '/privacy.php',
    'Terms (/terms.php)'                               => '/terms.php',
    'Size Guide (/size-guide.php)'                     => '/size-guide.php',

    // ── Admin Pages (Fully rendered & executed with admin session) ───────────
    'Admin Dashboard (/admin/index.php)'               => '/admin/index.php',
    'Admin Orders (/admin/orders.php)'                 => '/admin/orders.php',
    'Admin Products (/admin/products.php)'             => '/admin/products.php',
    'Admin Stock (/admin/stock.php)'                   => '/admin/stock.php',
    'Admin Customers (/admin/customers.php)'           => '/admin/customers.php',
    'Admin Banners (/admin/banners.php)'               => '/admin/banners.php',
    'Admin Audit Log (/admin/audit-log.php)'           => '/admin/audit-log.php',
    'Admin Offers (/admin/offers.php)'                 => '/admin/offers.php',
    'Admin Admins (/admin/admins.php)'                 => '/admin/admins.php',
    'Admin Settings (/admin/settings.php)'             => '/admin/settings.php',
    'Admin Product Form (/admin/product-form.php)'     => '/admin/product-form.php',
];

echo "========================================================\n";
echo "  HERAMBA FASHION — PAGE HTTP STATUS VERIFICATION     \n";
echo "========================================================\n\n";

$results = [];

foreach ($pages as $label => $uri) {
    $url = sprintf('http://%s:%d%s', $host, $port, $uri);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER     => [
            'X-Forwarded-Proto: https',
            'Connection: close',
        ],
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) PageTester/1.0',
    ]);

    $body = (string)curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
        $status = 'ERR';
        $detail = $error;
        $passed = false;
    } else {
        $status = (string)$httpCode;
        // Check for SQL errors or PHP fatal crashes in the response body
        if (str_contains($body, 'SQLSTATE') || str_contains($body, 'Fatal error') || str_contains($body, 'Uncaught PDOException')) {
            preg_match('/(SQLSTATE\[.*?\]|Fatal error:.*?)(\n|<br)/i', $body, $m);
            $errSnippet = !empty($m[1]) ? strip_tags($m[1]) : 'SQL Exception';
            $detail = 'FAILED: ' . $errSnippet;
            $passed = false;
        } elseif ($httpCode === 500) {
            $detail = 'FAILED: Internal Server Error (500)';
            $passed = false;
        } elseif ($httpCode === 200) {
            $detail = 'OK (200 OK)';
            $passed = true;
        } else {
            $detail = 'HTTP ' . $httpCode;
            $passed = true;
        }
    }

    $results[] = [
        'label'  => $label,
        'uri'    => $uri,
        'code'   => $status,
        'detail' => $detail,
        'passed' => $passed,
    ];

    printf("[%s] %-52s HTTP %-4s (%s)\n", $passed ? 'PASS' : 'FAIL', $label, $status, $detail);
}

// Clean up
@unlink($cookieJar);
if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
proc_terminate($process);

echo "\n========================================================\n";
$passCount = count(array_filter($results, fn($r) => $r['passed']));
$totalCount = count($results);
echo "  PAGE TEST SUMMARY: {$passCount} / {$totalCount} PAGES PASSED\n";
echo "========================================================\n";
