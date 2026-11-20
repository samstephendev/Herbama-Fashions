<?php
/**
 * OfferModel — Herbama Fashions
 * ==============================
 * Manages promotional offers, discount codes, and admin CRUD.
 */

declare(strict_types=1);

class OfferModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /** Admin: list all offers. */
    public function adminList(): array
    {
        $stmt = $this->db->query('SELECT * FROM offers ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    /** Find by ID. */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM offers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Admin: create an offer. */
    public function createOffer(array $data, int $adminId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO offers 
             (title, code, type, value, min_order_value, max_discount, applies_to, applies_to_id, starts_at, ends_at, is_active)
             VALUES (:title, :code, :type, :val, :min, :max, :app, :app_id, :starts, :ends, :active)'
        );
        $stmt->execute([
            ':title'   => $data['title'],
            ':code'    => !empty($data['code']) ? strtoupper(trim($data['code'])) : null,
            ':type'    => $data['type'] ?? 'percent',
            ':val'     => (float)$data['value'],
            ':min'     => (float)($data['min_order_value'] ?? 0),
            ':max'     => !empty($data['max_discount']) ? (float)$data['max_discount'] : null,
            ':app'     => $data['applies_to'] ?? 'all',
            ':app_id'  => !empty($data['applies_to_id']) ? (int)$data['applies_to_id'] : null,
            ':starts'  => !empty($data['starts_at']) ? $data['starts_at'] : null,
            ':ends'    => !empty($data['ends_at']) ? $data['ends_at'] : null,
            ':active'  => (int)($data['is_active'] ?? 1),
        ]);
        $id = (int) $this->db->lastInsertId();
        db_audit_log('OFFER_CREATED', ['offer_id' => $id, 'title' => $data['title']], $adminId, 'offers', $id);
        return $id;
    }

    /** Admin: update an offer. */
    public function updateOffer(int $id, array $data, int $adminId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE offers 
             SET title = :title, code = :code, type = :type, value = :val, min_order_value = :min,
                 max_discount = :max, applies_to = :app, applies_to_id = :app_id, starts_at = :starts,
                 ends_at = :ends, is_active = :active
             WHERE id = :id'
        );
        $ok = $stmt->execute([
            ':title'   => $data['title'],
            ':code'    => !empty($data['code']) ? strtoupper(trim($data['code'])) : null,
            ':type'    => $data['type'] ?? 'percent',
            ':val'     => (float)$data['value'],
            ':min'     => (float)($data['min_order_value'] ?? 0),
            ':max'     => !empty($data['max_discount']) ? (float)$data['max_discount'] : null,
            ':app'     => $data['applies_to'] ?? 'all',
            ':app_id'  => !empty($data['applies_to_id']) ? (int)$data['applies_to_id'] : null,
            ':starts'  => !empty($data['starts_at']) ? $data['starts_at'] : null,
            ':ends'    => !empty($data['ends_at']) ? $data['ends_at'] : null,
            ':active'  => (int)($data['is_active'] ?? 1),
            ':id'      => $id,
        ]);
        if ($ok) {
            db_audit_log('OFFER_UPDATED', ['offer_id' => $id], $adminId, 'offers', $id);
        }
        return $ok;
    }

    /** Admin: delete an offer. */
    public function deleteOffer(int $id, int $adminId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM offers WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('OFFER_DELETED', ['offer_id' => $id], $adminId, 'offers', $id);
        }
        return $ok;
    }
}
