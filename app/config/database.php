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

            // Enable SSL if CA path is provided (required for Aiven / Cloud MySQL)
            if (!empty(DB_SSL_CA)) {
                $caPath = DB_SSL_CA;
                // Support relative path from project root
                if (!file_exists($caPath) && file_exists(BASE_ROOT . '/' . ltrim($caPath, '/'))) {
                    $caPath = BASE_ROOT . '/' . ltrim($caPath, '/');
                }
                
                if (file_exists($caPath)) {
                    if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
                        $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                    }
                } else {
                    error_log('[DB WARNING] DB_SSL_CA file not found at: ' . DB_SSL_CA);
                }
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
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

    /** Prevent cloning / serialization of the singleton. */
    private function __construct() {}
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize a singleton.');
    }
}

/**
 * Convenience wrapper — returns the shared PDO instance.
 * Usage: $pdo = db();  then  $pdo->prepare(...)->execute([...]);
 */
function db(): PDO
{
    return Database::getInstance();
}
