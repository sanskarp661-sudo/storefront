<?php
require __DIR__ . '/includes/bootstrap.php';

$categories = get_categories();
$all = search_products(['sort' => 'featured', 'per_page' => 12])['products'];
$newest = search_products(['sort' => 'newest', 'per_page' => 4])['products'];
$withImage = array_values(array_filter($all, fn($p) => $p['image_url']));
$hero = $withImage[0] ?? $all[0] ?? null;
$recent = recently_viewed();
$brands = array_slice(get_brands(null), 0, 6);
// "Shop by category" tabs: up to 4 products from each of the first 4 categories.
$tabs = [];
foreach (array_slice($categories, 0, 4) as $c) {
    $items = search_products(['category' => $c['name'], 'sort' => 'featured', 'per_page' => 4])['products'];
    if ($items) $tabs[] = ['name' => $c['name'], 'products' => $items];
}

render_page('', function () use ($categories, $all, $newest, $hero, $recent, $brands, $tabs) {
    $c0 = $categories[0] ?? null;
    $c1 = $categories[1] ?? null;
    ?>

<!-- Hero -->
<section class="container-page pt-6">
  <div class="grid gap-4 lg:grid-cols-2">
    <div class="relative flex min-h-[480px] items-end overflow-hidden rounded-[4px] bg-[#141414] lg:min-h-[640px]">
      <?php if ($hero && $hero['image_url']): ?>
        <img src="<?= e($hero['image_url']) ?>" alt="" class="absolute inset-0 h-full w-full object-cover object-top" fetchpriority="high">
      <?php else: ?>
        <div class="ph-dk absolute inset-0"></div>
      <?php endif; ?>
      <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>
      <div class="relative flex max-w-[560px] flex-col gap-5 p-7 sm:p-12">
        <span class="eyebrow text-white"><?= $hero ? 'Featured · ' . e($hero['category'] ?: STORE_NAME) : 'Welcome to ' . e(STORE_NAME) ?></span>
        <h1 class="h1 text-white"><?= $hero ? e($hero['name']) : 'Shop now, pay on delivery.' ?></h1>
        <div class="flex flex-wrap gap-2.5">
          <a href="<?= e($hero ? product_url($hero['sku']) : url('products.php')) ?>" class="btn btn-w">Shop now</a>
          <a href="<?= e(url('products.php')) ?>" class="btn border-white text-white hover:bg-white hover:text-ink">Browse all</a>
        </div>
      </div>
    </div>

    <div class="grid gap-4 lg:grid-rows-2">
      <?php if ($c1 ?? $c0): $cat = $c1 ?? $c0; ?>
        <a href="<?= e(category_url($cat['name'])) ?>" class="group relative flex min-h-[312px] items-end justify-between gap-4 overflow-hidden rounded-[4px] bg-linen p-7 text-ink">
          <?php if ($cat['image_url']): ?><img src="<?= e($cat['image_url']) ?>" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover object-top transition duration-700 group-hover:scale-[1.03]"><div class="absolute inset-0 bg-gradient-to-t from-surface/95 via-surface/30 to-transparent"></div><?php endif; ?>
          <span class="relative flex flex-col gap-1.5"><span class="cap"><?= $cat['count'] ?> style<?= $cat['count'] === 1 ? '' : 's' ?></span><span class="h2"><?= e($cat['name']) ?></span></span>
          <span class="btn btn-p btn-s relative">Explore</span>
        </a>
      <?php endif; ?>
      <a href="<?= e(url('products.php')) ?>" class="flex min-h-[312px] flex-col justify-between gap-6 rounded-[4px] bg-accent p-7 text-white hover:text-white">
        <span class="cap">Cash on delivery</span>
        <span class="flex flex-col gap-2.5"><span class="font-display text-[clamp(56px,6.5vw,96px)] leading-[.9]">Pay when it arrives.</span><span class="text-[13px] text-[#dde3ff]">No card needed · delivered across India</span></span>
        <span class="btn btn-w btn-s w-max">Start shopping</span>
      </a>
    </div>
  </div>
</section>

<!-- Categories -->
<?php if ($categories): ?>
<section class="container-page py-14 lg:py-20">
  <div class="mb-7 flex flex-wrap items-end justify-between gap-4"><h2 class="h2">Shop by category</h2><a href="<?= e(url('products.php')) ?>" class="btn btn-t">All products</a></div>
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 <?= [3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][min(5, max(3, count($categories)))] ?>">
    <?php foreach (array_slice($categories, 0, 5) as $c): ?>
      <a href="<?= e(category_url($c['name'])) ?>" class="group flex flex-col gap-3 text-ink">
        <span class="stage"><?php if ($c['image_url']): ?><img src="<?= e($c['image_url']) ?>" alt="" loading="lazy" class="transition duration-500 group-hover:scale-[1.03]"><?php else: ?><span class="absolute inset-0 flex items-center justify-center text-ink-soft"><?= icon(category_icon($c['name']), 'h-12 w-12', 1.2) ?></span><?php endif; ?></span>
        <span class="flex items-baseline justify-between gap-2"><span class="h4"><?= e($c['name']) ?></span><span class="text-xs text-ink-soft"><?= $c['count'] ?></span></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- New in -->
<?php if ($newest): ?>
<section class="container-page">
  <?= section_heading('New in', null, url('products.php', ['sort' => 'newest']), 'View all', 'Just landed') ?>
  <div class="grid grid-cols-2 gap-x-3 gap-y-8 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-5">
    <?php foreach ($newest as $i => $p) echo product_card($p, $i < 2); ?>
  </div>
</section>
<?php else: ?>
<section class="container-page">
  <?= empty_state('package', 'Our catalogue is being updated', 'New products will appear here in a moment. Please check back soon.', url('track.php'), 'Track an order') ?>
</section>
<?php endif; ?>

<!-- Shop by category: tabs -->
<?php if (count($tabs) > 1): ?>
<section class="container-page py-14 lg:py-20" data-tabs>
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <h2 class="h2">Most loved</h2>
    <div class="scroll-x gap-1.5" role="tablist" aria-label="Category">
      <?php foreach ($tabs as $i => $t): ?>
        <button type="button" role="tab" data-tab="t<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" class="chip"><?= e($t['name']) ?></button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php foreach ($tabs as $i => $t): ?>
    <div data-panel="t<?= $i ?>" role="tabpanel" class="grid grid-cols-2 gap-x-3 gap-y-8 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-5 <?= $i ? 'hidden' : '' ?>">
      <?php foreach ($t['products'] as $p) echo product_card($p); ?>
    </div>
  <?php endforeach; ?>
</section>
<?php elseif (count($all) > 4): ?>
<section class="container-page py-14 lg:py-20">
  <?= section_heading('Shop the range', null, url('products.php')) ?>
  <div class="grid grid-cols-2 gap-x-3 gap-y-8 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-5">
    <?php foreach (array_slice($all, 0, 8) as $p) echo product_card($p); ?>
  </div>
</section>
<?php else: ?>
<div class="h-14 lg:h-20"></div>
<?php endif; ?>

<!-- Labels we carry -->
<?php if ($brands): ?>
<section class="border-y border-line">
  <div class="container-page flex min-h-[120px] flex-wrap items-center justify-between gap-6 py-6">
    <span class="cap text-ink-soft">Labels we carry</span>
    <?php foreach ($brands as $b): ?><a href="<?= e(url('products.php', ['brand' => $b])) ?>" class="h3 text-ink"><?= e($b) ?></a><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($recent): ?>
<section class="container-page pt-14 lg:pt-20">
  <?= section_heading('Recently viewed', 'Pick up where you left off.') ?>
  <div class="rail"><?php foreach ($recent as $p) echo product_card($p); ?></div>
</section>
<?php endif; ?>

<!-- How it works -->
<section class="container-page pt-14 lg:pt-20">
  <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
    <div class="flex flex-col gap-5">
      <span class="eyebrow">How it works</span>
      <h2 class="h2">Order online.<br>Pay on delivery.</h2>
      <p class="lead">No cards needed. Our team confirms every order and you pay in cash when it reaches you.</p>
      <a href="<?= e(url('products.php')) ?>" class="btn btn-p w-max">Start shopping</a>
    </div>
    <ol class="grid gap-4 sm:grid-cols-3">
      <?php foreach ([['Add to bag', 'Pick products with live stock and prices.'], ['Place your order', 'Enter your address — no payment needed now.'], ['Pay on delivery', 'Track it online and pay cash at your door.']] as $n => [$t, $d]): ?>
        <li class="flex flex-col gap-2 rounded-[6px] bg-linen p-6">
          <span class="font-display text-5xl leading-none"><?= $n + 1 ?></span>
          <p class="mt-3 font-semibold"><?= e($t) ?></p><p class="text-[13px] text-ink-soft"><?= e($d) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php }, ['active' => 'home']);
