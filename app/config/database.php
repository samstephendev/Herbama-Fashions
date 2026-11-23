<?php
/**
 * Database Connection — PDO singleton
 * =====================================
 * Returns a single PDO instance per request.
 * Supports standard local connections (XAMPP/Hostinger) and SSL connections (Aiven).
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    /** Returns the shared PDO connection, creating it on first call. */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                (int)DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Real prepared statements
            ];
            if (defined('Pdo\Mysql::ATTR_FOUND_ROWS')) {
                $options[\Pdo\Mysql::ATTR_FOUND_ROWS] = true;
            } elseif (defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
                $options[@PDO::MYSQL_ATTR_FOUND_ROWS] = true;
            }

            // Resolve SSL options based on DB_SSL_MODE and DB_SSL_CA
            try {
                $sslOptions = self::resolveSslOptions();
                $options += $sslOptions;
            } catch (PDOException $sslEx) {
                // SSL configuration error (e.g. missing CA in verify mode)
                error_log('[DB] SSL Configuration Error: ' . $sslEx->getMessage());
                http_response_code(503);
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    die('<h3>Database Connection Failed</h3>' .
                        '<p><strong>SSL Configuration Error:</strong> ' . htmlspecialchars($sslEx->getMessage()) . '</p>');
                } else {
                    die('Service temporarily unavailable. Please try again later.');
                }
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

                // In non-production environments (and in CLI test runs), enable ONLY_FULL_GROUP_BY
                // so that GROUP BY violations are caught locally before reaching a strict server.
                $appEnv = defined('APP_ENV') ? APP_ENV : 'local';
                if ($appEnv !== 'production' || (defined('TEST_SUITE_RUNNING') && TEST_SUITE_RUNNING)) {
                    try {
                        self::$instance->exec("SET SESSION sql_mode = CONCAT(@@sql_mode, ',ONLY_FULL_GROUP_BY')");
                    } catch (\Throwable $ignore) {
                        // Silently ignore if the driver does not support SET SESSION (e.g. SQLite in unit tests)
                    }
                }
            } catch (PDOException $e) {
                // Log the real error privately
                error_log('[DB] Connection failed: ' . $e->getMessage());
                http_response_code(503);

                if (defined('APP_DEBUG') && APP_DEBUG) {
                    die('<h3>Database Connection Failed</h3>' .
                        '<p>Could not connect to MySQL server at <code>' . htmlspecialchars(DB_HOST . ':' . DB_PORT) . '</code>.</p>' .
                        '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
                        '<p><em>Please verify your database credentials in <code>.env</code> or <code>app/config/config.php</code>.</em></p>');
                } else {
                    die('Service temporarily unavailable. Please try again later.');
                }
            }
        }

        return self::$instance;
    }

    /** Test if database is reachable without terminating execution. */
    public static function testConnection(): array
    {
        try {
            $pdo = self::getInstance();
            $stmt = $pdo->query('SELECT 1');
            return [
                'ok'        => true,
                'connected' => true,
                'message'   => 'Connected successfully to ' . DB_NAME,
                'error'     => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'        => false,
                'connected' => false,
                'message'   => null,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve absolute path to a CA file (supports absolute and relative to project root).
     */
    public static function resolveCaPath(string $caPath): ?string
    {
        $trimmed = trim($caPath);
        if ($trimmed === '') {
            return null;
        }
        if (file_exists($trimmed)) {
            return $trimmed;
        }
        if (defined('BASE_ROOT')) {
            $relative = BASE_ROOT . '/' . ltrim($trimmed, '/\\');
            if (file_exists($relative)) {
                return $relative;
            }
        }
        return null;
    }

    /**
     * Resolve PDO MySQL SSL configuration options based on DB_SSL_MODE and DB_SSL_CA.
     *
     * @param string|null $sslMode 'require' | 'verify' | 'disable' (defaults to DB_SSL_MODE)
     * @param string|null $sslCa   Path to CA file (defaults to DB_SSL_CA)
     * @return array PDO connection options array
     * @throws PDOException When 'verify' mode is active but CA file is missing
     */
    public static function resolveSslOptions(?string $sslMode = null, ?string $sslCa = null): array
    {
        $mode = strtolower(trim($sslMode ?? (defined('DB_SSL_MODE') ? DB_SSL_MODE : 'disable')));
        $ca   = trim($sslCa ?? (defined('DB_SSL_CA') ? DB_SSL_CA : ''));
        $options = [];

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

        // 'disable' or unconfigured: connect without SSL options
        return [];
    }

    /** Prevent cloning / serialization of the singleton. */
    private function __construct() {}
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize a singleton.');
    }
}

if (!function_exists('db')) {
    /**
     * Convenience wrapper — returns the shared PDO instance.
     * Usage: $pdo = db();  then  $pdo->prepare(...)->execute([...]);
     */
    function db(): PDO
    {
        return Database::getInstance();
    }
}
