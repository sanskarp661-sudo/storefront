<?php
declare(strict_types=1);

const PRODUCT_COLUMNS = 'sku, name, description, category, brand, image_url, unit, price, currency, available_quantity';

function product_from_row(array $r): array
{
    return [
        'sku' => $r['sku'],
        'name' => $r['name'],
        'description' => $r['description'],
        'category' => $r['category'],
        'brand' => $r['brand'],
        'image_url' => $r['image_url'],
        'unit' => $r['unit'],
        'price' => (float) $r['price'],
        'currency' => $r['currency'],
        'available' => (float) $r['available_quantity'],
    ];
}

const PRODUCT_SORTS = [
    'featured' => '(available_quantity > 0) DESC, erp_updated_at DESC, name',
    'newest' => 'erp_updated_at DESC, name',
    'price-asc' => 'price ASC, name',
    'price-desc' => 'price DESC, name',
    'name' => 'name ASC',
];

/**
 * Catalog search. $q matches name / SKU / brand / category (every word must match).
 * Returns ['products' => [...], 'total' => n, 'page' => n, 'page_count' => n].
 */
function search_products(array $opts): array
{
    $perPage = max(1, min(96, (int) ($opts['per_page'] ?? 24)));
    $page = max(1, (int) ($opts['page'] ?? 1));
    $where = ["status = 'active'"];
    $params = [];

    $terms = array_slice(preg_split('/\s+/', mb_strtolower(trim((string) ($opts['q'] ?? ''))), -1, PREG_SPLIT_NO_EMPTY), 0, 8);
    foreach ($terms as $i => $t) {
        $where[] = "search_text LIKE :t$i";
        $params["t$i"] = '%' . addcslashes($t, '\\%_') . '%';
    }
    if (!empty($opts['category'])) {
        $where[] = 'category = :category';
        $params['category'] = $opts['category'];
    }
    if (!empty($opts['brand'])) {
        $where[] = 'brand = :brand';
        $params['brand'] = $opts['brand'];
    }
    if (!empty($opts['in_stock'])) $where[] = 'available_quantity > 0';

    $whereSql = implode(' AND ', $where);
    $order = PRODUCT_SORTS[$opts['sort'] ?? 'featured'] ?? PRODUCT_SORTS['featured'];
    $offset = ($page - 1) * $perPage;

    $rows = db_all("SELECT " . PRODUCT_COLUMNS . " FROM products WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset", $params);
    $total = (int) db_one("SELECT COUNT(*) AS n FROM products WHERE $whereSql", $params)['n'];

    return [
        'products' => array_map('product_from_row', $rows),
        'total' => $total,
        'page' => $page,
        'page_count' => max(1, (int) ceil($total / $perPage)),
    ];
}

function get_product(string $sku): ?array
{
    $row = db_one("SELECT " . PRODUCT_COLUMNS . " FROM products WHERE sku = ? AND status = 'active' ORDER BY erp_updated_at DESC LIMIT 1", [$sku]);
    return $row ? product_from_row($row) : null;
}

/** Active products for a list of SKUs, keyed by SKU. */
function get_products_by_skus(array $skus): array
{
    $skus = array_values(array_unique(array_map('strval', $skus)));
    if (!$skus) return [];
    $placeholders = implode(',', array_fill(0, count($skus), '?'));
    $out = [];
    foreach (db_all("SELECT " . PRODUCT_COLUMNS . " FROM products WHERE status = 'active' AND sku IN ($placeholders) ORDER BY erp_updated_at", $skus) as $r) {
        $out[$r['sku']] = product_from_row($r); // newest row wins if a SKU appears twice
    }
    return $out;
}

function get_related_products(array $product, int $limit = 4): array
{
    if (!$product['category']) return [];
    $rows = db_all(
        "SELECT " . PRODUCT_COLUMNS . " FROM products WHERE status = 'active' AND category = ? AND sku <> ?
         ORDER BY (available_quantity > 0) DESC, erp_updated_at DESC LIMIT " . (int) $limit,
        [$product['category'], $product['sku']]
    );
    return array_map('product_from_row', $rows);
}

/** ERP categories that currently have active products, with a count and a sample image. */
function get_categories(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $rows = db_all(
        "SELECT c.name, COUNT(p.erp_id) AS n,
                (SELECT p2.image_url FROM products p2
                  WHERE p2.category = c.name AND p2.status = 'active' AND p2.image_url IS NOT NULL
                  ORDER BY p2.available_quantity > 0 DESC, p2.erp_updated_at DESC LIMIT 1) AS image_url
           FROM categories c
           JOIN products p ON p.category = c.name AND p.status = 'active'
          GROUP BY c.name
          ORDER BY n DESC, c.name"
    );
    return $cache = array_map(fn($r) => ['name' => $r['name'], 'count' => (int) $r['n'], 'image_url' => $r['image_url']], $rows);
}

/** Category names for navigation; never breaks the page if the database is unavailable. */
function nav_categories(): array
{
    try {
        return array_column(get_categories(), 'name');
    } catch (Throwable $e) {
        error_log('[storefront] nav categories failed: ' . $e->getMessage());
        return [];
    }
}

function get_brands(?string $category): array
{
    $sql = "SELECT DISTINCT brand FROM products WHERE status = 'active' AND brand IS NOT NULL AND brand <> ''";
    $params = [];
    if ($category) {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }
    return array_column(db_all($sql . ' ORDER BY brand', $params), 'brand');
}
