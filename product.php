<?php
require __DIR__ . '/includes/bootstrap.php';

$product = get_product(get_string('sku', 100));
if (!$product) not_found();
$related = get_related_products($product, 8);
$recent = recently_viewed($product['sku']);
remember_viewed($product['sku']);
$inCart = cart_raw()[$product['sku']] ?? 0;
$max = (int) floor($product['available']);
$remaining = max(0, $max - $inCart);

render_page($product['name'], function () use ($product, $related, $recent, $inCart, $max, $remaining) {
    $p = $product;
    $buyForm = function (string $variant) use ($p, $remaining) {
        ob_start(); ?>
        <form method="post" action="<?= e(url('cart.php')) ?>" class="<?= $variant === 'sticky' ? 'flex items-center gap-2' : 'space-y-4' ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="sku" value="<?= e($p['sku']) ?>">
          <?php if ($variant === 'main'): ?>
            <div class="flex items-center gap-4">
              <span class="text-sm font-semibold text-ink-soft">Quantity</span>
              <div class="qty inline-flex h-12 items-center rounded-xl border-1.5 border border-line bg-white">
                <button type="button" data-step="-1" class="flex h-full w-11 items-center justify-center rounded-l-xl hover:bg-slate-50" aria-label="Decrease quantity"><?= icon('minus', 'h-4 w-4') ?></button>
                <input type="number" name="quantity" value="1" min="1" max="<?= max($remaining, 1) ?>" inputmode="numeric" aria-label="Quantity" class="w-12 bg-transparent text-center font-bold outline-none">
                <button type="button" data-step="1" class="flex h-full w-11 items-center justify-center rounded-r-xl hover:bg-slate-50" aria-label="Increase quantity"><?= icon('plus', 'h-4 w-4') ?></button>
              </div>
            </div>
          <?php else: ?>
            <input type="hidden" name="quantity" value="1">
          <?php endif; ?>
          <div class="<?= $variant === 'sticky' ? 'flex flex-1 gap-2' : 'grid gap-3 sm:grid-cols-2' ?>">
            <button type="submit" name="action" value="add" class="btn btn-outline <?= $variant === 'sticky' ? 'flex-1 px-2 text-sm' : 'btn-lg border-brand text-brand' ?>" <?= $remaining <= 0 ? 'disabled' : '' ?>><?= icon('shopping-cart', 'h-5 w-5') ?> <?= $remaining <= 0 ? 'Max in cart' : ($variant === 'sticky' ? 'Add' : 'Add to cart') ?></button>
            <button type="submit" name="action" value="buy" class="btn btn-accent <?= $variant === 'sticky' ? 'flex-1 px-2 text-sm' : 'btn-lg' ?>" <?= $remaining <= 0 ? 'disabled' : '' ?>><?= icon('zap', 'h-5 w-5') ?> Buy now</button>
          </div>
        </form>
        <?php return (string) ob_get_clean();
    };
    ?>
<div class="container-page py-8">
  <?php
  $crumbs = [['Home', url('')], ['Shop', url('products.php')]];
  if ($p['category']) $crumbs[] = [$p['category'], category_url($p['category'])];
  $crumbs[] = [$p['name'], null];
  echo breadcrumbs($crumbs);
  ?>

  <div class="grid gap-6 lg:grid-cols-[1.1fr_1fr] lg:gap-10">
    <div class="lg:sticky lg:top-36 lg:self-start">
      <div class="card relative overflow-hidden p-3">
        <div class="absolute left-6 top-6 z-10"><?= stock_badge($p['available'], true) ?></div>
        <?= wishlist_button($p['sku'], 'absolute right-6 top-6 z-10') ?>
        <?= product_gallery($p) ?>
      </div>
      <?php if (count($p['images']) > 1): ?>
        <div class="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-6" data-gallery-thumbs>
          <?php foreach ($p['images'] as $i => $src): ?>
            <a href="#img-<?= $i + 1 ?>" data-thumb="<?= $i ?>" aria-label="Show image <?= $i + 1 ?> of <?= count($p['images']) ?>" <?= $i === 0 ? 'aria-current="true"' : '' ?>
               class="block overflow-hidden rounded-xl border-2 bg-white transition <?= $i === 0 ? 'border-brand' : 'border-line hover:border-brand/50' ?>">
              <img src="<?= e($src) ?>" alt="" loading="lazy" decoding="async" class="aspect-square w-full object-contain p-1.5">
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($p['image_url']): ?><p class="mt-3 hidden text-center text-xs text-muted lg:block">Hover over the image to zoom<?= count($p['images']) > 1 ? ' · use the arrows or thumbnails to see all ' . count($p['images']) . ' photos' : '' ?></p><?php endif; ?>
    </div>

    <div class="space-y-5">
      <div class="card p-6 sm:p-8">
        <div class="flex flex-wrap items-center gap-2">
          <?php if ($p['brand']): ?><span class="rounded-lg bg-brand-soft px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-brand"><?= e($p['brand']) ?></span><?php endif; ?>
          <?php if ($p['category']): ?><a href="<?= e(category_url($p['category'])) ?>" class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-ink-soft hover:bg-slate-200"><?= e($p['category']) ?></a><?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl font-extrabold leading-tight tracking-tight sm:text-[2rem]"><?= e($p['name']) ?></h1>
        <p class="mt-1 text-sm text-muted">SKU <span class="font-mono"><?= e($p['sku']) ?></span></p>

        <div class="mt-5 flex flex-wrap items-end gap-x-3 gap-y-1 border-t border-line pt-5">
          <span class="text-[2rem] font-extrabold leading-none tracking-tight"><?= e(format_price($p['price'], $p['currency'])) ?></span>
          <?php if ($p['unit']): ?><span class="pb-1 text-sm text-ink-soft">per <?= e($p['unit']) ?></span><?php endif; ?>
        </div>
        <p class="mt-1 text-xs text-ink-soft">Price excludes GST · applicable taxes are shown on your invoice</p>
        <div class="mt-4"><?= stock_badge($p['available']) ?></div>

        <div class="mt-6">
          <?php if ($max <= 0): ?>
            <div class="rounded-2xl bg-slate-100 p-4 text-sm font-semibold text-ink-soft">This product is currently out of stock. Add it to your wishlist and check back soon.</div>
          <?php else: ?>
            <?= $buyForm('main') ?>
          <?php endif; ?>
          <?php if ($inCart > 0): ?>
            <p class="mt-3 flex items-center gap-2 text-sm text-ink-soft"><?= icon('circle-check', 'h-4 w-4 text-success') ?> <?= $inCart ?> already in your cart · <a href="<?= e(url('cart.php')) ?>" class="font-bold text-brand hover:underline">View cart</a></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card grid gap-4 p-6 sm:grid-cols-2">
        <?php foreach ([
            ['banknote', 'Cash on Delivery', 'Pay in cash when your order arrives'],
            ['shield-check', 'Genuine product', 'Supplied and invoiced with GST'],
            ['truck', 'Track your order', 'Live status from confirmation to delivery'],
            ['headset', 'Here to help', SUPPORT_EMAIL ?: 'Contact our team any time'],
        ] as [$ic, $t, $d]): ?>
          <div class="flex gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand"><?= icon($ic, 'h-5 w-5') ?></span>
            <div class="min-w-0"><p class="text-sm font-bold"><?= e($t) ?></p><p class="text-xs text-ink-soft <?= str_contains($d, '@') ? 'break-all' : '' ?>"><?= e($d) ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="card overflow-hidden" data-tabs>
        <div class="flex border-b border-line text-sm font-bold" role="tablist">
          <?php if ($p['description']): ?><button type="button" role="tab" data-tab="desc" aria-selected="true" class="tab-btn border-b-2 border-brand px-6 py-4 text-brand">Description</button><?php endif; ?>
          <button type="button" role="tab" data-tab="specs" aria-selected="<?= $p['description'] ? 'false' : 'true' ?>" class="tab-btn border-b-2 px-6 py-4 <?= $p['description'] ? 'border-transparent text-ink-soft' : 'border-brand text-brand' ?>">Details</button>
        </div>
        <?php if ($p['description']): ?>
          <div data-panel="desc" class="p-6 text-[15px] leading-relaxed text-ink-soft whitespace-pre-line"><?= e($p['description']) ?></div>
        <?php endif; ?>
        <dl data-panel="specs" class="divide-y divide-line text-sm <?= $p['description'] ? 'hidden' : '' ?>">
          <?php foreach (array_filter(['SKU' => $p['sku'], 'Brand' => $p['brand'], 'Category' => $p['category'], 'Sold per' => $p['unit'], 'Availability' => $max > 0 ? 'In stock' : 'Out of stock']) as $k => $v): ?>
            <div class="grid grid-cols-[8rem_1fr] gap-4 px-6 py-3.5"><dt class="text-ink-soft"><?= e($k) ?></dt><dd class="font-semibold"><?= e((string) $v) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="mt-16">
      <?= section_heading('Similar products', $p['category'] ? 'More from ' . $p['category'] : null, $p['category'] ? category_url($p['category']) : null) ?>
      <div class="rail"><?php foreach ($related as $r) echo product_card($r); ?></div>
    </section>
  <?php endif; ?>
  <?php if ($recent): ?>
    <section class="mt-16">
      <?= section_heading('Recently viewed') ?>
      <div class="rail"><?php foreach ($recent as $r) echo product_card($r); ?></div>
    </section>
  <?php endif; ?>
</div>

<?php if ($max > 0): ?>
  <div class="h-20 lg:hidden"></div>
  <div class="fixed inset-x-0 bottom-16 z-30 border-t border-line bg-white/95 p-3 shadow-[0_-8px_24px_rgb(15_23_42/0.08)] backdrop-blur lg:hidden">
    <div class="flex items-center gap-3">
      <div class="shrink-0"><p class="text-[11px] text-ink-soft">Price</p><p class="whitespace-nowrap font-extrabold leading-tight"><?= e(format_price($p['price'], $p['currency'])) ?></p></div>
      <?= $buyForm('sticky') ?>
    </div>
  </div>
<?php endif; ?>
<?php }, ['description' => mb_substr((string) $product['description'], 0, 160) ?: $product['name']]);
