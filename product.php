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
        $full = $remaining <= 0;
        ob_start(); ?>
        <form method="post" action="<?= e(url('cart.php')) ?>" class="flex flex-wrap items-center gap-2.5">
          <?= csrf_field() ?>
          <input type="hidden" name="sku" value="<?= e($p['sku']) ?>">
          <?php if ($variant === 'main'): ?>
            <div class="qty flex h-[52px] items-center rounded-[2px] border border-line bg-white">
              <button type="button" data-step="-1" class="flex h-full w-11 items-center justify-center hover:bg-linen" aria-label="Decrease quantity"><?= icon('minus', 'h-4 w-4') ?></button>
              <input type="number" name="quantity" value="1" min="1" max="<?= max($remaining, 1) ?>" inputmode="numeric" aria-label="Quantity" class="w-10 bg-transparent text-center font-semibold outline-none">
              <button type="button" data-step="1" class="flex h-full w-11 items-center justify-center hover:bg-linen" aria-label="Increase quantity"><?= icon('plus', 'h-4 w-4') ?></button>
            </div>
          <?php else: ?>
            <input type="hidden" name="quantity" value="1">
          <?php endif; ?>
          <button type="submit" name="action" value="add" class="btn btn-p min-h-[52px] flex-1 <?= $variant === 'sticky' ? '!px-3' : '' ?>" <?= $full ? 'disabled' : '' ?>><?= icon('shopping-bag', 'h-[18px] w-[18px]', 1.6) ?> <?= $full ? 'Max in bag' : 'Add to bag' ?></button>
          <button type="submit" name="action" value="buy" class="btn btn-g min-h-[52px] flex-1 <?= $variant === 'sticky' ? '!px-3' : '' ?>" <?= $full ? 'disabled' : '' ?>>Buy now</button>
        </form>
        <?php return (string) ob_get_clean();
    };
    ?>
<div class="container-page flex flex-col gap-14 pb-4 pt-6">
  <div class="flex flex-col gap-5">
    <?php
    $crumbs = [['Home', url('')]];
    if ($p['category']) $crumbs[] = [$p['category'], category_url($p['category'])];
    if ($p['category'] && $p['sub_category']) $crumbs[] = [$p['sub_category'], url('products.php', ['category' => $p['category'], 'sub' => $p['sub_category']])];
    $crumbs[] = [$p['name'], null];
    echo str_replace('mb-6', '', breadcrumbs($crumbs));
    ?>

    <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-12">
      <!-- Gallery -->
      <section class="flex items-start gap-3.5 lg:sticky lg:top-40" aria-label="Product images">
        <?php if (count($p['images']) > 1): ?>
          <div class="hidden w-[84px] flex-none flex-col gap-2.5 sm:flex" data-gallery-thumbs>
            <?php foreach ($p['images'] as $i => $src): ?>
              <a href="#img-<?= $i + 1 ?>" data-thumb="<?= $i ?>" aria-label="Show image <?= $i + 1 ?> of <?= count($p['images']) ?>" <?= $i === 0 ? 'aria-current="true"' : '' ?>
                 class="stage block border-2 <?= $i === 0 ? 'border-brand' : 'border-line' ?>">
                <img src="<?= e($src) ?>" alt="" loading="lazy" decoding="async">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="relative min-w-0 flex-1">
          <div class="pc-badges !left-4 !top-4"><?= stock_badge($p['available'], true) ?></div>
          <?= product_gallery($p) ?>
        </div>
      </section>

      <!-- Buy box -->
      <section class="flex flex-col gap-6" aria-label="Product details">
        <div class="flex flex-col gap-2.5">
          <?php if ($p['brand']): ?><a href="<?= e(url('products.php', ['brand' => $p['brand']])) ?>" class="text-[13px] font-semibold"><?= e($p['brand']) ?></a><?php endif; ?>
          <h1 class="h2"><?= e($p['name']) ?></h1>
          <p class="text-xs text-ink-soft">SKU <?= e($p['sku']) ?><?= $p['sub_category'] ? ' · ' . e($p['sub_category']) : '' ?></p>
        </div>
        <div class="flex flex-col gap-1.5">
          <p class="flex items-baseline gap-3"><span class="price-l"><?= e(format_price($p['price'], $p['currency'])) ?></span><?php if ($p['unit']): ?><span class="text-sm text-ink-soft">per <?= e($p['unit']) ?></span><?php endif; ?></p>
          <span class="text-xs text-ink-soft">Price excludes GST · applicable taxes are shown on your invoice</span>
        </div>
        <div class="flex items-center gap-2"><?= stock_badge($p['available']) ?><?php if ($max > 0): ?><span class="text-[13px] text-ink-soft">Live stock from our warehouse</span><?php endif; ?></div>

        <?php if ($max <= 0): ?>
          <div class="alert warn">This product is sold out right now. Add it to your wishlist and check back soon.</div>
          <div class="flex items-center gap-2.5"><?= wishlist_button($p['sku']) ?><span class="text-sm">Save to wishlist</span></div>
        <?php else: ?>
          <div class="flex items-center gap-2.5">
            <div class="min-w-0 flex-1"><?= $buyForm('main') ?></div>
            <?= str_replace('h-10 w-10', 'h-[52px] w-[52px] border border-line', wishlist_button($p['sku'])) ?>
          </div>
        <?php endif; ?>
        <?php if ($inCart > 0): ?>
          <div class="alert ok items-center" role="status"><?= icon('circle-check', 'h-4 w-4 shrink-0') ?> <span class="flex-1"><?= $inCart ?> already in your bag</span><a href="<?= e(url('cart.php')) ?>" class="font-semibold underline underline-offset-4">View bag</a></div>
        <?php endif; ?>

        <div class="flex flex-col gap-2.5 rounded-[4px] border border-line bg-white p-4 text-sm">
          <p class="lbl !mb-0 flex items-center gap-2"><?= icon('truck', 'h-4 w-4') ?> Delivery</p>
          <p class="flex items-center gap-2"><?= icon('check', 'h-4 w-4 text-moss') ?> Cash on delivery available across India</p>
          <p class="flex items-center gap-2"><?= icon('check', 'h-4 w-4 text-moss') ?> Track your order online at every step</p>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
          <?php foreach ([['banknote', 'Pay on delivery'], ['shield-check', 'Genuine, GST invoiced'], ['headset', 'Help when you need it']] as [$ic, $t]): ?>
            <div class="flex flex-col items-center gap-1.5 px-1.5 py-3"><?= icon($ic, 'h-5 w-5', 1.6) ?><span class="text-xs font-semibold"><?= e($t) ?></span></div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </div>

  <!-- Details -->
  <section class="flex flex-col gap-6" data-tabs>
    <div class="tabs" role="tablist">
      <?php if ($p['description']): ?><button type="button" role="tab" data-tab="desc" aria-selected="true" class="tab">Description</button><?php endif; ?>
      <button type="button" role="tab" data-tab="specs" aria-selected="<?= $p['description'] ? 'false' : 'true' ?>" class="tab">Details</button>
      <button type="button" role="tab" data-tab="ship" aria-selected="false" class="tab">Delivery &amp; payment</button>
    </div>
    <?php if ($p['description']): ?>
      <div data-panel="desc" class="max-w-3xl whitespace-pre-line text-base leading-[1.75] text-[#2b2926]"><?= e($p['description']) ?></div>
    <?php endif; ?>
    <div data-panel="specs" class="<?= $p['description'] ? 'hidden' : '' ?>">
      <table class="tbl card max-w-[760px] overflow-hidden"><tbody>
        <?php foreach (array_filter(['SKU' => $p['sku'], 'Brand' => $p['brand'], 'Category' => $p['category'], 'Type' => $p['sub_category'], 'Sold per' => $p['unit'], 'Availability' => $max > 0 ? 'In stock' : 'Sold out']) as $k => $v): ?>
          <tr><td class="w-[220px] text-ink-soft"><?= e($k) ?></td><td><?= e((string) $v) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
    <div data-panel="ship" class="hidden grid max-w-[960px] gap-4 md:grid-cols-2">
      <div class="card flex flex-col gap-3 p-7"><h3 class="h4">Delivery</h3><p class="text-[13px] text-ink-soft">We confirm every order before it ships, and you can follow it online from confirmation to your door.</p><a href="<?= e(url('track.php')) ?>" class="text-[13px] font-semibold underline underline-offset-4">Track an order</a></div>
      <div class="card flex flex-col gap-3 p-7"><h3 class="h4">Payment</h3><p class="text-[13px] text-ink-soft">Pay in cash when your order arrives. Prices exclude GST; applicable taxes are shown on your invoice.</p></div>
    </div>
  </section>

  <?php if ($related): ?>
    <section>
      <?= section_heading('You may also like', null, $p['category'] ? category_url($p['category']) : null, 'Shop ' . ($p['category'] ?: 'all')) ?>
      <div class="grid grid-cols-2 gap-x-3 gap-y-8 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-5"><?php foreach (array_slice($related, 0, 4) as $r) echo product_card($r); ?></div>
    </section>
  <?php endif; ?>
  <?php if ($recent): ?>
    <section>
      <?= section_heading('Recently viewed') ?>
      <div class="rail"><?php foreach ($recent as $r) echo product_card($r); ?></div>
    </section>
  <?php endif; ?>
</div>

<?php if ($max > 0): ?>
  <div class="h-20 lg:hidden"></div>
  <div class="fixed inset-x-0 bottom-16 z-30 border-t border-line bg-white px-4 py-3 lg:hidden">
    <div class="flex items-center gap-3">
      <div class="shrink-0"><p class="text-[11px] text-ink-soft">Price</p><p class="whitespace-nowrap font-semibold leading-tight"><?= e(format_price($p['price'], $p['currency'])) ?></p></div>
      <div class="min-w-0 flex-1"><?= $buyForm('sticky') ?></div>
    </div>
  </div>
<?php endif; ?>
<?php }, ['description' => mb_substr((string) $product['description'], 0, 160) ?: $product['name'], 'active' => $product['category'] ?? '']);
