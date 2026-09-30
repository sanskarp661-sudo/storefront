<?php
require __DIR__ . '/includes/bootstrap.php';

$categories = get_categories();
$all = search_products(['sort' => 'featured', 'per_page' => 12])['products'];
$newest = search_products(['sort' => 'newest', 'per_page' => 8])['products'];
$withImage = array_values(array_filter($all, fn($p) => $p['image_url']));
$hero = $withImage[0] ?? $all[0] ?? null;
$heroSide = array_slice(array_values(array_filter($withImage, fn($p) => $p['sku'] !== ($hero['sku'] ?? ''))), 0, 2);
$customer = current_customer();
$recent = recently_viewed();

render_page('', function () use ($categories, $all, $newest, $hero, $heroSide, $customer, $recent) { ?>

<!-- Hero -->
<section class="container-page pt-5 lg:pt-8">
  <div class="grid gap-4 lg:grid-cols-[1.65fr_1fr]">
    <div class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-brand via-indigo-600 to-violet-700 text-white shadow-lift">
      <div class="hero-grid absolute inset-0"></div>
      <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-fuchsia-400/30 blur-3xl"></div>
      <div class="absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-accent/30 blur-3xl"></div>
      <div class="relative grid items-center gap-6 p-7 sm:p-10 md:grid-cols-[1.1fr_1fr] lg:min-h-[26rem]">
        <div>
          <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold backdrop-blur"><?= icon('sparkles', 'h-3.5 w-3.5') ?> <?= $hero ? 'Featured today' : 'Welcome to ' . e(STORE_NAME) ?></span>
          <h1 class="mt-4 text-[2.1rem] font-extrabold leading-[1.05] tracking-tight sm:text-5xl">
            <?= $hero ? e($hero['name']) : 'Shop smarter, pay on delivery.' ?>
          </h1>
          <p class="mt-4 max-w-md text-[15px] leading-relaxed text-white/80">
            <?= $hero && $hero['description'] ? e(mb_strimwidth(preg_replace('/\s+/', ' ', $hero['description']), 0, 140, '…')) : 'Genuine products with live stock from our warehouse. Order in minutes, pay cash when it arrives, and track every step.' ?>
          </p>
          <?php if ($hero): ?>
            <p class="mt-5 flex items-baseline gap-2"><span class="text-3xl font-extrabold"><?= e(format_price($hero['price'], $hero['currency'])) ?></span><span class="text-sm text-white/70">excl. GST</span></p>
          <?php endif; ?>
          <div class="mt-6 flex flex-wrap gap-3">
            <a href="<?= e($hero ? product_url($hero['sku']) : url('products.php')) ?>" class="btn btn-lg bg-white text-brand shadow-lg hover:bg-brand-soft"><?= $hero ? 'Shop now' : 'Start shopping' ?> <?= icon('arrow-right', 'h-5 w-5') ?></a>
            <a href="<?= e(url('products.php')) ?>" class="btn btn-lg border border-white/30 text-white hover:bg-white/10">Browse all</a>
          </div>
        </div>
        <?php if ($hero): ?>
          <a href="<?= e(product_url($hero['sku'])) ?>" class="relative mx-auto w-full max-w-sm">
            <div class="absolute inset-6 rounded-full bg-white/25 blur-2xl"></div>
            <div class="relative overflow-hidden rounded-3xl bg-white p-2 shadow-2xl ring-1 ring-white/40 transition duration-500 hover:rotate-0 md:rotate-2">
              <?= product_image($hero['image_url'], $hero['name'], 'rounded-2xl', true, $hero['category'], 'p-6') ?>
            </div>
            <span class="absolute -bottom-3 -left-3 flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-sm font-bold text-ink shadow-xl"><?= icon('banknote', 'h-5 w-5 text-accent') ?> Cash on Delivery</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
      <?php foreach ($heroSide as $i => $p): ?>
        <a href="<?= e(product_url($p['sku'])) ?>" class="group relative flex items-center gap-4 overflow-hidden rounded-[1.75rem] p-6 shadow-card <?= $i === 0 ? 'bg-gradient-to-br from-orange-100 to-amber-50' : 'bg-gradient-to-br from-emerald-100 to-teal-50' ?>">
          <div class="flex-1">
            <p class="text-[11px] font-bold uppercase tracking-wider <?= $i === 0 ? 'text-accent-dark' : 'text-emerald-700' ?>"><?= $i === 0 ? 'Trending' : 'Just in' ?></p>
            <h3 class="mt-1 line-clamp-2 text-lg font-extrabold leading-tight"><?= e($p['name']) ?></h3>
            <p class="mt-2 font-bold"><?= e(format_price($p['price'], $p['currency'])) ?></p>
            <span class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-ink group-hover:gap-2 transition-all">Shop now <?= icon('arrow-right', 'h-4 w-4') ?></span>
          </div>
          <div class="w-32 shrink-0 overflow-hidden rounded-2xl bg-white shadow-sm transition duration-500 group-hover:scale-105"><?= product_image($p['image_url'], $p['name'], '', true, $p['category'], 'p-3') ?></div>
        </a>
      <?php endforeach; ?>
      <?php if (count($heroSide) < 2): ?>
        <?php if (!$heroSide): ?>
          <a href="<?= e(url('products.php', ['sort' => 'newest'])) ?>" class="group relative flex flex-col justify-between overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-orange-100 to-amber-50 p-6 shadow-card">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-accent shadow-sm"><?= icon('sparkles', 'h-6 w-6') ?></span>
            <div class="mt-6"><h3 class="text-xl font-extrabold">New arrivals</h3><p class="mt-1 text-sm text-ink-soft">Fresh stock, just added</p></div>
            <span class="mt-3 inline-flex items-center gap-1 text-sm font-bold group-hover:gap-2 transition-all">Explore <?= icon('arrow-right', 'h-4 w-4') ?></span>
          </a>
        <?php endif; ?>
        <a href="<?= e(url($customer ? 'account.php' : 'register.php')) ?>" class="group relative flex flex-col justify-between overflow-hidden rounded-[1.75rem] bg-ink p-6 text-white shadow-card">
          <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-brand/40 blur-2xl"></div>
          <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10"><?= icon($customer ? 'circle-user' : 'gift', 'h-6 w-6') ?></span>
          <div class="relative mt-6"><h3 class="text-xl font-extrabold"><?= $customer ? 'Welcome back, ' . e(explode(' ', $customer['name'])[0]) : 'Create your account' ?></h3><p class="mt-1 text-sm text-white/70"><?= $customer ? 'See your orders, wishlist and addresses' : 'Save addresses, wishlist favourites and track orders' ?></p></div>
          <span class="relative mt-3 inline-flex items-center gap-1 text-sm font-bold text-accent group-hover:gap-2 transition-all"><?= $customer ? 'My account' : 'Sign up free' ?> <?= icon('arrow-right', 'h-4 w-4') ?></span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Categories -->
<?php if ($categories): ?>
<section class="container-page pt-14">
  <?= section_heading('Shop by category', 'Find what you need, faster', url('products.php'), 'All products') ?>
  <?php $catCols = [2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5', 6 => 'lg:grid-cols-6'][min(6, max(2, min(5, count($categories)) + 1))]; ?>
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 <?= $catCols ?>">
    <?php foreach (array_slice($categories, 0, 5) as $c): ?>
      <a href="<?= e(category_url($c['name'])) ?>" class="group relative overflow-hidden rounded-3xl bg-gradient-to-br <?= tile_gradient($c['name']) ?> p-5 text-white shadow-card transition hover:-translate-y-1 hover:shadow-lift">
        <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-white/15 transition group-hover:scale-125"></div>
        <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 backdrop-blur"><?= icon(category_icon($c['name']), 'h-6 w-6') ?></span>
        <p class="relative mt-8 text-lg font-extrabold leading-tight"><?= e($c['name']) ?></p>
        <p class="relative mt-0.5 flex items-center gap-1 text-xs font-semibold text-white/80"><?= $c['count'] ?> product<?= $c['count'] === 1 ? '' : 's' ?> <?= icon('arrow-right', 'h-3.5 w-3.5 transition group-hover:translate-x-1') ?></p>
      </a>
    <?php endforeach; ?>
    <a href="<?= e(url('products.php')) ?>" class="group flex flex-col justify-between rounded-3xl border-2 border-dashed border-line bg-white p-5 transition hover:border-brand">
      <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-soft text-brand"><?= icon('layout-grid', 'h-6 w-6') ?></span>
      <div class="mt-8"><p class="text-lg font-extrabold">Everything</p><p class="flex items-center gap-1 text-xs font-semibold text-ink-soft">Browse the full range <?= icon('arrow-right', 'h-3.5 w-3.5') ?></p></div>
    </a>
  </div>
</section>
<?php endif; ?>

<!-- New arrivals rail (only when the catalogue is big enough not to repeat "Popular picks") -->
<?php if (count($all) > 8 || !$all): ?>
<section class="container-page pt-14">
  <?= section_heading('New arrivals', 'The latest additions to our catalogue', url('products.php', ['sort' => 'newest'])) ?>
  <?php if ($newest): ?>
    <div class="rail"><?php foreach ($newest as $p) echo product_card($p); ?></div>
  <?php else: ?>
    <?= empty_state('package', 'Our catalogue is being updated', 'New products will appear here in a moment. Please check back soon.', url('track.php'), 'Track an order') ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- Promo banners per category -->
<?php if (count($categories) >= 1): ?>
<section class="container-page grid gap-4 pt-14 md:grid-cols-2">
  <?php foreach (array_slice($categories, 0, 2) as $i => $c): ?>
    <a href="<?= e(category_url($c['name'])) ?>" class="group relative flex min-h-[13rem] items-center overflow-hidden rounded-[1.75rem] p-8 shadow-card <?= $i === 0 ? 'bg-ink text-white' : 'bg-gradient-to-br from-accent to-rose-500 text-white' ?>">
      <div class="relative z-10 max-w-[60%]">
        <p class="text-xs font-bold uppercase tracking-wider text-white/70">Explore</p>
        <h3 class="mt-1 text-2xl font-extrabold leading-tight sm:text-3xl"><?= e($c['name']) ?></h3>
        <p class="mt-2 text-sm text-white/75"><?= $c['count'] ?> product<?= $c['count'] === 1 ? '' : 's' ?> in stock and ready to ship</p>
        <span class="btn btn-sm mt-5 bg-white text-ink">Shop <?= e($c['name']) ?> <?= icon('arrow-right', 'h-4 w-4') ?></span>
      </div>
      <div class="absolute -right-6 bottom-0 top-0 flex w-[45%] items-center justify-center">
        <?php if ($c['image_url']): ?>
          <div class="w-full max-w-[14rem] overflow-hidden rounded-3xl bg-white shadow-2xl transition duration-500 group-hover:-rotate-3 group-hover:scale-105"><?= product_image($c['image_url'], $c['name'], '', false, $c['name'], 'p-4') ?></div>
        <?php else: ?>
          <span class="text-white/25 transition duration-500 group-hover:scale-110"><?= icon(category_icon($c['name']), 'h-40 w-40', 1.25) ?></span>
        <?php endif; ?>
      </div>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- All products -->
<?php if (count($all) > 0): ?>
<section class="container-page pt-14">
  <?= section_heading(count($all) > 8 ? 'Popular picks' : 'Shop our range', 'In stock and ready to ship', url('products.php')) ?>
  <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
    <?php foreach (array_slice($all, 0, 8) as $p) echo product_card($p); ?>
  </div>
</section>
<?php endif; ?>

<?php if ($recent): ?>
<section class="container-page pt-14">
  <?= section_heading('Recently viewed', 'Pick up where you left off') ?>
  <div class="rail"><?php foreach ($recent as $p) echo product_card($p); ?></div>
</section>
<?php endif; ?>

<!-- How it works -->
<section class="container-page pt-14">
  <div class="card grid gap-8 p-8 sm:p-10 lg:grid-cols-[1fr_2fr] lg:items-center">
    <div>
      <p class="text-xs font-bold uppercase tracking-wider text-brand">How it works</p>
      <h2 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Order online. Pay on delivery.</h2>
      <p class="mt-2 text-sm text-ink-soft">No cards needed. Our team confirms every order and you pay in cash when it reaches you.</p>
    </div>
    <ol class="grid gap-4 sm:grid-cols-3">
      <?php foreach ([['shopping-cart', 'Add to cart', 'Pick products with live stock and prices.'], ['circle-check', 'Place your order', 'Enter your address — no payment needed now.'], ['banknote', 'Pay on delivery', 'Track it online and pay cash at your door.']] as $n => [$ic, $t, $d]): ?>
        <li class="relative rounded-2xl bg-surface p-5">
          <span class="absolute right-4 top-4 text-4xl font-extrabold text-line"><?= $n + 1 ?></span>
          <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-brand shadow-sm"><?= icon($ic, 'h-5 w-5') ?></span>
          <p class="mt-4 font-bold"><?= e($t) ?></p><p class="mt-1 text-sm text-ink-soft"><?= e($d) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php }, ['active' => 'home']);
