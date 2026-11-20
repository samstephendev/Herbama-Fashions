<?php
/**
 * Audit Log Helper
 * ================
 * Records admin and sensitive user actions to a private log file.
 * Logs are NEVER accessible via HTTP (file lives outside public_html).
 */

declare(strict_types=1);

/**
 * Write an entry to the audit log.
 *
 * @param string   $action   Short action name, e.g. 'ORDER_STATUS_CHANGED'
 * @param array    $context  Arbitrary context data (will be JSON-encoded)
 * @param int|null $actorId  User ID performing the action (null = unauthenticated)
 */
function audit_log(string $action, array $context = [], ?int $actorId = null): void
{
    if ($actorId === null) {
        $actorId = Session::userId();
    }

    $entry = [
        'ts'       => date('c'),                           // ISO 8601
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'actor_id' => $actorId,
        'action'   => strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $action)),
        'context'  => $context,
        'uri'      => $_SERVER['REQUEST_URI'] ?? '',
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    // Ensure the log directory exists
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0750, true);
    }

    // Append-only write; suppress error (don't crash the app if log write fails)
    @file_put_contents(AUDIT_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Also write to the audit log via the database (audit_log table).
 * This version is used when a DB connection is available.
 */
function db_audit_log(string $action, array $context = [], ?int $actorId = null, ?string $entity = null, ?int $entityId = null): void
{
    // Write to file first (always works even if DB is down)
    audit_log($action, $context, $actorId);

    if ($actorId === null) {
        $actorId = Session::userId();
    }

    try {
        $pdo  = db();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (admin_id, action, entity, entity_id, details, ip_address, created_at)
             VALUES (:admin_id, :action, :entity, :entity_id, :details, :ip, NOW())'
        );
        $stmt->execute([
            ':admin_id'  => $actorId,
            ':action'    => strtoupper(substr($action, 0, 100)),
            ':entity'    => $entity ? substr($entity, 0, 60) : null,
            ':entity_id' => $entityId,
            ':details'   => json_encode($context, JSON_UNESCAPED_UNICODE),
            ':ip'        => substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
        ]);
    } catch (PDOException $e) {
        // DB audit failure is non-fatal; file log already captured the event
        error_log('[AUDIT] DB write failed: ' . $e->getMessage());
    }
}
