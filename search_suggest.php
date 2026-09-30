<?php
/** Live search suggestions for the header search box (public catalogue data only). */
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

$q = get_string('q', 60);
if (mb_strlen($q) < 2) json_response(['products' => [], 'categories' => []]);

$products = search_products(['q' => $q, 'per_page' => 6, 'sort' => 'featured'])['products'];
$cats = array_values(array_filter(array_column(get_categories(), 'name'), fn($c) => str_contains(mb_strtolower($c), mb_strtolower($q))));
header('Cache-Control: public, max-age=60');
json_response([
    'products' => array_map(fn($p) => [
        'name' => $p['name'],
        'url' => product_url($p['sku']),
        'price' => format_price($p['price'], $p['currency']),
        'image' => $p['image_url'],
        'category' => $p['category'],
    ], $products),
    'categories' => array_map(fn($c) => ['name' => $c, 'url' => category_url($c)], array_slice($cats, 0, 3)),
    'all_url' => url('products.php', ['q' => $q]),
]);
