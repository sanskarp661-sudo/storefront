<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post_string('action', 20);
    $sku = post_string('sku', 100);
    $return = safe_return_url(post_string('return', 500), product_url($sku));

    if ($action === 'add' || $action === 'buy') {
        $product = get_product($sku);
        $qty = max(1, (int) post_string('quantity', 6));
        if (!$product || $product['available'] <= 0) {
            flash('error', 'Sorry, that product is no longer available.');
            redirect(url('products.php'));
        }
        $max = (int) floor($product['available']);
        $current = cart_raw()[$sku] ?? 0;
        $newQty = min($current + ($action === 'buy' && $current > 0 ? 0 : $qty), $max);
        cart_set($sku, $newQty);
        if ($action === 'buy') redirect(url('checkout.php'));
        flash('success', $newQty > $current
            ? "Added {$product['name']} to your cart."
            : "You already have all available stock of {$product['name']} in your cart.");
        redirect($return);
    }

    if ($action === 'update') {
        $quantities = $_POST['qty'] ?? [];
        if (is_array($quantities)) {
            foreach ($quantities as $s => $q) {
                if (isset(cart_raw()[(string) $s])) cart_set((string) $s, max(0, (int) $q));
            }
        }
        redirect(url('cart.php'));
    }

    if ($action === 'remove') {
        cart_set($sku, 0);
        redirect(url('cart.php'));
    }

    if ($action === 'save') {
        $c = current_customer();
        if (!$c) redirect(url('login.php', ['return' => url('cart.php')]));
        if (!in_array($sku, wishlist_skus($c), true)) wishlist_toggle((int) $c['id'], $sku);
        cart_set($sku, 0);
        flash('success', 'Moved to your wishlist.');
        redirect(url('cart.php'));
    }
    redirect(url('cart.php'));
}

$cart = cart_contents();
foreach ($cart['notices'] as $n) flash('warning', $n);
$suggestions = array_values(array_filter(search_products(['sort' => 'featured', 'in_stock' => true, 'per_page' => 12])['products'],
    fn($p) => !isset(cart_raw()[$p['sku']])));

render_page('Your cart', function () use ($cart, $suggestions) {
    $itemCount = array_sum(array_column($cart['lines'], 'quantity')); ?>
<div class="container-page py-8">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="text-3xl font-extrabold tracking-tight">Shopping cart</h1>
      <?php if ($cart['lines']): ?><p class="mt-1 text-sm text-ink-soft"><?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?></p><?php endif; ?>
    </div>
    <?php if ($cart['lines']): ?><ol class="hidden items-center gap-2 text-xs font-bold sm:flex">
      <li class="flex items-center gap-2 text-brand"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-white">1</span> Cart</li><li class="h-px w-8 bg-line"></li>
      <li class="flex items-center gap-2 text-muted"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200">2</span> Details</li><li class="h-px w-8 bg-line"></li>
      <li class="flex items-center gap-2 text-muted"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200">3</span> Confirmation</li>
    </ol><?php endif; ?>
  </div>

  <?php if (!$cart['lines']): ?>
    <?= empty_state('shopping-cart', 'Your cart is empty', "Looks like you haven't added anything yet. Explore our catalogue and find something you love.", url('products.php'), 'Start shopping') ?>
  <?php else: ?>
    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
      <div class="card divide-y divide-line">
        <form method="post" action="<?= e(url('cart.php')) ?>" id="cart-form"><?= csrf_field() ?><input type="hidden" name="action" value="update"></form>
        <?php foreach ($cart['lines'] as $l): $p = $l['product']; $fid = md5($p['sku']); ?>
          <div class="flex gap-4 p-4 sm:p-5">
            <a href="<?= e(product_url($p['sku'])) ?>" class="w-24 shrink-0 overflow-hidden rounded-2xl border border-line sm:w-32"><?= product_image($p['image_url'], $p['name'], '', false, $p['category'], 'p-2') ?></a>
            <div class="flex min-w-0 flex-1 flex-col">
              <div class="flex justify-between gap-4">
                <div class="min-w-0">
                  <?php if ($p['brand']): ?><p class="text-[11px] font-bold uppercase tracking-wider text-brand"><?= e($p['brand']) ?></p><?php endif; ?>
                  <a href="<?= e(product_url($p['sku'])) ?>" class="line-clamp-2 font-bold hover:text-brand"><?= e($p['name']) ?></a>
                  <p class="mt-0.5 text-sm text-ink-soft"><?= e(format_price($p['price'], $p['currency'])) ?><?= $p['unit'] ? ' / ' . e($p['unit']) : '' ?></p>
                  <?php if ($l['max'] <= 5): ?><p class="mt-1 text-xs font-bold text-accent-dark">Only <?= $l['max'] ?> left in stock</p><?php endif; ?>
                </div>
                <p class="shrink-0 text-lg font-extrabold"><?= e(format_price($l['line_total'], $p['currency'])) ?></p>
              </div>
              <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 pt-3">
                <div class="qty inline-flex h-10 items-center rounded-xl border border-line bg-white">
                  <button type="button" data-step="-1" class="flex h-full w-10 items-center justify-center rounded-l-xl hover:bg-slate-50" aria-label="Decrease quantity"><?= icon('minus', 'h-4 w-4') ?></button>
                  <input form="cart-form" type="number" name="qty[<?= e($p['sku']) ?>]" value="<?= $l['quantity'] ?>" min="0" max="<?= $l['max'] ?>" data-autosubmit-change aria-label="Quantity for <?= e($p['name']) ?>" class="w-10 bg-transparent text-center text-sm font-bold outline-none">
                  <button type="button" data-step="1" class="flex h-full w-10 items-center justify-center rounded-r-xl hover:bg-slate-50" aria-label="Increase quantity"><?= icon('plus', 'h-4 w-4') ?></button>
                </div>
                <form method="post" action="<?= e(url('cart.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="sku" value="<?= e($p['sku']) ?>">
                  <button class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-brand"><?= icon('heart', 'h-4 w-4') ?> Save for later</button></form>
                <form method="post" action="<?= e(url('cart.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="sku" value="<?= e($p['sku']) ?>">
                  <button class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-rose-600"><?= icon('trash-2', 'h-4 w-4') ?> Remove</button></form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="flex items-center justify-between p-4 sm:px-5">
          <a href="<?= e(url('products.php')) ?>" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand"><?= icon('arrow-left', 'h-4 w-4') ?> Continue shopping</a>
          <button type="submit" form="cart-form" class="btn btn-outline btn-sm" data-hide-with-js>Update cart</button>
        </div>
      </div>

      <aside class="space-y-4 lg:sticky lg:top-36 lg:self-start">
        <div class="card p-6">
          <h2 class="text-lg font-extrabold">Order summary</h2>
          <dl class="mt-5 space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-ink-soft">Items (<?= $itemCount ?>)</dt><dd class="font-semibold"><?= e(format_price($cart['subtotal'], $cart['currency'])) ?></dd></div>
            <div class="flex justify-between"><dt class="text-ink-soft">GST</dt><dd class="text-ink-soft">On invoice</dd></div>
            <div class="flex justify-between"><dt class="text-ink-soft">Payment</dt><dd class="font-semibold text-success">Cash on Delivery</dd></div>
          </dl>
          <div class="mt-5 flex items-end justify-between border-t border-dashed border-line pt-5">
            <span class="font-bold">Total <span class="block text-xs font-medium text-ink-soft">excl. GST</span></span>
            <span class="text-2xl font-extrabold"><?= e(format_price($cart['subtotal'], $cart['currency'])) ?></span>
          </div>
          <a href="<?= e(url('checkout.php')) ?>" class="btn btn-accent btn-lg mt-6 w-full">Proceed to checkout <?= icon('arrow-right', 'h-5 w-5') ?></a>
          <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-ink-soft"><?= icon('lock', 'h-3.5 w-3.5') ?> Final prices are confirmed when you place the order</p>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200">
          <?= icon('banknote', 'h-6 w-6 shrink-0') ?><span><strong>No payment needed now.</strong> Pay in cash when your order is delivered.</span>
        </div>
      </aside>
    </div>
  <?php endif; ?>

  <?php if ($suggestions): ?>
    <section class="mt-16">
      <?= section_heading($cart['lines'] ? 'You might also like' : 'Popular right now', null, url('products.php')) ?>
      <div class="rail"><?php foreach (array_slice($suggestions, 0, 8) as $p) echo product_card($p); ?></div>
    </section>
  <?php endif; ?>
</div>
<?php }, ['active' => 'cart']);
