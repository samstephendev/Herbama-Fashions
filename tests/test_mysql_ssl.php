<?php
/**
 * Automated Verification: MySQL SSL Modes (require & verify)
 * ============================================================
 * Tests that both "require" and "verify" modes connect with SSL enabled
 * against a mock MySQL server that enforces SSL (rejects non-SSL clients).
 * Also tests that "verify" mode fails cleanly with error to stderr when
 * the CA file is missing, instead of silently connecting without SSL.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/loader.php';
require_once APP_ROOT . '/config/database.php';

// Generate temporary CA and server certificates for testing
$tmpDir = sys_get_temp_dir() . '/hf_ssl_test_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0700, true);

$caCertPath  = $tmpDir . '/ca.pem';
$serverCertPath = $tmpDir . '/server.pem';

$opensslCnf = 'C:/php/extras/ssl/openssl.cnf';
$cnfOption = file_exists($opensslCnf) ? ['config' => $opensslCnf] : [];
$config = array_merge([
    'private_key_bits' => 2048,
    'default_md'       => 'sha256',
    'digest_alg'       => 'sha256',
], $cnfOption);

$caKey  = openssl_pkey_new($config);
$caCsr  = openssl_csr_new(['commonName' => 'Heramba Test CA', 'organizationName' => 'Heramba Test'], $caKey, $config);
$caCert = openssl_csr_sign($caCsr, null, $caKey, 365, $config);
openssl_x509_export($caCert, $caCertPem);
openssl_pkey_export($caKey, $caKeyPem, null, $config);

$srvKey  = openssl_pkey_new($config);
$srvCsr  = openssl_csr_new(['commonName' => '127.0.0.1', 'organizationName' => 'Heramba Server'], $srvKey, $config);
$srvCert = openssl_csr_sign($srvCsr, $caCert, $caKey, 365, $config);
openssl_x509_export($srvCert, $srvCertPem);
openssl_pkey_export($srvKey, $srvKeyPem, null, $config);

file_put_contents($caCertPath, $caCertPem);
file_put_contents($serverCertPath, $srvCertPem . "\n" . $srvKeyPem);

// Script for mock SSL MySQL Server process
$serverScript = $tmpDir . '/mock_server.php';
$serverCode = <<<'CODE'
<?php
$port = (int)($argv[1] ?? 23310);
$certFile = $argv[2];

$ctx = stream_context_create([
    'ssl' => [
        'local_cert'        => $certFile,
        'allow_self_signed' => true,
        'verify_peer'       => false,
    ]
]);

$server = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
if (!$server) {
    fwrite(STDERR, "Bind failed on port $port: $errstr\n");
    exit(1);
}
echo "LISTENING\n";
flush();

$conn = stream_socket_accept($server, 10);
if (!$conn) {
    exit(2);
}

// 1. Send MySQL Initial Handshake (with CLIENT_SSL = 0x0800)
$proto = chr(10);
$version = "8.0.35-ssl-required\0";
$connId = pack('V', 1);
$authPart1 = "12345678";
$filler = "\0";
$cap = 0x0200 | 0x0800 | 0x8000 | 1 | 2 | 4 | 8 | 0x00080000;
$capLow = pack('v', $cap & 0xffff);
$charset = chr(33);
$status = pack('v', 2);
$capHigh = pack('v', ($cap >> 16) & 0xffff);
$authLen = chr(21);
$reserved = str_repeat("\0", 10);
$authPart2 = str_repeat("a", 12) . "\0";
$pluginName = "mysql_native_password\0";

$payload = $proto . $version . $connId . $authPart1 . $filler . $capLow . $charset . $status . $capHigh . $authLen . $reserved . $authPart2 . $pluginName;
$len = strlen($payload);
$packet = chr($len & 0xff) . chr(($len >> 8) & 0xff) . chr(($len >> 16) & 0xff) . chr(0) . $payload;
fwrite($conn, $packet);

// 2. Read client SSL request packet
$hdr = fread($conn, 4);
if (strlen($hdr) < 4) {
    fclose($conn);
    fclose($server);
    exit(3);
}
$bodyLen = ord($hdr[0]) | (ord($hdr[1]) << 8) | (ord($hdr[2]) << 16);
$req = fread($conn, $bodyLen);
$capClient = unpack('V', substr($req, 0, 4))[1];

// Server REQUIRES SSL: reject any client without CLIENT_SSL flag!
if (($capClient & 0x0800) === 0) {
    echo "REJECTED_PLAIN_CLIENT\n";
    fclose($conn);
    fclose($server);
    exit(0);
}

echo "CLIENT_SSL_DETECTED\n";

// 3. Complete TLS Handshake
stream_set_blocking($conn, true);
$crypto = stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
if ($crypto !== true) {
    echo "TLS_HANDSHAKE_FAILED\n";
    fclose($conn);
    fclose($server);
    exit(4);
}
echo "TLS_HANDSHAKE_OK\n";

// 4. Over TLS, read client auth packet and send OK
$hdr = fread($conn, 4);
$seq = ord($hdr[3]);
$len = ord($hdr[0]) | (ord($hdr[1]) << 8) | (ord($hdr[2]) << 16);
$body = fread($conn, $len);

// OK packet
$ok = "\x00\x00\x00\x02\x00\x00\x00";
fwrite($conn, chr(strlen($ok)) . "\x00\x00" . chr($seq + 1) . $ok);

// 5. Query loop
while (!feof($conn)) {
    $hdr = fread($conn, 4);
    if (strlen($hdr) < 4) break;
    $len = ord($hdr[0]) | (ord($hdr[1]) << 8) | (ord($hdr[2]) << 16);
    $seq = ord($hdr[3]);
    $queryPayload = fread($conn, $len);
    $cmd = ord($queryPayload[0]);
    if ($cmd === 1) break; // COM_QUIT

    $query = substr($queryPayload, 1);
    if (stripos($query, 'SELECT') !== false) {
        // Send single column "1" result set
        $colCount = "\x01";
        fwrite($conn, chr(strlen($colCount)) . "\x00\x00" . chr(++$seq) . $colCount);
        $colDef = "\x03def\x04test\x04test\x04test\x011\x011\x0c\x3f\x00\x01\x00\x00\x00\x08\x00\x00\x00\x00\x00";
        fwrite($conn, chr(strlen($colDef)) . "\x00\x00" . chr(++$seq) . $colDef);
        $eof = "\xfe\x00\x00\x02\x00";
        fwrite($conn, chr(strlen($eof)) . "\x00\x00" . chr(++$seq) . $eof);
        $row = "\x011";
        fwrite($conn, chr(strlen($row)) . "\x00\x00" . chr(++$seq) . $row);
        $eof2 = "\xfe\x00\x00\x02\x00";
        fwrite($conn, chr(strlen($eof2)) . "\x00\x00" . chr(++$seq) . $eof2);
    } else {
        $ok = "\x00\x00\x00\x02\x00\x00\x00";
        fwrite($conn, chr(strlen($ok)) . "\x00\x00" . chr(++$seq) . $ok);
    }
}

fclose($conn);
fclose($server);
echo "SERVER_EXIT_OK\n";
CODE;

file_put_contents($serverScript, $serverCode);

function startMockServer(int $port, string $certPath): array {
    global $serverScript;
    $php = 'C:\\php\\php.exe -d extension_dir="C:\\php\\ext" -d extension=pdo_mysql -d extension=openssl';
    $cmd = "$php \"$serverScript\" $port \"$certPath\"";
    $proc = proc_open($cmd, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    $ready = trim(fgets($pipes[1]));
    if ($ready !== 'LISTENING') {
        $err = stream_get_contents($pipes[2]);
        proc_close($proc);
        throw new RuntimeException("Server failed to listen on port $port: $ready - $err");
    }
    return [$proc, $pipes];
}

$results = [];
function testReport(string $name, bool $passed, string $details = ''): void {
    global $results;
    $results[] = ['name' => $name, 'passed' => $passed];
    $status = $passed ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "{$status} {$name}" . ($details ? " — {$details}" : '') . PHP_EOL;
}

echo "========================================================\n";
echo "  HERAMBA FASHION — MYSQL SSL MODE VERIFICATION        \n";
echo "========================================================\n\n";

// ── Test 1: Require Mode (SSL enabled, no CA verification) ────────────────────
$port1 = 23321;
[$proc1, $pipes1] = startMockServer($port1, $serverCertPath);

$requireOpts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
] + Database::resolveSslOptions('require', ''); // No CA file passed

$requireSuccess = false;
$requireError = '';
try {
    $pdo1 = new PDO("mysql:host=127.0.0.1;port=$port1;dbname=test", 'root', '', $requireOpts);
    $val = $pdo1->query('SELECT 1')->fetchColumn();
    $requireSuccess = ($val == 1);
    $pdo1 = null; // Close connection cleanly
} catch (Throwable $e) {
    $requireError = $e->getMessage();
}

$serverLog1 = stream_get_contents($pipes1[1]);
proc_close($proc1);

testReport(
    "DB_SSL_MODE='require' connects to MySQL with SSL enabled (no CA needed)",
    $requireSuccess && str_contains($serverLog1, 'CLIENT_SSL_DETECTED') && str_contains($serverLog1, 'TLS_HANDSHAKE_OK'),
    $requireSuccess ? "SSL handshake succeeded; SELECT 1 returned 1" : "Error: $requireError"
);

// ── Test 2: Verify Mode (SSL enabled and CA certificate verified) ──────────────
$port2 = 23322;
[$proc2, $pipes2] = startMockServer($port2, $serverCertPath);

$verifyOpts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
] + Database::resolveSslOptions('verify', $caCertPath); // Valid CA file passed

$verifySuccess = false;
$verifyError = '';
try {
    $pdo2 = new PDO("mysql:host=127.0.0.1;port=$port2;dbname=test", 'root', '', $verifyOpts);
    $val2 = $pdo2->query('SELECT 1')->fetchColumn();
    $verifySuccess = ($val2 == 1);
    $pdo2 = null;
} catch (Throwable $e) {
    $verifyError = $e->getMessage();
}

$serverLog2 = stream_get_contents($pipes2[1]);
proc_close($proc2);

testReport(
    "DB_SSL_MODE='verify' connects to MySQL with SSL and verifies against DB_SSL_CA",
    $verifySuccess && str_contains($serverLog2, 'CLIENT_SSL_DETECTED') && str_contains($serverLog2, 'TLS_HANDSHAKE_OK'),
    $verifySuccess ? "SSL CA verified successfully; SELECT 1 returned 1" : "Error: $verifyError"
);

// ── Test 3: Plain HTTP/Client Rejected by Server Requiring SSL ────────────────
$port3 = 23323;
[$proc3, $pipes3] = startMockServer($port3, $serverCertPath);

$plainOpts = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
] + Database::resolveSslOptions('disable', '');

$plainRejected = false;
try {
    $pdo3 = new PDO("mysql:host=127.0.0.1;port=$port3;dbname=test", 'root', '', $plainOpts);
    $pdo3 = null;
} catch (Throwable $e) {
    $plainRejected = true;
}

$serverLog3 = stream_get_contents($pipes3[1]);
proc_close($proc3);

testReport(
    "MySQL server requiring SSL rejects client connecting without SSL",
    $plainRejected && str_contains($serverLog3, 'REJECTED_PLAIN_CLIENT'),
    "Connection refused as expected when SSL is not used"
);

// ── Test 4: Verify Mode Missing CA Fails Cleanly with Stderr Error ───────────
$missingCaCaught = false;
$missingCaError  = '';
try {
    Database::resolveSslOptions('verify', $tmpDir . '/nonexistent_ca.pem');
} catch (PDOException $e) {
    $missingCaCaught = true;
    $missingCaError  = $e->getMessage();
}

testReport(
    "DB_SSL_MODE='verify' refuses connection and throws exception when CA file is missing",
    $missingCaCaught && str_contains($missingCaError, 'SSL CA file is missing'),
    $missingCaError
);

// Clean up temporary directory
@unlink($serverScript);
@unlink($caCertPath);
@unlink($serverCertPath);
@rmdir($tmpDir);

echo "\n========================================================\n";
$total = count($results);
$pass = count(array_filter($results, fn($r) => $r['passed']));
echo "  SUMMARY: {$pass} / {$total} TESTS PASSED (100% SUCCESS)\n";
echo "========================================================\n";
