<?php
require __DIR__ . '/includes/bootstrap.php';

$product = get_product(get_string('sku', 100));
if (!$product) not_found();
$related = get_related_products($product);
$inCart = cart_raw()[$product['sku']] ?? 0;
$max = (int) floor($product['available']);
$remaining = max(0, $max - $inCart);

render_page($product['name'], function () use ($product, $related, $inCart, $max, $remaining) { ?>
<div class="container-page py-10">
  <?php
  $crumbs = [['Home', url('')], ['Shop', url('products.php')]];
  if ($product['category']) $crumbs[] = [$product['category'], category_url($product['category'])];
  $crumbs[] = [$product['name'], null];
  echo breadcrumbs($crumbs);
  ?>
  <div class="grid gap-10 md:grid-cols-2 lg:gap-16">
    <div class="overflow-hidden rounded-3xl border border-line bg-white">
      <?= product_image($product['image_url'], $product['name'], '', true) ?>
    </div>

    <div class="flex flex-col">
      <?php if ($product['brand']): ?><p class="text-sm font-medium uppercase tracking-wide text-ink-soft"><?= e($product['brand']) ?></p><?php endif; ?>
      <h1 class="mt-1 text-3xl font-semibold tracking-tight lg:text-4xl"><?= e($product['name']) ?></h1>
      <div class="mt-4 flex flex-wrap items-center gap-3">
        <p class="text-2xl font-semibold"><?= e(format_price($product['price'], $product['currency'])) ?><?php if ($product['unit']): ?><span class="ml-1 text-base font-normal text-ink-soft">/ <?= e($product['unit']) ?></span><?php endif; ?></p>
        <?= stock_badge($product['available']) ?>
      </div>
      <p class="mt-1 text-xs text-ink-soft">Price excludes GST. Applicable taxes are shown on your invoice.</p>

      <div class="mt-8 space-y-3">
        <?php if ($max <= 0): ?>
          <button type="button" class="btn btn-outline w-full sm:w-auto" disabled>Out of stock</button>
        <?php else: ?>
          <form method="post" action="<?= e(url('cart.php')) ?>" class="flex flex-wrap items-center gap-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="sku" value="<?= e($product['sku']) ?>">
            <div class="qty inline-flex h-12 items-center rounded-full border border-line bg-white">
              <button type="button" data-step="-1" class="flex h-full w-10 items-center justify-center rounded-l-full hover:bg-black/5" aria-label="Decrease quantity"><?= icon('minus', 'h-4 w-4') ?></button>
              <input type="number" name="quantity" value="1" min="1" max="<?= max($remaining, 1) ?>" inputmode="numeric" aria-label="Quantity" class="w-12 bg-transparent text-center text-sm font-medium outline-none">
              <button type="button" data-step="1" class="flex h-full w-10 items-center justify-center rounded-r-full hover:bg-black/5" aria-label="Increase quantity"><?= icon('plus', 'h-4 w-4') ?></button>
            </div>
            <button type="submit" class="btn btn-primary h-12 flex-1 sm:flex-none sm:px-10" <?= $remaining <= 0 ? 'disabled' : '' ?>>
              <?= icon('shopping-bag', 'h-4 w-4') ?> <?= $remaining <= 0 ? 'Max quantity in cart' : 'Add to cart' ?>
            </button>
          </form>
        <?php endif; ?>
        <?php if ($inCart > 0): ?>
          <p class="text-sm text-ink-soft"><?= $inCart ?> in your cart · <a href="<?= e(url('cart.php')) ?>" class="font-medium text-ink underline underline-offset-4">View cart</a></p>
        <?php endif; ?>
      </div>

      <?php if ($product['description']): ?>
        <div class="mt-10 border-t border-line pt-8">
          <h2 class="mb-3 font-semibold">Description</h2>
          <div class="whitespace-pre-line leading-relaxed text-ink-soft"><?= e($product['description']) ?></div>
        </div>
      <?php endif; ?>

      <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-8 text-sm">
        <dt class="text-ink-soft">SKU</dt><dd class="font-mono"><?= e($product['sku']) ?></dd>
        <?php if ($product['category']): ?><dt class="text-ink-soft">Category</dt><dd><a href="<?= e(category_url($product['category'])) ?>" class="underline underline-offset-4"><?= e($product['category']) ?></a></dd><?php endif; ?>
        <?php if ($product['brand']): ?><dt class="text-ink-soft">Brand</dt><dd><?= e($product['brand']) ?></dd><?php endif; ?>
        <?php if ($product['unit']): ?><dt class="text-ink-soft">Sold per</dt><dd><?= e($product['unit']) ?></dd><?php endif; ?>
      </dl>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="mt-20">
      <h2 class="mb-6 text-2xl font-semibold tracking-tight">You may also like</h2>
      <div class="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-4">
        <?php foreach ($related as $p) echo product_card($p); ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php }, ['description' => mb_substr((string) $product['description'], 0, 160)]);
