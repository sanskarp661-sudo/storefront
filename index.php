<?php
require __DIR__ . '/includes/bootstrap.php';

$categories = get_categories();
$newest = search_products(['sort' => 'newest', 'in_stock' => true, 'per_page' => 8])['products'];
$hero = array_slice(array_values(array_filter(
    search_products(['sort' => 'featured', 'per_page' => 8])['products'],
    fn($p) => $p['image_url']
)), 0, 3);

render_page('', function () use ($categories, $newest, $hero) { ?>
<section class="border-b border-line">
  <div class="container-page grid items-center gap-10 py-14 md:grid-cols-2 md:py-20">
    <div class="space-y-6">
      <span class="inline-flex items-center gap-2 rounded-full border border-line bg-white px-3 py-1 text-xs font-medium text-ink-soft">
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Live stock, straight from our warehouse
      </span>
      <h1 class="text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
        Everything you need, <span class="text-accent">in stock</span> and ready to ship.
      </h1>
      <p class="max-w-md text-lg text-ink-soft">
        Browse the full <?= e(STORE_NAME) ?> catalogue with up-to-date availability, pay cash on delivery, and follow your order to your doorstep.
      </p>
      <div class="flex flex-wrap gap-3">
        <a href="<?= e(url('products.php')) ?>" class="btn btn-primary">Shop the catalogue <?= icon('arrow-right', 'h-4 w-4') ?></a>
        <a href="<?= e(url('track.php')) ?>" class="btn btn-outline">Track an order</a>
      </div>
    </div>
    <div class="relative hidden h-[420px] md:block">
      <?php if ($hero): ?>
        <?php $pos = ['left-0 top-6 rotate-[-4deg]', 'left-1/2 top-0 z-10 -translate-x-1/2', 'right-0 top-16 rotate-[5deg]']; ?>
        <?php foreach ($hero as $i => $p): ?>
          <a href="<?= e(product_url($p['sku'])) ?>" class="absolute w-56 overflow-hidden rounded-3xl border border-line bg-white shadow-xl shadow-black/5 <?= $pos[$i] ?>">
            <?= product_image($p['image_url'], $p['name'], '', true) ?>
            <div class="line-clamp-1 p-3 text-sm font-medium"><?= e($p['name']) ?></div>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="grid h-full grid-cols-2 gap-4">
          <div class="rounded-3xl bg-ink"></div><div class="rounded-full bg-accent"></div>
          <div class="rounded-full bg-accent/30"></div><div class="rounded-3xl bg-ink/80"></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="container-page grid gap-4 py-10 sm:grid-cols-3">
  <?php foreach ([
      ['badge-check', 'Up-to-date availability', 'Stock levels come straight from our inventory system.'],
      ['banknote', 'Cash on Delivery', 'Pay in cash when your order arrives.'],
      ['package-check', 'Track every step', 'See when your order is confirmed, shipped and delivered.'],
  ] as [$ic, $title, $body]): ?>
    <div class="flex gap-4 rounded-2xl border border-line bg-white p-5">
      <span class="text-accent"><?= icon($ic, 'h-6 w-6 shrink-0', 1.75) ?></span>
      <div><p class="font-medium"><?= e($title) ?></p><p class="mt-1 text-sm text-ink-soft"><?= e($body) ?></p></div>
    </div>
  <?php endforeach; ?>
</section>

<?php if ($categories): ?>
<section class="container-page py-10">
  <div class="mb-6 flex items-end justify-between">
    <h2 class="text-2xl font-semibold tracking-tight">Shop by category</h2>
    <a href="<?= e(url('products.php')) ?>" class="text-sm font-medium text-ink-soft hover:text-ink">View all →</a>
  </div>
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
    <?php foreach (array_slice($categories, 0, 8) as $c): ?>
      <a href="<?= e(category_url($c['name'])) ?>" class="group relative overflow-hidden rounded-2xl border border-line bg-white">
        <?= product_image($c['image_url'], '', 'aspect-[4/3]! transition-transform duration-500 group-hover:scale-105') ?>
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
        <div class="absolute bottom-0 p-4 text-white">
          <p class="font-semibold"><?= e($c['name']) ?></p>
          <p class="text-xs text-white/80"><?= $c['count'] ?> product<?= $c['count'] === 1 ? '' : 's' ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="container-page py-10">
  <div class="mb-6 flex items-end justify-between">
    <h2 class="text-2xl font-semibold tracking-tight">New arrivals</h2>
    <a href="<?= e(url('products.php', ['sort' => 'newest'])) ?>" class="text-sm font-medium text-ink-soft hover:text-ink">See more →</a>
  </div>
  <?php if ($newest): ?>
    <div class="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 lg:grid-cols-4">
      <?php foreach ($newest as $p) echo product_card($p); ?>
    </div>
  <?php else: ?>
    <p class="rounded-2xl border border-dashed border-line p-10 text-center text-ink-soft">Our catalogue is being updated — please check back in a moment.</p>
  <?php endif; ?>
</section>
<?php });
