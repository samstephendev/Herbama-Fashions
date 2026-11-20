<?php
/**
 * BannerModel — Herbama Fashions
 * ================================
 * Fetches promotional hero slides and marketing banners.
 */

declare(strict_types=1);

class BannerModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Retrieve all active hero carousel banners ordered by sort_order.
     */
    public function getActiveBanners(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM banners
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute();
            $banners = $stmt->fetchAll();

            if (!empty($banners)) {
                return $banners;
            }
        } catch (PDOException $e) {
            error_log('[BannerModel] getActiveBanners failed: ' . $e->getMessage());
        }

        // Fallback default banners if DB has none yet
        return [
            [
                'id'          => 1,
                'title'       => 'Adorable Styles for Little Princesses',
                'subtitle'    => 'Handcrafted party frocks, festive lehengas & everyday dresses tailored with love.',
                'badge_text'  => 'NEW ARRIVALS 2024',
                'button_text' => 'Shop Girls Collection',
                'button_url'  => '/collection.php?cat=girls',
                'image_url'   => '/assets/images/banner1.svg',
                'bg_color'    => '#FAF4EB',
            ],
            [
                'id'          => 2,
                'title'       => 'Trendy & Tough Styles for Active Boys',
                'subtitle'    => 'Breathable cotton shirts, dungarees and shorts built for playtime adventures.',
                'badge_text'  => 'BOYS ESSENTIALS',
                'button_text' => 'Shop Boys Collection',
                'button_url'  => '/collection.php?cat=boys',
                'image_url'   => '/assets/images/banner2.svg',
                'bg_color'    => '#F5EFEB',
            ],
            [
                'id'          => 3,
                'title'       => 'Royal Festive Wear for Every Celebration',
                'subtitle'    => 'Exquisite silk kurtas, zari shararas and traditional ethnic outfits for festive days.',
                'badge_text'  => 'FESTIVE COLLECTION',
                'button_text' => 'Explore Festive Edit',
                'button_url'  => '/collection.php?cat=festive',
                'image_url'   => '/assets/images/banner3.svg',
                'bg_color'    => '#FDF8F0',
            ],
        ];
    }

    /** Admin: list all banners. */
    public function adminList(): array
    {
        $stmt = $this->db->query('SELECT * FROM banners ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll();
    }

    /** Admin: create a banner. */
    public function createBanner(array $data, int $adminId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO banners (title, subtitle, badge_text, button_text, button_url, image_url, bg_color, sort_order, is_active)
             VALUES (:title, :sub, :badge, :btn_txt, :btn_url, :img, :bg, :sort, :active)'
        );
        $stmt->execute([
            ':title'   => $data['title'],
            ':sub'     => $data['subtitle'] ?? null,
            ':badge'   => $data['badge_text'] ?? null,
            ':btn_txt' => $data['button_text'] ?? 'Shop Now',
            ':btn_url' => $data['button_url'] ?? '/collection.php',
            ':img'     => $data['image_url'],
            ':bg'      => $data['bg_color'] ?? '#FAF4EB',
            ':sort'    => (int)($data['sort_order'] ?? 0),
            ':active'  => (int)($data['is_active'] ?? 1),
        ]);
        $id = (int) $this->db->lastInsertId();
        db_audit_log('BANNER_CREATED', ['banner_id' => $id, 'title' => $data['title']], $adminId, 'banners', $id);
        return $id;
    }

    /** Admin: update a banner. */
    public function updateBanner(int $id, array $data, int $adminId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE banners 
             SET title = :title, subtitle = :sub, badge_text = :badge, button_text = :btn_txt,
                 button_url = :btn_url, image_url = :img, bg_color = :bg, sort_order = :sort, is_active = :active
             WHERE id = :id'
        );
        $ok = $stmt->execute([
            ':title'   => $data['title'],
            ':sub'     => $data['subtitle'] ?? null,
            ':badge'   => $data['badge_text'] ?? null,
            ':btn_txt' => $data['button_text'] ?? 'Shop Now',
            ':btn_url' => $data['button_url'] ?? '/collection.php',
            ':img'     => $data['image_url'],
            ':bg'      => $data['bg_color'] ?? '#FAF4EB',
            ':sort'    => (int)($data['sort_order'] ?? 0),
            ':active'  => (int)($data['is_active'] ?? 1),
            ':id'      => $id,
        ]);
        if ($ok) {
            db_audit_log('BANNER_UPDATED', ['banner_id' => $id], $adminId, 'banners', $id);
        }
        return $ok;
    }

    /** Admin: delete a banner. */
    public function deleteBanner(int $id, int $adminId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM banners WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('BANNER_DELETED', ['banner_id' => $id], $adminId, 'banners', $id);
        }
        return $ok;
    }
}
