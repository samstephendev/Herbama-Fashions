<?php
// Fix resolveSslOptions() to use integer-fallback constants so tests pass without pdo_mysql
$file = __DIR__ . '/../app/config/database.php';
$src  = file_get_contents($file);

// Find the block by unique markers and replace
$newBlock = <<<'NEWBLOCK'
        // Resolve PDO MySQL attribute constants, with integer fallbacks for environments
        // where pdo_mysql is not loaded (e.g. unit-test runners using pdo_sqlite).
        $attrSslCa     = defined('Pdo\Mysql::ATTR_SSL_CA')
            ? \Pdo\Mysql::ATTR_SSL_CA
            : (defined('PDO::MYSQL_ATTR_SSL_CA') ? PDO::MYSQL_ATTR_SSL_CA : 1008);
        $attrSslVerify = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
            ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT
            : (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') ? PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT : 1013);

        if ($mode === 'require') {
            // Connect with SSL enabled but without verifying server certificate (no CA needed).
            $caResolved = self::resolveCaPath($ca);
            $options[$attrSslCa]     = $caResolved ?? '';
            $options[$attrSslVerify] = false;
            return $options;
        }

        if ($mode === 'verify') {
            $caResolved = self::resolveCaPath($ca);
            if ($caResolved === null) {
                $errorMsg = '[DB ERROR] SSL CA file is missing or not found in "verify" mode: ' . ($ca !== '' ? $ca : '(DB_SSL_CA is empty)');
                // Log clear error to stderr instead of silently connecting without SSL
                if (defined('STDERR') && is_resource(STDERR)) {
                    fwrite(STDERR, $errorMsg . "\n");
                } else {
                    @file_put_contents('php://stderr', $errorMsg . "\n");
                }
                error_log($errorMsg);
                throw new PDOException($errorMsg);
            }

            $options[$attrSslCa]     = $caResolved;
            $options[$attrSslVerify] = true;
            return $options;
        }
NEWBLOCK;

// Use regex to replace the block from "if ($mode === 'require')" through the closing "}"
// of the verify block (just before "        // 'disable'")
$pattern = '/        if \(\$mode === \'require\'\) \{.*?        \}\n\n        \/\/ \'disable\'/s';
$replacement = $newBlock . "\n\n        // 'disable'";

$new = preg_replace($pattern, $replacement, $src, 1, $count);
if ($count === 0) {
    echo "ERROR: pattern not matched\n";
    exit(1);
}

file_put_contents($file, $new);
echo "OK: replaced $count block(s) in app/config/database.php\n";

// Quick sanity check
$check = file_get_contents($file);
if (strpos($check, '$attrSslCa') !== false && strpos($check, '$attrSslVerify') !== false) {
    echo "OK: new variables present in file\n";
} else {
    echo "ERROR: new variables NOT found after replacement\n";
    exit(1);
}
