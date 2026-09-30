<?php
declare(strict_types=1);

/**
 * Session cart: $_SESSION['cart'] maps SKU => quantity. Names, prices and
 * stock always come fresh from the products table when the cart is shown.
 */

const CART_MAX_LINES = 50;

function cart_raw(): array
{
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

function cart_count(): int
{
    return (int) array_sum(cart_raw());
}

function cart_set(string $sku, int $quantity): void
{
    $cart = cart_raw();
    if ($quantity <= 0) {
        unset($cart[$sku]);
    } elseif (isset($cart[$sku]) || count($cart) < CART_MAX_LINES) {
        $cart[$sku] = min($quantity, 10000);
    }
    $_SESSION['cart'] = $cart;
}

function cart_add(string $sku, int $quantity): void
{
    cart_set($sku, (cart_raw()[$sku] ?? 0) + $quantity);
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/**
 * The cart joined with current catalog data. Items that are no longer sold or
 * are out of stock are removed, quantities above stock are reduced, and a
 * notice is returned for each change.
 */
function cart_contents(): array
{
    $raw = cart_raw();
    $products = get_products_by_skus(array_keys($raw));
    $lines = [];
    $notices = [];
    $changed = false;

    foreach ($raw as $sku => $qty) {
        $sku = (string) $sku;
        $p = $products[$sku] ?? null;
        if (!$p) {
            $notices[] = "An item ($sku) is no longer available and was removed from your cart.";
            unset($raw[$sku]);
            $changed = true;
            continue;
        }
        $max = (int) floor($p['available']);
        if ($max <= 0) {
            $notices[] = "{$p['name']} is out of stock and was removed from your cart.";
            unset($raw[$sku]);
            $changed = true;
            continue;
        }
        if ($qty > $max) {
            $notices[] = "Only $max of {$p['name']} available — quantity updated.";
            $qty = $raw[$sku] = $max;
            $changed = true;
        }
        $lines[] = ['product' => $p, 'quantity' => (int) $qty, 'line_total' => round($p['price'] * $qty, 2), 'max' => $max];
    }
    if ($changed) $_SESSION['cart'] = $raw;

    return [
        'lines' => $lines,
        'notices' => $notices,
        'subtotal' => round(array_sum(array_column($lines, 'line_total')), 2),
        'currency' => $lines[0]['product']['currency'] ?? 'INR',
    ];
}
