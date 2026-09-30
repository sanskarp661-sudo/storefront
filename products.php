<?php
require __DIR__ . '/includes/bootstrap.php';

$q = get_string('q', 100);
$category = get_string('category');
$brand = get_string('brand');
$inStock = get_string('stock') === '1';
$sort = array_key_exists(get_string('sort'), PRODUCT_SORTS) ? get_string('sort') : 'featured';
$page = max(1, (int) get_string('page'));
$minPrice = is_numeric(get_string('min')) ? max(0, (float) get_string('min')) : null;
$maxPrice = is_numeric(get_string('max')) ? max(0, (float) get_string('max')) : null;

$result = search_products(['q' => $q, 'category' => $category, 'brand' => $brand, 'in_stock' => $inStock, 'sort' => $sort, 'page' => $page, 'per_page' => 24, 'min_price' => $minPrice, 'max_price' => $maxPrice]);
$categories = get_categories();
$brands = get_brands($category ?: null);
[$lo, $hi] = price_bounds($category ?: null);

$state = ['q' => $q, 'category' => $category, 'brand' => $brand, 'stock' => $inStock ? '1' : null, 'sort' => $sort === 'featured' ? null : $sort,
    'min' => $minPrice !== null ? (string) $minPrice : null, 'max' => $maxPrice !== null ? (string) $maxPrice : null];
$link = fn(array $overrides = []) => url('products.php', array_merge($state, ['page' => null], $overrides));

$chips = [];
if ($q !== '') $chips[] = ['“' . $q . '”', $link(['q' => null])];
if ($category) $chips[] = [$category, $link(['category' => null, 'brand' => null])];
if ($brand) $chips[] = [$brand, $link(['brand' => null])];
if ($minPrice !== null || $maxPrice !== null) $chips[] = [($minPrice !== null ? format_price($minPrice) : '₹0') . ' – ' . ($maxPrice !== null ? format_price($maxPrice) : 'any'), $link(['min' => null, 'max' => null])];
if ($inStock) $chips[] = ['In stock', $link(['stock' => null])];

$heading = $q !== '' ? "Results for “{$q}”" : ($category ?: 'All products');

render_page($q !== '' ? "Search: $q" : ($category ?: 'Shop all'), function () use ($q, $category, $brand, $inStock, $sort, $page, $result, $categories, $brands, $link, $heading, $chips, $minPrice, $maxPrice, $lo, $hi, $state) {
    $filters = function (string $idPrefix) use ($categories, $brands, $category, $brand, $inStock, $link, $minPrice, $maxPrice, $lo, $hi, $state) {
        $row = fn(bool $on, string $href, string $label, string $count = '') =>
            '<a href="' . e($href) . '" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm ' . ($on ? 'bg-brand-soft font-bold text-brand' : 'text-ink-soft hover:bg-slate-50 hover:text-ink') . '">'
            . '<span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 ' . ($on ? 'border-brand bg-brand text-white' : 'border-slate-300') . '">' . ($on ? icon('check', 'h-3 w-3', 3) : '') . '</span>'
            . '<span class="flex-1">' . e($label) . '</span>' . ($count !== '' ? '<span class="text-xs text-muted">' . e($count) . '</span>' : '') . '</a>';
        ob_start(); ?>
        <div class="space-y-6">
          <div>
            <h3 class="mb-2 px-3 text-xs font-bold uppercase tracking-wider text-muted">Category</h3>
            <?= $row($category === '', $link(['category' => null, 'brand' => null]), 'All categories') ?>
            <?php foreach ($categories as $c) echo $row($category === $c['name'], $link(['category' => $c['name'], 'brand' => null]), $c['name'], (string) $c['count']); ?>
          </div>
          <?php if ($brands): ?>
            <div>
              <h3 class="mb-2 px-3 text-xs font-bold uppercase tracking-wider text-muted">Brand</h3>
              <?php foreach ($brands as $b) echo $row($brand === $b, $link(['brand' => $brand === $b ? null : $b]), $b); ?>
            </div>
          <?php endif; ?>
          <form method="get" action="<?= e(url('products.php')) ?>" class="px-3">
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-muted">Price (₹)</h3>
            <?php foreach ($state as $k => $v): if ($v !== null && !in_array($k, ['min', 'max'], true)) { ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php } endforeach; ?>
            <div class="flex items-center gap-2">
              <input id="<?= $idPrefix ?>min" name="min" type="number" min="0" step="1" inputmode="numeric" value="<?= $minPrice !== null ? e((string) $minPrice) : '' ?>" placeholder="<?= e((string) floor($lo)) ?>" aria-label="Minimum price" class="field-input px-3 py-2 text-sm">
              <span class="text-muted">–</span>
              <input id="<?= $idPrefix ?>max" name="max" type="number" min="0" step="1" inputmode="numeric" value="<?= $maxPrice !== null ? e((string) $maxPrice) : '' ?>" placeholder="<?= e((string) ceil($hi)) ?>" aria-label="Maximum price" class="field-input px-3 py-2 text-sm">
            </div>
            <button class="btn btn-outline btn-sm mt-3 w-full">Apply price</button>
          </form>
          <div class="px-3">
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-muted">Availability</h3>
            <a href="<?= e($link(['stock' => $inStock ? null : '1'])) ?>" class="flex items-center justify-between text-sm font-medium">
              In stock only
              <span class="flex h-6 w-11 items-center rounded-full p-0.5 transition <?= $inStock ? 'bg-brand' : 'bg-slate-300' ?>"><span class="h-5 w-5 rounded-full bg-white shadow transition <?= $inStock ? 'translate-x-5' : '' ?>"></span></span>
            </a>
          </div>
        </div>
        <?php return (string) ob_get_clean();
    };
    ?>
<div class="container-page py-8">
  <?= breadcrumbs($category ? [['Home', url('')], ['Shop', url('products.php')], [$category, null]] : [['Home', url('')], ['Shop', null]]) ?>

  <?php if ($category && !$q): ?>
    <div class="relative mb-8 overflow-hidden rounded-[1.75rem] bg-gradient-to-br <?= tile_gradient($category) ?> p-7 text-white shadow-card sm:p-9">
      <div class="absolute -right-10 -top-10 h-48 w-48 rounded-full bg-white/15"></div>
      <span class="absolute bottom-0 right-8 hidden text-white/20 sm:block"><?= icon(category_icon($category), 'h-40 w-40', 1.25) ?></span>
      <p class="relative text-xs font-bold uppercase tracking-wider text-white/75">Category</p>
      <h1 class="relative mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl"><?= e($category) ?></h1>
      <p class="relative mt-2 text-sm text-white/80"><?= $result['total'] ?> product<?= $result['total'] === 1 ? '' : 's' ?> · Cash on Delivery available</p>
    </div>
  <?php else: ?>
    <div class="mb-6">
      <h1 class="text-3xl font-extrabold tracking-tight"><?= e($heading) ?></h1>
      <p class="mt-1 text-sm text-ink-soft"><?= $result['total'] ?> product<?= $result['total'] === 1 ? '' : 's' ?></p>
    </div>
  <?php endif; ?>

  <div class="grid gap-8 lg:grid-cols-[250px_1fr]">
    <aside class="hidden lg:block">
      <div class="card sticky top-36 p-3 py-5"><?= $filters('d') ?></div>
    </aside>

    <div>
      <div class="mb-5 flex flex-wrap items-center gap-3">
        <details class="filter-drawer lg:hidden">
          <summary class="btn btn-outline btn-sm cursor-pointer"><?= icon('sliders-horizontal', 'h-4 w-4') ?> Filters<?= $chips ? ' (' . count($chips) . ')' : '' ?></summary>
          <div class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-ink/40 backdrop-blur-sm" data-close-menu></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-white p-4 pb-8 shadow-2xl">
              <div class="mb-3 flex items-center justify-between px-3"><span class="text-lg font-extrabold">Filters</span><button type="button" data-close-menu class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close filters"><?= icon('x') ?></button></div>
              <?= $filters('m') ?>
            </div>
          </div>
        </details>
        <div class="flex flex-1 flex-wrap items-center gap-2">
          <?php foreach ($chips as [$label, $href]): ?>
            <a href="<?= e($href) ?>" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-ink shadow-sm ring-1 ring-line hover:ring-brand"><?= e($label) ?> <?= icon('x', 'h-3.5 w-3.5 text-muted') ?></a>
          <?php endforeach; ?>
          <?php if (count($chips) > 1): ?><a href="<?= e(url('products.php')) ?>" class="text-xs font-bold text-brand hover:underline">Clear all</a><?php endif; ?>
        </div>
        <form method="get" action="<?= e(url('products.php')) ?>" class="flex items-center gap-2 text-sm">
          <?php foreach ($state as $k => $v): if ($v !== null && $k !== 'sort') { ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php } endforeach; ?>
          <label for="sort" class="hidden text-ink-soft sm:block">Sort by</label>
          <select id="sort" name="sort" data-autosubmit class="rounded-xl border-1.5 border border-line bg-white px-3 py-2 font-semibold outline-none focus:border-brand">
            <?php foreach (['featured' => 'Featured', 'newest' => 'Newest first', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'name' => 'Name: A–Z'] as $v => $label): ?>
              <option value="<?= e($v) ?>"<?= $sort === $v ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-outline btn-sm">Apply</button></noscript>
        </form>
      </div>

      <?php if ($result['products']): ?>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
          <?php foreach ($result['products'] as $i => $p) echo product_card($p, $i < 4); ?>
        </div>
      <?php else: ?>
        <?= empty_state('search', 'No products match', $q !== '' ? "We couldn't find anything for “{$q}”. Try a different word or clear your filters." : 'Try removing a filter to see more products.', url('products.php'), 'Clear filters') ?>
      <?php endif; ?>

      <?php if ($result['page_count'] > 1): ?>
        <nav class="mt-10 flex items-center justify-center gap-2" aria-label="Pagination">
          <?php if ($page > 1): ?><a href="<?= e($link(['page' => $page - 1])) ?>" class="btn btn-outline btn-sm"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</a><?php endif; ?>
          <?php for ($n = max(1, $page - 2); $n <= min($result['page_count'], $page + 2); $n++): ?>
            <a href="<?= e($link(['page' => $n])) ?>" class="flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold <?= $n === $page ? 'bg-brand text-white' : 'bg-white text-ink ring-1 ring-line hover:ring-brand' ?>"><?= $n ?></a>
          <?php endfor; ?>
          <?php if ($page < $result['page_count']): ?><a href="<?= e($link(['page' => $page + 1])) ?>" class="btn btn-outline btn-sm">Next <?= icon('chevron-right', 'h-4 w-4') ?></a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php }, ['active' => $category ?: 'all']);
