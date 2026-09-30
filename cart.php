<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post_string('action', 20);
    $sku = post_string('sku', 100);

    if ($action === 'add') {
        $product = get_product($sku);
        $qty = max(1, (int) post_string('quantity', 6));
        if (!$product || $product['available'] <= 0) {
            flash('error', 'Sorry, that product is no longer available.');
            redirect(url('products.php'));
        }
        $max = (int) floor($product['available']);
        $current = cart_raw()[$sku] ?? 0;
        $newQty = min($current + $qty, $max);
        cart_set($sku, $newQty);
        flash('success', $newQty > $current
            ? "Added {$product['name']} to your cart."
            : "You already have all available stock of {$product['name']} in your cart.");
        redirect(product_url($sku));
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
    redirect(url('cart.php'));
}

$cart = cart_contents();
foreach ($cart['notices'] as $n) flash('warning', $n);

render_page('Your cart', function () use ($cart) { ?>
<div class="container-page py-10">
  <h1 class="text-3xl font-semibold tracking-tight">Your cart</h1>
  <?php if (!$cart['lines']): ?>
    <div class="mt-8 rounded-3xl border border-dashed border-line bg-white p-14 text-center">
      <span class="mx-auto block w-fit text-ink-soft/50"><?= icon('shopping-bag', 'h-10 w-10', 1.5) ?></span>
      <p class="mt-4 text-lg font-medium">Your cart is empty</p>
      <p class="mt-1 text-sm text-ink-soft">Find something you love in our catalogue.</p>
      <a href="<?= e(url('products.php')) ?>" class="btn btn-primary mt-6">Start shopping</a>
    </div>
  <?php else: ?>
    <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_360px]">
      <div>
        <form method="post" action="<?= e(url('cart.php')) ?>" id="cart-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <ul class="divide-y divide-line border-y border-line">
            <?php foreach ($cart['lines'] as $l): $p = $l['product']; ?>
              <li class="flex gap-4 py-5">
                <a href="<?= e(product_url($p['sku'])) ?>" class="w-24 shrink-0 overflow-hidden rounded-xl border border-line sm:w-28"><?= product_image($p['image_url'], $p['name']) ?></a>
                <div class="flex flex-1 flex-col gap-2">
                  <div class="flex justify-between gap-4">
                    <div>
                      <a href="<?= e(product_url($p['sku'])) ?>" class="font-medium hover:underline"><?= e($p['name']) ?></a>
                      <p class="text-sm text-ink-soft"><?= e(format_price($p['price'], $p['currency'])) ?><?= $p['unit'] ? ' / ' . e($p['unit']) : '' ?></p>
                    </div>
                    <p class="font-semibold"><?= e(format_price($l['line_total'], $p['currency'])) ?></p>
                  </div>
                  <div class="mt-auto flex items-center justify-between">
                    <div class="qty inline-flex h-9 items-center rounded-full border border-line bg-white">
                      <button type="button" data-step="-1" class="flex h-full w-9 items-center justify-center rounded-l-full hover:bg-black/5" aria-label="Decrease quantity"><?= icon('minus', 'h-4 w-4') ?></button>
                      <input type="number" name="qty[<?= e($p['sku']) ?>]" value="<?= $l['quantity'] ?>" min="0" max="<?= $l['max'] ?>" data-autosubmit-change aria-label="Quantity for <?= e($p['name']) ?>" class="w-10 bg-transparent text-center text-sm font-medium outline-none">
                      <button type="button" data-step="1" class="flex h-full w-9 items-center justify-center rounded-r-full hover:bg-black/5" aria-label="Increase quantity"><?= icon('plus', 'h-4 w-4') ?></button>
                    </div>
                    <button type="submit" form="remove-<?= e(md5($p['sku'])) ?>" class="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-red-600"><?= icon('trash-2', 'h-4 w-4') ?> Remove</button>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="mt-6 flex items-center justify-between">
            <a href="<?= e(url('products.php')) ?>" class="text-sm font-medium text-ink-soft hover:text-ink">← Continue shopping</a>
            <button type="submit" class="btn btn-outline px-4 py-2 text-sm" data-hide-with-js>Update cart</button>
          </div>
        </form>
        <?php foreach ($cart['lines'] as $l): ?>
          <form method="post" action="<?= e(url('cart.php')) ?>" id="remove-<?= e(md5($l['product']['sku'])) ?>" class="hidden">
            <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="sku" value="<?= e($l['product']['sku']) ?>">
          </form>
        <?php endforeach; ?>
      </div>

      <aside class="h-fit rounded-3xl border border-line bg-white p-6 lg:sticky lg:top-24">
        <h2 class="text-lg font-semibold">Order summary</h2>
        <dl class="mt-5 space-y-3 text-sm">
          <div class="flex justify-between"><dt class="text-ink-soft">Subtotal</dt><dd class="font-medium"><?= e(format_price($cart['subtotal'], $cart['currency'])) ?></dd></div>
          <div class="flex justify-between"><dt class="text-ink-soft">GST</dt><dd class="text-ink-soft">Confirmed on invoice</dd></div>
        </dl>
        <div class="mt-5 flex justify-between border-t border-line pt-5 font-semibold"><span>Total (excl. GST)</span><span><?= e(format_price($cart['subtotal'], $cart['currency'])) ?></span></div>
        <a href="<?= e(url('checkout.php')) ?>" class="btn btn-accent mt-6 w-full">Checkout <?= icon('arrow-right', 'h-4 w-4') ?></a>
        <p class="mt-3 text-center text-xs text-ink-soft">Final prices are confirmed when your order is placed.</p>
      </aside>
    </div>
  <?php endif; ?>
</div>
<?php });
