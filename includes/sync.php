<?php
declare(strict_types=1);

/**
 * ERP → storefront catalog sync, run by cron/sync_products.php.
 *
 * - Incremental runs fetch products.php?since=<newest updated_at seen so far>.
 *   The cursor is the ERP's own timestamp, so server clocks never need to agree.
 * - Every FULL_SYNC_HOURS a full run walks every page and marks products the ERP
 *   no longer returns as inactive (an incremental fetch can't see deletions).
 * - A lease row prevents two cron runs from overlapping.
 */

const SYNC_NAME = 'catalog';
const FULL_SYNC_HOURS = 6;
const SYNC_LEASE_SECONDS = 600;
const SYNC_PER_PAGE = 200;
const SYNC_MAX_PAGES = 1000;

/** Upserts ERP product records into the local products table. */
function upsert_products(array $products, ?string $syncedAt = null): void
{
    if (!$products) return;
    $syncedAt ??= db_now();

    foreach (array_chunk($products, 100) as $chunk) {
        $values = [];
        $params = [];
        foreach ($chunk as $p) {
            $values[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            array_push(
                $params,
                (int) $p['id'],
                (string) $p['sku'],
                (string) $p['name'],
                $p['description'] ?? null,
                $p['category'] ?? null,
                $p['brand'] ?? null,
                $p['image_url'] ?? null,
                $p['unit'] ?? null,
                (float) ($p['price'] ?? 0),
                ($p['currency'] ?? '') ?: 'INR',
                (float) ($p['quantity_on_hand'] ?? 0),
                max(0, (float) ($p['available_quantity'] ?? 0)),
                ($p['status'] ?? '') ?: 'active',
                db_datetime($p['updated_at'] ?? null),
                $syncedAt,
                mb_strtolower(implode(' ', array_filter([$p['name'] ?? '', $p['sku'] ?? '', $p['brand'] ?? '', $p['category'] ?? ''])))
            );
        }
        db_query(
            'INSERT INTO products (erp_id, sku, name, description, category, brand, image_url, unit, price, currency,
                                   quantity_on_hand, available_quantity, status, erp_updated_at, synced_at, search_text)
             VALUES ' . implode(',', $values) . '
             ON DUPLICATE KEY UPDATE
               sku = VALUES(sku), name = VALUES(name), description = VALUES(description), category = VALUES(category),
               brand = VALUES(brand), image_url = VALUES(image_url), unit = VALUES(unit), price = VALUES(price),
               currency = VALUES(currency), quantity_on_hand = VALUES(quantity_on_hand),
               available_quantity = VALUES(available_quantity), status = VALUES(status),
               erp_updated_at = VALUES(erp_updated_at), synced_at = VALUES(synced_at), search_text = VALUES(search_text)',
            $params
        );
    }
}

function sync_categories(): int
{
    $categories = erp_list_categories();
    $now = db_now();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ids = [];
        foreach ($categories as $c) {
            db_query(
                'INSERT INTO categories (erp_id, name, synced_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), synced_at = VALUES(synced_at)',
                [(int) $c['id'], (string) $c['name'], $now]
            );
            $ids[] = (int) $c['id'];
        }
        if ($ids) {
            db_query('DELETE FROM categories WHERE erp_id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        } else {
            db_query('DELETE FROM categories');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return count($categories);
}

/** Takes the sync lease; returns the sync_state row, or null if another run holds it. */
function acquire_sync_lease(): ?array
{
    db_query('INSERT IGNORE INTO sync_state (name) VALUES (?)', [SYNC_NAME]);
    $taken = db_query(
        'UPDATE sync_state SET lease_until = UTC_TIMESTAMP(3) + INTERVAL ' . SYNC_LEASE_SECONDS . ' SECOND
          WHERE name = ? AND (lease_until IS NULL OR lease_until < UTC_TIMESTAMP(3))',
        [SYNC_NAME]
    )->rowCount();
    return $taken === 1 ? db_one('SELECT * FROM sync_state WHERE name = ?', [SYNC_NAME]) : null;
}

/**
 * Runs a sync. $mode: 'auto' (full every FULL_SYNC_HOURS, else incremental), 'full' or 'incremental'.
 * Returns a summary array; throws on ERP/database errors (after recording them).
 */
function sync_catalog(string $mode = 'auto'): array
{
    $state = acquire_sync_lease();
    if ($state === null) return ['skipped' => true, 'reason' => 'another sync is running'];

    $runStartedAt = db_now();
    $full = $mode === 'full'
        || empty($state['cursor_value'])
        || ($mode === 'auto' && (empty($state['last_full_at'])
            || strtotime($state['last_full_at'] . ' UTC') < time() - FULL_SYNC_HOURS * 3600));

    try {
        $since = $full ? null : $state['cursor_value'];
        $cursor = $state['cursor_value'];
        $count = 0;

        for ($page = 1; $page <= SYNC_MAX_PAGES; $page++) {
            $res = erp_list_products($page, SYNC_PER_PAGE, $since);
            $products = $res['products'] ?? [];
            upsert_products($products, $runStartedAt);
            $count += count($products);
            foreach ($products as $p) {
                $u = $p['updated_at'] ?? null;
                if ($u && (!$cursor || strtotime($u) > strtotime($cursor))) $cursor = $u;
            }
            if (empty($res['has_more']) || !$products) break;
        }

        $deactivated = 0;
        if ($full) {
            $deactivated = db_query(
                "UPDATE products SET status = 'inactive' WHERE synced_at < ? AND status <> 'inactive'",
                [$runStartedAt]
            )->rowCount();
        }

        $categories = sync_categories();

        db_query(
            'UPDATE sync_state SET cursor_value = ?, last_run_at = ?, last_full_at = ?, last_error = NULL, lease_until = NULL WHERE name = ?',
            [$cursor, $runStartedAt, $full ? $runStartedAt : $state['last_full_at'], SYNC_NAME]
        );
        return [
            'skipped' => false,
            'mode' => $full ? 'full' : 'incremental',
            'products' => $count,
            'deactivated' => $deactivated,
            'categories' => $categories,
            'cursor' => $cursor,
        ];
    } catch (Throwable $e) {
        try {
            db_query('UPDATE sync_state SET last_error = ?, lease_until = NULL WHERE name = ?', [$e->getMessage(), SYNC_NAME]);
        } catch (Throwable $ignored) {
        }
        throw $e;
    }
}

function catalog_last_synced_at(): ?string
{
    try {
        return db_one('SELECT last_run_at FROM sync_state WHERE name = ?', [SYNC_NAME])['last_run_at'] ?? null;
    } catch (Throwable $e) {
        return null;
    }
}
