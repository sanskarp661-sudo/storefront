<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('account.php', ['tab' => 'wishlist']));
require_csrf();
$sku = post_string('sku', 100);
$return = safe_return_url(post_string('return', 500), url('products.php'));

$customer = current_customer();
if (!$customer) {
    flash('warning', 'Sign in to save products to your wishlist.');
    redirect(url('login.php', ['return' => $return]));
}
$product = get_product($sku) ?? (in_array($sku, wishlist_skus($customer), true) ? ['name' => $sku] : null);
if (!$product) redirect($return);

$added = wishlist_toggle((int) $customer['id'], $sku);
flash('success', $added ? "Saved {$product['name']} to your wishlist." : "Removed {$product['name']} from your wishlist.");
redirect($return);
