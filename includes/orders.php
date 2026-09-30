<?php
declare(strict_types=1);

const ORDER_STATUSES = ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'];
const TERMINAL_STATUSES = ['completed', 'cancelled'];
/** How long a status may go without a webhook before viewing the order polls the ERP. */
const POLL_ACTIVE_SECONDS = 120;
const POLL_TERMINAL_SECONDS = 3600;

function valid_status($s): ?string
{
    return in_array($s, ORDER_STATUSES, true) ? $s : null;
}

function generate_website_order_id(): string
{
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford base32: no I/L/O/U
    $id = '';
    foreach (str_split(random_bytes(10)) as $byte) $id .= $alphabet[ord($byte) % 32];
    return 'WEB-' . $id;
}

function load_order(int $id): ?array
{
    return db_one('SELECT * FROM orders WHERE id = ?', [$id]);
}

function load_order_items(int $orderId): array
{
    return db_all('SELECT sku, name, quantity, unit_price, image_url FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

// ---------------------------------------------------------------------------
// Placing orders

/**
 * Re-checks each cart line against the ERP with a live ?sku= lookup, so the
 * customer isn't sold stock that went in the last few minutes. If the ERP is
 * slow, falls back to the local copy (the ERP still validates SKUs on POST).
 *
 * Returns ['issues' => [sku => ['message', 'available']], 'lines' => [...]].
 */
function check_order_items(array $items): array
{
    $local = get_products_by_skus(array_keys($items));
    $issues = [];
    $lines = [];
    $fresh = [];
    $erpReachable = true;

    foreach ($items as $sku => $qty) {
        $sku = (string) $sku;
        $live = null;
        $liveOk = false;
        if ($erpReachable) {
            try {
                $live = erp_get_product($sku, 4);
                $liveOk = true;
                if ($live) $fresh[] = $live;
            } catch (Throwable $e) {
                $erpReachable = false; // don't wait on every line if the ERP is down
            }
        }

        $l = $local[$sku] ?? null;
        if ($liveOk) {
            $active = $live !== null && ($live['status'] ?? 'active') === 'active';
            $name = $live['name'] ?? $l['name'] ?? $sku;
            $price = (float) ($live['price'] ?? 0);
            $available = (float) ($live['available_quantity'] ?? 0);
            $image = $live['image_url'] ?? null;
        } else {
            $active = $l !== null;
            $name = $l['name'] ?? $sku;
            $price = $l['price'] ?? 0.0;
            $available = $l['available'] ?? 0.0;
            $image = $l['image_url'] ?? null;
        }

        if (!$active) {
            $issues[$sku] = ['message' => "$name is no longer available.", 'available' => 0];
        } elseif ($available < $qty) {
            $n = max(0, (int) floor($available));
            $issues[$sku] = ['message' => $n === 0 ? "$name is out of stock." : "Only $n of $name left in stock.", 'available' => $n];
        }
        $lines[] = ['sku' => $sku, 'name' => $name, 'quantity' => (int) $qty, 'unit_price' => $price, 'image_url' => $image];
    }

    if ($fresh) {
        try {
            upsert_products($fresh);
        } catch (Throwable $e) {
            error_log('[storefront] could not refresh products after stock check: ' . $e->getMessage());
        }
    }
    return ['issues' => $issues, 'lines' => $lines];
}

/** The ERP API has no payment field, so the payment method heads the order notes. */
function erp_notes(array $order): string
{
    $method = payment_method($order['payment_method']);
    $line = $method['erp_note'] ?? ('Payment: ' . $order['payment_method']);
    return $order['notes'] ? $line . "\n\n" . $order['notes'] : $line;
}

function mark_submitted(int $id, array $erp): void
{
    $total = isset($erp['total_amount']) ? (float) $erp['total_amount'] : null;
    db_query(
        "UPDATE orders SET submit_state = 'submitted', submit_error = NULL, erp_order_no = ?, status = ?,
                erp_total_amount = COALESCE(?, erp_total_amount),
                updated_at = UTC_TIMESTAMP(3)
          WHERE id = ?",
        [(string) $erp['order_no'], valid_status($erp['status'] ?? null) ?? 'pending', $total, $id]
    );
}

/** Stores a full order_status.php snapshot (status, delivery note, invoice). */
function apply_status_snapshot(int $id, array $s): void
{
    $dn = is_array($s['delivery_note'] ?? null) ? $s['delivery_note'] : null;
    $inv = is_array($s['invoice'] ?? null) ? $s['invoice'] : null;
    db_query(
        'UPDATE orders SET
            status = COALESCE(?, status),
            status_updated_at = UTC_TIMESTAMP(3),
            erp_order_no = COALESCE(erp_order_no, ?),
            erp_total_amount = COALESCE(?, erp_total_amount),
            order_date = COALESCE(?, order_date),
            dn_no = ?, dn_status = ?,
            invoice_no = ?, invoice_status = ?, invoice_amount_paid = ?, invoice_total = ?,
            last_polled_at = UTC_TIMESTAMP(3), updated_at = UTC_TIMESTAMP(3)
          WHERE id = ?',
        [
            valid_status($s['status'] ?? null),
            $s['order_no'] ?? null,
            isset($s['total_amount']) ? (float) $s['total_amount'] : null,
            !empty($s['order_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $s['order_date']) ? $s['order_date'] : null,
            $dn['dn_no'] ?? null,
            $dn['status'] ?? null,
            $inv['invoice_no'] ?? null,
            $inv['status'] ?? null,
            isset($inv['amount_paid']) ? (float) $inv['amount_paid'] : null,
            isset($inv['total']) ? (float) $inv['total'] : null,
            $id,
        ]
    );
}

/**
 * After an ambiguous ERP failure (timeout, 5xx) the order may or may not exist
 * there. Look it up by our own id before deciding anything, so a retry can
 * never create a duplicate. Returns 'submitted' | 'absent' | 'unreachable'.
 */
function reconcile_order(array $order): string
{
    try {
        $s = erp_order_status(['website_order_id' => $order['website_order_id']], 5);
    } catch (Throwable $e) {
        return 'unreachable';
    }
    if ($s === null) return 'absent';
    mark_submitted((int) $order['id'], $s);
    apply_status_snapshot((int) $order['id'], $s);
    return 'submitted';
}

function submit_order_to_erp(array $order): array
{
    $ok = ['ok' => true, 'order' => $order];
    $items = load_order_items((int) $order['id']);

    try {
        $res = erp_create_order([
            'website_order_id' => $order['website_order_id'],
            'customer' => [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'],
                'company' => $order['customer_company'],
            ],
            'shipping_address' => [
                'label' => $order['ship_label'],
                'address_line' => $order['ship_address_line'],
                'city' => $order['ship_city'],
                'state' => $order['ship_state'],
                'pincode' => $order['ship_pincode'],
                'country' => $order['ship_country'],
                'contact_person' => $order['customer_name'],
                'contact_phone' => $order['customer_phone'],
                'contact_email' => $order['customer_email'],
            ],
            'items' => array_map(fn($i) => ['sku' => $i['sku'], 'quantity' => (int) $i['quantity']], $items),
            'notes' => erp_notes($order),
        ]);
        mark_submitted((int) $order['id'], $res);
        return $ok;
    } catch (ErpHttpException $e) {
        if ($e->status === 400 || $e->status === 422) {
            db_query("UPDATE orders SET submit_state = 'rejected', submit_error = ?, updated_at = UTC_TIMESTAMP(3) WHERE id = ?", [$e->getMessage(), $order['id']]);
            return [
                'ok' => false,
                'rotate_key' => true,
                'error' => $e->status === 422
                    ? "One of the items in your cart can't be ordered right now: " . $e->getMessage()
                    : "We couldn't place your order: " . $e->getMessage(),
            ];
        }
        $failure = $e;
    } catch (Throwable $e) {
        $failure = $e;
    }

    error_log('[storefront] ERP order submission failed for ' . $order['website_order_id'] . ': ' . $failure->getMessage());
    if (reconcile_order($order) === 'submitted') return $ok;

    db_query("UPDATE orders SET submit_state = 'unknown', submit_error = ?, updated_at = UTC_TIMESTAMP(3) WHERE id = ?", [$failure->getMessage(), $order['id']]);
    return [
        'ok' => false,
        'rotate_key' => false,
        'error' => "We couldn't reach our order system to confirm your order. Please try again in a moment — trying again won't create a duplicate order.",
    ];
}

/** Handles a second submit with the same checkout key (double click, retry after an error). */
function resume_order(array $order): array
{
    $ok = ['ok' => true, 'order' => $order];
    switch ($order['submit_state']) {
        case 'submitted':
            return $ok;
        case 'rejected':
            return ['ok' => false, 'rotate_key' => true, 'error' => $order['submit_error'] ?: 'This order was rejected. Please review your cart.'];
        case 'submitting':
            // Another request for this key is still talking to the ERP.
            if (strtotime($order['created_at'] . ' UTC') > time() - 60) return $ok;
            // A stale "submitting" row means that request died; fall through.
        default:
            $outcome = reconcile_order($order);
            if ($outcome === 'submitted') return $ok;
            if ($outcome === 'absent') return submit_order_to_erp($order);
            return ['ok' => false, 'rotate_key' => false, 'error' => "We still can't reach our order system. Please try again in a few minutes."];
    }
}

/**
 * Validated checkout input → local order row → ERP order.
 * $input keys: checkout_key, name, email, phone, company, label, address_line, city,
 * state, pincode, notes, payment_method, items (sku => qty).
 *
 * Returns ['ok' => true, 'order' => row] or
 *         ['ok' => false, 'error' => msg, 'rotate_key' => bool, 'issues' => [...]].
 */
function place_order(array $input): array
{
    $existing = db_one('SELECT * FROM orders WHERE checkout_key = ?', [$input['checkout_key']]);
    if ($existing) return resume_order($existing);

    $method = payment_method($input['payment_method']);
    if (!$method || $method['kind'] !== 'offline') {
        // Online methods need a gateway flow (create payment, redirect, verify) before the ERP order.
        return ['ok' => false, 'rotate_key' => false, 'error' => "That payment method isn't available. Please choose another."];
    }

    $check = check_order_items($input['items']);
    if ($check['issues']) {
        return [
            'ok' => false,
            'rotate_key' => true,
            'issues' => $check['issues'],
            'error' => 'Some items in your cart have changed. Please review them and place your order again.',
        ];
    }

    $subtotal = round(array_sum(array_map(fn($l) => $l['unit_price'] * $l['quantity'], $check['lines'])), 2);
    $pdo = db();
    $orderId = null;

    for ($attempt = 0; $attempt < 3 && $orderId === null; $attempt++) {
        $pdo->beginTransaction();
        try {
            $inserted = db_query(
                'INSERT IGNORE INTO orders (website_order_id, checkout_key, access_token, customer_name, customer_email, customer_phone,
                        customer_company, ship_label, ship_address_line, ship_city, ship_state, ship_pincode, ship_country, notes,
                        payment_method, subtotal_estimate, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))',
                [
                    generate_website_order_id(),
                    $input['checkout_key'],
                    bin2hex(random_bytes(16)),
                    $input['name'],
                    $input['email'],
                    $input['phone'],
                    $input['company'],
                    $input['label'],
                    $input['address_line'],
                    $input['city'],
                    $input['state'],
                    $input['pincode'],
                    'India',
                    $input['notes'],
                    $method['id'],
                    $subtotal,
                ]
            )->rowCount();

            if ($inserted === 1) {
                $orderId = (int) $pdo->lastInsertId();
                foreach ($check['lines'] as $l) {
                    db_query(
                        'INSERT INTO order_items (order_id, sku, name, quantity, unit_price, image_url) VALUES (?, ?, ?, ?, ?, ?)',
                        [$orderId, $l['sku'], $l['name'], $l['quantity'], $l['unit_price'], $l['image_url']]
                    );
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        if ($orderId === null) {
            // A concurrent submit with the same checkout key won, or (vanishingly rarely) the random id collided.
            $concurrent = db_one('SELECT * FROM orders WHERE checkout_key = ?', [$input['checkout_key']]);
            if ($concurrent) return resume_order($concurrent);
        }
    }
    if ($orderId === null) throw new RuntimeException('Could not allocate a website order id');

    return submit_order_to_erp(load_order($orderId));
}

// ---------------------------------------------------------------------------
// Viewing and refreshing orders

function order_needs_refresh(array $o): bool
{
    if ($o['submit_state'] === 'rejected') return false;
    if ($o['submit_state'] === 'unknown') return true;
    if ($o['submit_state'] === 'submitting') return strtotime($o['created_at'] . ' UTC') < time() - 30;
    $last = max(
        $o['last_webhook_at'] ? strtotime($o['last_webhook_at'] . ' UTC') : 0,
        $o['last_polled_at'] ? strtotime($o['last_polled_at'] . ' UTC') : 0
    );
    $interval = in_array($o['status'], TERMINAL_STATUSES, true) ? POLL_TERMINAL_SECONDS : POLL_ACTIVE_SECONDS;
    return $last < time() - $interval;
}

/**
 * Fallback poll of order_status.php for when a webhook was missed (the ERP
 * doesn't retry them) or hasn't arrived yet. Never throws: if the ERP is slow
 * the stored record is shown as-is.
 */
function refresh_order_if_stale(array $o): array
{
    if (!order_needs_refresh($o)) return $o;
    try {
        if ($o['submit_state'] !== 'submitted' || !$o['erp_order_no']) {
            reconcile_order($o);
        } else {
            $s = erp_order_status(['order_no' => $o['erp_order_no']], 3);
            if ($s) apply_status_snapshot((int) $o['id'], $s);
        }
    } catch (Throwable $e) {
        error_log('[storefront] order status refresh failed for ' . $o['website_order_id'] . ': ' . $e->getMessage());
    }
    return load_order((int) $o['id']) ?? $o;
}

/** Loads an order for the holder of its private link (website order id + access key). */
function get_order_for_viewer(string $websiteOrderId, string $token): ?array
{
    if ($websiteOrderId === '' || $token === '') return null;
    $o = db_one('SELECT * FROM orders WHERE website_order_id = ?', [$websiteOrderId]);
    if (!$o || !hash_equals($o['access_token'], $token)) return null;
    return refresh_order_if_stale($o);
}

/**
 * "Track my order": needs the order reference (WEB-… or the ERP's SO-…) AND the
 * checkout email, so knowing one of them alone reveals nothing.
 */
function find_order_link(string $reference, string $email): ?array
{
    $ref = strtoupper(trim($reference));
    return db_one(
        'SELECT website_order_id, access_token FROM orders
          WHERE (website_order_id = ? OR erp_order_no = ?) AND LOWER(customer_email) = ?
          LIMIT 1',
        [$ref, $ref, strtolower(trim($email))]
    );
}

/** Cron: settle orders whose ERP submission outcome is still unknown. */
function reconcile_stuck_orders(int $limit = 25): array
{
    $rows = db_all(
        "SELECT * FROM orders
          WHERE (submit_state = 'unknown' OR (submit_state = 'submitting' AND created_at < UTC_TIMESTAMP(3) - INTERVAL 2 MINUTE))
            AND created_at > UTC_TIMESTAMP(3) - INTERVAL 7 DAY
          ORDER BY created_at LIMIT " . (int) $limit
    );
    $settled = 0;
    foreach ($rows as $row) {
        if (reconcile_order($row) === 'submitted') $settled++;
    }
    return ['checked' => count($rows), 'settled' => $settled];
}

// ---------------------------------------------------------------------------
// Webhooks

/**
 * Applies a verified webhook with a few quick queries. Out-of-order deliveries
 * are handled by applying a status only if its sent_at is not older than the
 * last status recorded. Returns ['duplicate' => bool, 'matched' => bool].
 */
function apply_webhook(string $rawBody, array $payload): array
{
    $d = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $sentAt = db_datetime(is_string($payload['sent_at'] ?? null) ? $payload['sent_at'] : null) ?? db_now();
    $websiteOrderId = is_string($d['website_order_id'] ?? null) && $d['website_order_id'] !== '' ? $d['website_order_id'] : null;
    $orderNo = is_string($d['order_no'] ?? null) && $d['order_no'] !== '' ? $d['order_no'] : null;
    $event = (string) $payload['event'];

    $inserted = db_query(
        'INSERT IGNORE INTO order_events (body_sha256, event, website_order_id, erp_order_no, payload, sent_at, received_at)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(3))',
        [hash('sha256', $rawBody), mb_substr($event, 0, 50), $websiteOrderId, $orderNo, $rawBody, $sentAt]
    )->rowCount();
    if ($inserted === 0) return ['duplicate' => true, 'matched' => false];
    $eventId = (int) db()->lastInsertId();

    if ($websiteOrderId !== null) {
        $match = 'website_order_id = ?';
        $matchParam = $websiteOrderId;
    } elseif ($orderNo !== null) {
        $match = 'erp_order_no = ?';
        $matchParam = $orderNo;
    } else {
        return ['duplicate' => false, 'matched' => false];
    }

    if ($event === 'order.status_changed') {
        $status = valid_status($d['status'] ?? null);
        if ($status === null) return ['duplicate' => false, 'matched' => false, 'ignored' => 'unknown status'];
        // MySQL applies SET assignments left to right: status is decided before status_updated_at moves.
        $count = db_query(
            "UPDATE orders SET
                status = IF(status_updated_at IS NULL OR status_updated_at <= ?, ?, status),
                status_updated_at = GREATEST(COALESCE(status_updated_at, ?), ?),
                erp_order_no = COALESCE(erp_order_no, ?),
                submit_state = 'submitted',
                last_webhook_at = UTC_TIMESTAMP(3), updated_at = UTC_TIMESTAMP(3)
              WHERE $match",
            [$sentAt, $status, $sentAt, $sentAt, $orderNo, $matchParam]
        )->rowCount();
    } elseif ($event === 'order.delivered') {
        $deliveredAt = db_datetime(is_string($d['delivered_at'] ?? null) ? $d['delivered_at'] : null) ?? $sentAt;
        $count = db_query(
            "UPDATE orders SET
                delivered_at = ?, dn_no = COALESCE(?, dn_no), dn_status = 'delivered',
                erp_order_no = COALESCE(erp_order_no, ?),
                submit_state = 'submitted',
                last_webhook_at = UTC_TIMESTAMP(3), updated_at = UTC_TIMESTAMP(3)
              WHERE $match",
            [$deliveredAt, is_string($d['dn_no'] ?? null) ? $d['dn_no'] : null, $orderNo, $matchParam]
        )->rowCount();
    } else {
        return ['duplicate' => false, 'matched' => false, 'ignored' => 'unknown event'];
    }

    // rowCount() is 0 when the row matched but nothing changed; check existence instead.
    $matched = $count > 0 || db_one("SELECT 1 AS x FROM orders WHERE $match", [$matchParam]) !== null;
    if ($matched) db_query('UPDATE order_events SET matched = 1 WHERE id = ?', [$eventId]);
    return ['duplicate' => false, 'matched' => $matched];
}
