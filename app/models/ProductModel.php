<?php
/**
 * ProductModel — Herbama Fashions
 * =================================
 * Product listing, detail, variant, category, wishlist, and admin CRUD.
 * Never returns raw data for financial decisions — that's OrderModel's job.
 */

declare(strict_types=1);

class ProductModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Public catalogue
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Paginated product listing with rich filters (category, subcategory, age, size, price range).
     * Whitelists sort columns and calculates total stock quantity.
     *
     * @param array $filters Supported: category_slug, subcategory_slug, age, size, min_price, max_price, is_new, is_featured, is_offer, q (search), sort
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 24): array
    {
        $where  = ['p.is_active = 1'];
        $params = [];

        // Category filter (matches category OR its parent category)
        if (!empty($filters['category_slug'])) {
            $catSlug = trim($filters['category_slug']);
            // Allow matching "girls" -> "girls-wear", "boys" -> "boys-wear", etc.
            if ($catSlug === 'girls') $catSlug = 'girls-wear';
            elseif ($catSlug === 'boys') $catSlug = 'boys-wear';
            elseif ($catSlug === 'infant') $catSlug = 'infant-wear';
            elseif ($catSlug === 'festive') $catSlug = 'festive-wear';

            $where[] = '(c.slug = :cat_slug OR parent_c.slug = :parent_slug)';
            $params[':cat_slug']    = $catSlug;
            $params[':parent_slug'] = $catSlug;
        }

        // Subcategory filter
        if (!empty($filters['subcategory_slug'])) {
            $where[] = 'c.slug = :subcat_slug';
            $params[':subcat_slug'] = trim($filters['subcategory_slug']);
        }

        // Age / Size filters (checks variant attributes)
        if (!empty($filters['age'])) {
            $ages = is_array($filters['age']) ? $filters['age'] : [$filters['age']];
            $agePlaceholders = [];
            foreach ($ages as $idx => $ageVal) {
                $key = ':age_' . $idx;
                $agePlaceholders[] = $key;
                $params[$key] = trim((string)$ageVal);
            }
            if (!empty($agePlaceholders)) {
                $where[] = 'EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 AND pv.age_label IN (' . implode(',', $agePlaceholders) . '))';
            }
        }

        if (!empty($filters['size'])) {
            $sizes = is_array($filters['size']) ? $filters['size'] : [$filters['size']];
            $sizePlaceholders = [];
            foreach ($sizes as $idx => $sVal) {
                $key = ':size_' . $idx;
                $sizePlaceholders[] = $key;
                $params[$key] = trim((string)$sVal);
            }
            if (!empty($sizePlaceholders)) {
                $where[] = 'EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 AND pv.age_label IN (' . implode(',', $sizePlaceholders) . '))';
            }
        }

        // Price range filter
        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $where[] = 'p.base_price >= :min_price';
            $params[':min_price'] = (float)$filters['min_price'];
        }
        if (isset($filters['max_price']) && is_numeric($filters['max_price']) && (float)$filters['max_price'] > 0) {
            $where[] = 'p.base_price <= :max_price';
            $params[':max_price'] = (float)$filters['max_price'];
        }

        // Flag filters
        if (!empty($filters['is_new'])) {
            $where[] = 'p.is_new = 1';
        }
        if (!empty($filters['is_featured'])) {
            $where[] = 'p.is_featured = 1';
        }
        if (!empty($filters['is_offer'])) {
            $where[] = '(p.compare_price > p.base_price OR p.discount_pct > 0)';
        }

        // Search query
        if (!empty($filters['q'])) {
            $where[] = '(p.name LIKE :q OR p.short_desc LIKE :q2 OR p.material LIKE :q3 OR p.design LIKE :q4)';
            $like = '%' . trim($filters['q']) . '%';
            $params[':q']  = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }

        // Whitelisted Order By
        $orderBy = match ($filters['sort'] ?? 'newest') {
            'price_asc'  => 'p.base_price ASC',
            'price_desc' => 'p.base_price DESC',
            'name_asc'   => 'p.name ASC',
            'popular'    => 'p.is_featured DESC, p.created_at DESC',
            default      => 'p.created_at DESC',
        };

        $whereClause = implode(' AND ', $where);
        $offset      = max(0, ($page - 1) * $perPage);

        // Count for pagination
        $countStmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT p.id) FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN categories parent_c ON c.parent_id = parent_c.id
             WHERE {$whereClause}"
        );
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetchColumn();

        // Product rows with stock sum and primary image.
        // Correlated subquery for primary_image avoids a GROUP BY on a non-aggregate join column,
        // which would violate ONLY_FULL_GROUP_BY on strict MySQL 8 servers (e.g. Aiven).
        $stmt = $this->db->prepare(
            "SELECT p.id, p.name, p.slug, p.short_desc, p.base_price, p.compare_price,
                    p.discount_pct, p.is_new, p.is_featured, p.material, p.design,
                    c.name AS category_name, c.slug AS category_slug,
                    (SELECT pi.image_path FROM product_images pi
                     WHERE pi.product_id = p.id
                     ORDER BY pi.is_primary DESC, pi.position ASC, pi.id ASC LIMIT 1) AS primary_image,
                    COALESCE((SELECT SUM(pv.stock_qty) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1), 0) AS stock_total,
                    (SELECT GROUP_CONCAT(DISTINCT pv.age_label ORDER BY pv.age_label SEPARATOR ', ') FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) AS available_sizes
             FROM products p
             LEFT JOIN categories c  ON p.category_id = c.id
             LEFT JOIN categories parent_c ON c.parent_id = parent_c.id
             WHERE {$whereClause}
             ORDER BY {$orderBy}
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items'       => $stmt->fetchAll(),
            'total'       => $totalCount,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($totalCount / max(1, $perPage)),
        ];
    }

    /**
     * Get min and max price for active products in a category (for price range slider).
     */
    public function getPriceBounds(?string $categorySlug = null): array
    {
        $where = 'p.is_active = 1';
        $params = [];

        if (!empty($categorySlug)) {
            $cat = trim($categorySlug);
            if ($cat === 'girls') $cat = 'girls-wear';
            elseif ($cat === 'boys') $cat = 'boys-wear';
            elseif ($cat === 'infant') $cat = 'infant-wear';
            elseif ($cat === 'festive') $cat = 'festive-wear';

            $where .= ' AND (c.slug = :cat OR parent_c.slug = :pcat)';
            $params[':cat'] = $cat;
            $params[':pcat'] = $cat;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(MIN(p.base_price), 199.00) AS min_price,
                        COALESCE(MAX(p.base_price), 2999.00) AS max_price
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 LEFT JOIN categories parent_c ON c.parent_id = parent_c.id
                 WHERE {$where}"
            );
            $stmt->execute($params);
            $res = $stmt->fetch();
            return [
                'min' => (float)($res['min_price'] ?? 199),
                'max' => (float)($res['max_price'] ?? 2999),
            ];
        } catch (PDOException $e) {
            return ['min' => 199, 'max' => 2999];
        }
    }

    /**
     * Get subcategories for a given parent category slug.
     */
    public function getSubcategories(string $parentSlug): array
    {
        $slug = trim($parentSlug);
        if ($slug === 'girls') $slug = 'girls-wear';
        elseif ($slug === 'boys') $slug = 'boys-wear';
        elseif ($slug === 'infant') $slug = 'infant-wear';
        elseif ($slug === 'festive') $slug = 'festive-wear';

        try {
            $stmt = $this->db->prepare(
                'SELECT sub.*, COUNT(p.id) AS product_count
                 FROM categories sub
                 JOIN categories parent ON sub.parent_id = parent.id
                 LEFT JOIN products p ON p.category_id = sub.id AND p.is_active = 1
                 WHERE parent.slug = :pslug AND sub.is_active = 1
                 GROUP BY sub.id
                 ORDER BY sub.sort_order ASC, sub.name ASC'
            );
            $stmt->execute([':pslug' => $slug]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Related products carousel for "You May Also Like".
     */
    public function getRelatedProducts(int $productId, ?int $categoryId = null, int $limit = 4): array
    {
        try {
            $sql = 'SELECT p.id, p.name, p.slug, p.short_desc, p.base_price, p.compare_price,
                           p.discount_pct, p.is_new, p.is_featured,
                           c.name AS category_name, c.slug AS category_slug,
                           (SELECT pi.image_path FROM product_images pi
                            WHERE pi.product_id = p.id
                            ORDER BY pi.is_primary DESC, pi.position ASC, pi.id ASC LIMIT 1) AS primary_image,
                           COALESCE((SELECT SUM(pv.stock_qty) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1), 0) AS stock_total,
                           (SELECT GROUP_CONCAT(DISTINCT pv.age_label ORDER BY pv.age_label SEPARATOR \', \') FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) AS available_sizes
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.is_active = 1 AND p.id != :pid';
            $params = [':pid' => $productId];

            if ($categoryId) {
                $sql .= ' AND p.category_id = :cid';
                $params[':cid'] = $categoryId;
            }

            $sql .= ' ORDER BY p.is_featured DESC, p.created_at DESC LIMIT :limit';

            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get active promo offer banner data.
     */
    public function getActivePromo(?string $categorySlug = null): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM offers
                 WHERE is_active = 1
                   AND (starts_at IS NULL OR starts_at <= NOW())
                   AND (ends_at IS NULL OR ends_at >= NOW())
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute();
            $offer = $stmt->fetch();
            return $offer ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Full product detail by slug — includes all images and variants.
     * Returns null if not found or not active.
     */
    public function findBySlug(string $slug): ?array
    {
        // Core product row
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.slug = :slug AND p.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $product = $stmt->fetch();
        if (!$product) {
            return null;
        }

        // Images
        $imgStmt = $this->db->prepare(
            'SELECT image_path, alt_text, is_primary, position
             FROM product_images
             WHERE product_id = :id
             ORDER BY is_primary DESC, position ASC'
        );
        $imgStmt->execute([':id' => $product['id']]);
        $product['images'] = $imgStmt->fetchAll();

        // Variants (grouped for display)
        $varStmt = $this->db->prepare(
            'SELECT id, age_label, color, sku, stock_qty, is_active
             FROM product_variants
             WHERE product_id = :id AND is_active = 1
             ORDER BY age_label, color'
        );
        $varStmt->execute([':id' => $product['id']]);
        $product['variants'] = $varStmt->fetchAll();

        return $product;
    }

    /** Find product by ID (for internal use — includes inactive). */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get a single variant by ID with its product — for price/stock lookup.
     * This is the authoritative source for pricing in the cart and order flow.
     */
    public function getVariantWithProduct(int $variantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT pv.id AS variant_id, pv.product_id, pv.age_label, pv.color, pv.sku, pv.stock_qty,
                    p.name AS product_name, p.slug AS product_slug,
                    p.base_price, p.compare_price, p.is_active AS product_active,
                    pv.is_active AS variant_active
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             WHERE pv.id = :vid AND pv.is_active = 1 AND p.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':vid' => $variantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Categories
    // ══════════════════════════════════════════════════════════════════════════

    /** Get all active top-level categories with child counts. */
    public function getCategories(bool $includeInactive = false): array
    {
        $where = $includeInactive ? '' : 'WHERE c.is_active = 1';
        $stmt  = $this->db->prepare(
            "SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
             {$where}
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Find category by slug. */
    public function findCategoryBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Wishlist (IDOR-safe)
    // ══════════════════════════════════════════════════════════════════════════

    /** Get wishlist product IDs for a user. */
    public function getWishlistIds(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT product_id FROM wishlist WHERE user_id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        return array_column($stmt->fetchAll(), 'product_id');
    }

    /** Get full wishlist products for a user. */
    public function getWishlist(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.name, p.slug, p.base_price, p.compare_price,
                    (SELECT pi.image_path FROM product_images pi
                     WHERE pi.product_id = p.id
                     ORDER BY pi.is_primary DESC, pi.position ASC, pi.id ASC LIMIT 1) AS primary_image,
                    w.created_at AS wishlisted_at
             FROM wishlist w
             JOIN products p ON w.product_id = p.id AND p.is_active = 1
             WHERE w.user_id = :uid
             ORDER BY w.created_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** Toggle wishlist (add if not present, remove if present). Returns 'added' or 'removed'. */
    public function toggleWishlist(int $userId, int $productId): string
    {
        // Check product exists and is active (prevent IDOR on product_id)
        $check = $this->db->prepare(
            'SELECT id FROM products WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $check->execute([':id' => $productId]);
        if (!$check->fetch()) {
            throw new \InvalidArgumentException('Product not found.');
        }

        $exists = $this->db->prepare(
            'SELECT id FROM wishlist WHERE user_id = :uid AND product_id = :pid LIMIT 1'
        );
        $exists->execute([':uid' => $userId, ':pid' => $productId]);

        if ($exists->fetch()) {
            $this->db->prepare(
                'DELETE FROM wishlist WHERE user_id = :uid AND product_id = :pid'
            )->execute([':uid' => $userId, ':pid' => $productId]);
            return 'removed';
        }

        $this->db->prepare(
            'INSERT INTO wishlist (user_id, product_id) VALUES (:uid, :pid)'
        )->execute([':uid' => $userId, ':pid' => $productId]);
        return 'added';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Admin CRUD
    // ══════════════════════════════════════════════════════════════════════════

    /** Admin: paginated product list (includes inactive). */
    public function adminList(int $page = 1, int $perPage = 30, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;
        $like   = '%' . $search . '%';
        $hasSearch = $search !== '';

        $stmt = $this->db->prepare(
            'SELECT p.id, p.name, p.slug, p.base_price, p.is_active, p.is_new,
                    p.stock_total, c.name AS category_name, p.created_at
             FROM (
               SELECT p.*, COALESCE(SUM(pv.stock_qty),0) AS stock_total
               FROM products p
               LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
               WHERE (:s = 0 OR p.name LIKE :name)
               GROUP BY p.id
             ) p
             LEFT JOIN categories c ON p.category_id = c.id
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':s',      (int) $hasSearch, PDO::PARAM_INT);
        $stmt->bindValue(':name',   $like);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Admin: create a new product. Returns new product ID. */
    public function adminCreate(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products
             (category_id, name, slug, description, short_desc, base_price, compare_price,
              discount_pct, material, design, neck, sleeves, closure, is_new, is_featured,
              is_active, meta_title, meta_desc, sort_order)
             VALUES
             (:cat, :name, :slug, :desc, :short, :price, :compare,
              :disc, :mat, :design, :neck, :sleeves, :closure, :new, :feat,
              :active, :mtitle, :mdesc, :sort)'
        );
        $stmt->execute([
            ':cat'     => $data['category_id'] ?? null,
            ':name'    => $data['name'],
            ':slug'    => $data['slug'],
            ':desc'    => $data['description'] ?? null,
            ':short'   => $data['short_desc'] ?? null,
            ':price'   => $data['base_price'],
            ':compare' => $data['compare_price'] ?? null,
            ':disc'    => $data['discount_pct'] ?? null,
            ':mat'     => $data['material'] ?? null,
            ':design'  => $data['design'] ?? null,
            ':neck'    => $data['neck'] ?? null,
            ':sleeves' => $data['sleeves'] ?? null,
            ':closure' => $data['closure'] ?? null,
            ':new'     => (int) ($data['is_new'] ?? 1),
            ':feat'    => (int) ($data['is_featured'] ?? 0),
            ':active'  => (int) ($data['is_active'] ?? 1),
            ':mtitle'  => $data['meta_title'] ?? null,
            ':mdesc'   => $data['meta_desc'] ?? null,
            ':sort'    => $data['sort_order'] ?? 0,
        ]);
        $id = (int) $this->db->lastInsertId();
        db_audit_log('PRODUCT_CREATED', ['product_id' => $id, 'name' => $data['name']]);
        return $id;
    }

    /** Admin: update a product. */
    public function adminUpdate(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products SET
             category_id=:cat, name=:name, slug=:slug, description=:desc, short_desc=:short,
             base_price=:price, compare_price=:compare, discount_pct=:disc,
             material=:mat, design=:design, neck=:neck, sleeves=:sleeves, closure=:closure,
             is_new=:new, is_featured=:feat, is_active=:active,
             meta_title=:mtitle, meta_desc=:mdesc, sort_order=:sort, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':cat'     => $data['category_id'] ?? null,
            ':name'    => $data['name'],
            ':slug'    => $data['slug'],
            ':desc'    => $data['description'] ?? null,
            ':short'   => $data['short_desc'] ?? null,
            ':price'   => $data['base_price'],
            ':compare' => $data['compare_price'] ?? null,
            ':disc'    => $data['discount_pct'] ?? null,
            ':mat'     => $data['material'] ?? null,
            ':design'  => $data['design'] ?? null,
            ':neck'    => $data['neck'] ?? null,
            ':sleeves' => $data['sleeves'] ?? null,
            ':closure' => $data['closure'] ?? null,
            ':new'     => (int) ($data['is_new'] ?? 0),
            ':feat'    => (int) ($data['is_featured'] ?? 0),
            ':active'  => (int) ($data['is_active'] ?? 1),
            ':mtitle'  => $data['meta_title'] ?? null,
            ':mdesc'   => $data['meta_desc'] ?? null,
            ':sort'    => $data['sort_order'] ?? 0,
            ':id'      => $id,
        ]);
        db_audit_log('PRODUCT_UPDATED', ['product_id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Admin: add a variant to a product. */
    public function addVariant(int $productId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO product_variants (product_id, age_label, color, sku, stock_qty)
             VALUES (:pid, :age, :color, :sku, :stock)'
        );
        $stmt->execute([
            ':pid'   => $productId,
            ':age'   => $data['age_label'],
            ':color' => $data['color'] ?? null,
            ':sku'   => $data['sku'] ?? null,
            ':stock' => max(0, (int) ($data['stock_qty'] ?? 0)),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Admin: adjust stock quantity for a variant (delta-based). */
    public function adjustStock(int $variantId, int $delta, int $adminId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_variants
             SET stock_qty = GREATEST(0, stock_qty + :delta)
             WHERE id = :id'
        );
        $stmt->execute([':delta' => $delta, ':id' => $variantId]);
        db_audit_log('STOCK_ADJUSTED', ['variant_id' => $variantId, 'delta' => $delta], $adminId);
        return $stmt->rowCount() > 0;
    }

    /** Admin: add a product image. */
    public function addImage(int $productId, string $imagePath, ?string $altText = null, bool $isPrimary = false): int
    {
        if ($isPrimary) {
            // Demote existing primary
            $this->db->prepare(
                'UPDATE product_images SET is_primary=0 WHERE product_id=:pid'
            )->execute([':pid' => $productId]);
        }

        // Set position to max+1
        $pos = $this->db->prepare(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM product_images WHERE product_id=:pid'
        );
        $pos->execute([':pid' => $productId]);
        $position = (int) $pos->fetchColumn();

        $stmt = $this->db->prepare(
            'INSERT INTO product_images (product_id, image_path, alt_text, is_primary, position)
             VALUES (:pid, :path, :alt, :primary, :pos)'
        );
        $stmt->execute([
            ':pid'     => $productId,
            ':path'    => $imagePath,
            ':alt'     => $altText,
            ':primary' => (int) $isPrimary,
            ':pos'     => $position,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Admin: delete a product. */
    public function deleteProduct(int $id, int $adminId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('PRODUCT_DELETED', ['product_id' => $id], $adminId, 'products', $id);
        }
        return $ok;
    }

    /** Admin: toggle active/inactive status. */
    public function toggleProductActive(int $id, int $adminId): bool
    {
        $stmt = $this->db->prepare('UPDATE products SET is_active = IF(is_active=1, 0, 1), updated_at=NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('PRODUCT_STATUS_TOGGLED', ['product_id' => $id], $adminId, 'products', $id);
        }
        return $ok;
    }

    /** Admin: get all images for a product. */
    public function getProductImages(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_images WHERE product_id = :pid ORDER BY is_primary DESC, position ASC, id ASC');
        $stmt->execute([':pid' => $productId]);
        return $stmt->fetchAll();
    }

    /** Admin: delete product image. */
    public function deleteImage(int $imageId, int $adminId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product_images WHERE id = :id');
        $stmt->execute([':id' => $imageId]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('PRODUCT_IMAGE_DELETED', ['image_id' => $imageId], $adminId, 'product_images', $imageId);
        }
        return $ok;
    }

    /** Admin: set primary image. */
    public function setPrimaryImage(int $imageId, int $productId, int $adminId): bool
    {
        $this->db->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :pid')->execute([':pid' => $productId]);
        $stmt = $this->db->prepare('UPDATE product_images SET is_primary = 1 WHERE id = :id AND product_id = :pid');
        $stmt->execute([':id' => $imageId, ':pid' => $productId]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('PRODUCT_PRIMARY_IMAGE_SET', ['image_id' => $imageId, 'product_id' => $productId], $adminId, 'product_images', $imageId);
        }
        return $ok;
    }

    /** Admin: get all variants for a product. */
    public function getProductVariants(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_variants WHERE product_id = :pid ORDER BY id ASC');
        $stmt->execute([':pid' => $productId]);
        return $stmt->fetchAll();
    }

    /** Admin: update variant. */
    public function updateVariant(int $variantId, array $data, int $adminId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_variants 
             SET age_label = :age, color = :color, sku = :sku, stock_qty = :stock, is_active = :active
             WHERE id = :id'
        );
        $ok = $stmt->execute([
            ':age'    => $data['age_label'],
            ':color'  => $data['color'] ?? null,
            ':sku'    => $data['sku'] ?? null,
            ':stock'  => max(0, (int)($data['stock_qty'] ?? 0)),
            ':active' => (int)($data['is_active'] ?? 1),
            ':id'     => $variantId,
        ]);
        if ($ok) {
            db_audit_log('VARIANT_UPDATED', ['variant_id' => $variantId, 'data' => $data], $adminId, 'product_variants', $variantId);
        }
        return $ok;
    }

    /** Admin: delete variant. */
    public function deleteVariant(int $variantId, int $adminId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product_variants WHERE id = :id');
        $stmt->execute([':id' => $variantId]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('VARIANT_DELETED', ['variant_id' => $variantId], $adminId, 'product_variants', $variantId);
        }
        return $ok;
    }

    /** Admin: set absolute variant stock with reason. */
    public function setVariantStock(int $variantId, int $newStock, int $adminId, string $reason = ''): bool
    {
        $stmt = $this->db->prepare('UPDATE product_variants SET stock_qty = :stock WHERE id = :id');
        $stmt->execute([':stock' => max(0, $newStock), ':id' => $variantId]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            db_audit_log('STOCK_OVERRIDDEN', ['variant_id' => $variantId, 'new_stock' => $newStock, 'reason' => $reason], $adminId, 'product_variants', $variantId);
        }
        return $ok;
    }

    /** Admin: get all variants with low stock across products. */
    public function getLowStockVariants(int $threshold = 5, ?bool $outOfStockOnly = false): array
    {
        $sql = 'SELECT pv.*, p.name AS product_name, p.slug AS product_slug, c.name AS category_name
                FROM product_variants pv
                JOIN products p ON pv.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1 ';
        if ($outOfStockOnly) {
            $sql .= 'AND pv.stock_qty = 0 ';
        } else {
            $sql .= 'AND pv.stock_qty <= :thresh ';
        }
        $sql .= 'ORDER BY pv.stock_qty ASC, p.name ASC';

        $stmt = $this->db->prepare($sql);
        if (!$outOfStockOnly) {
            $stmt->bindValue(':thresh', $threshold, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
