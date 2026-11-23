<?php
/**
 * SettingModel — Heramba Fashion
 * =================================
 * Manages site-wide key-value settings stored in `settings` table.
 * Caches settings in memory for the request duration.
 */

declare(strict_types=1);

class SettingModel
{
    private PDO $db;
    private static ?array $cache = null;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Retrieve all settings as associative array [key => value].
     */
    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $stmt = $this->db->query('SELECT setting_key, setting_value, setting_type FROM settings');
            $rows = $stmt->fetchAll();
            $settings = [];

            foreach ($rows as $row) {
                $val = $row['setting_value'];
                $val = match ($row['setting_type']) {
                    'integer' => (int) $val,
                    'boolean' => (bool) $val,
                    'json'    => json_decode($val ?? '', true),
                    default   => $val,
                };
                $settings[$row['setting_key']] = $val;
            }

            self::$cache = $settings;
            return self::$cache;
        } catch (PDOException $e) {
            error_log('[SettingModel] all() query failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve single setting value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    /**
     * Update or insert a setting value (admin only).
     */
    public function set(string $key, string $value, ?int $adminId = null): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (:key, :val, NOW())
             ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = NOW()'
        );
        $res = $stmt->execute([
            ':key'  => $key,
            ':val'  => $value,
            ':val2' => $value,
        ]);

        self::$cache = null; // Invalidate cache

        if ($adminId !== null) {
            db_audit_log('SETTING_UPDATED', ['key' => $key, 'value' => $value], $adminId, 'settings');
        }

        return $res;
    }

    /**
     * Calculate shipping charge based on destination country and subtotal.
     */
    public function calculateShipping(string $country, float $subtotal): float
    {
        $countryLower = strtolower(trim($country));
        $isIndia = ($countryLower === 'india' || empty($countryLower));

        if ($isIndia) {
            $freeThreshold = (float) $this->get('free_shipping_threshold_india', 999.00);
            if ($subtotal >= $freeThreshold) {
                return 0.00;
            }
            return (float) $this->get('shipping_india', 99.00);
        }

        // UAE / Dubai or other international destination
        return (float) $this->get('shipping_uae', 1499.00);
    }
}
