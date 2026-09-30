<?php
require __DIR__ . '/includes/bootstrap.php';

$q = get_string('q', 100);
$category = get_string('category');
$brand = get_string('brand');
$inStock = get_string('stock') === '1';
$sort = array_key_exists(get_string('sort'), PRODUCT_SORTS) ? get_string('sort') : 'featured';
$page = max(1, (int) get_string('page'));

$result = search_products(['q' => $q, 'category' => $category, 'brand' => $brand, 'in_stock' => $inStock, 'sort' => $sort, 'page' => $page, 'per_page' => 24]);
$categories = get_categories();
$brands = get_brands($category ?: null);

/** Current filters with overrides, as a products.php URL. */
$link = function (array $overrides = []) use ($q, $category, $brand, $inStock, $sort): string {
    return url('products.php', array_merge([
        'q' => $q, 'category' => $category, 'brand' => $brand,
        'stock' => $inStock ? '1' : null, 'sort' => $sort === 'featured' ? null : $sort,
    ], $overrides));
};
$filterClass = fn(bool $active) => 'block whitespace-nowrap rounded-full px-3 py-1.5 lg:rounded-lg '
    . ($active ? 'bg-ink text-white [&_span]:text-white/70' : 'border border-line bg-white hover:border-ink lg:border-transparent lg:bg-transparent');

$heading = $q !== '' ? "Results for “{$q}”" : ($category ?: 'All products');

render_page($q !== '' ? "Search: $q" : ($category ?: 'Shop all'), function () use ($q, $category, $brand, $inStock, $sort, $page, $result, $categories, $brands, $link, $filterClass, $heading) { ?>
<div class="container-page py-10">
  <?= breadcrumbs($category ? [['Home', url('')], ['Shop', url('products.php')], [$category, null]] : [['Home', url('')], ['Shop', null]]) ?>

  <div class="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-6">
    <div>
      <h1 class="text-3xl font-semibold tracking-tight"><?= e($heading) ?></h1>
      <p class="mt-1 text-sm text-ink-soft"><?= $result['total'] ?> product<?= $result['total'] === 1 ? '' : 's' ?></p>
    </div>
    <form method="get" action="<?= e(url('products.php')) ?>" class="flex items-center gap-2 text-sm">
      <?php foreach (['q' => $q, 'category' => $category, 'brand' => $brand, 'stock' => $inStock ? '1' : ''] as $k => $v): if ($v !== '') { ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
      <?php } endforeach; ?>
      <label for="sort" class="text-ink-soft">Sort by</label>
      <select id="sort" name="sort" data-autosubmit class="rounded-full border border-line bg-white px-3 py-2 font-medium outline-none focus:border-ink">
        <?php foreach (['featured' => 'Featured', 'newest' => 'Newest', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'name' => 'Name: A–Z'] as $v => $label): ?>
          <option value="<?= e($v) ?>"<?= $sort === $v ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-outline px-3 py-2">Apply</button></noscript>
    </form>
  </div>

  <div class="mt-8 grid gap-10 lg:grid-cols-[220px_1fr]">
    <aside class="space-y-8 text-sm">
      <div>
        <h2 class="mb-3 font-semibold">Category</h2>
        <ul class="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:gap-1 lg:overflow-visible">
          <li><a href="<?= e($link(['category' => null, 'brand' => null])) ?>" class="<?= $filterClass($category === '') ?>">All</a></li>
          <?php foreach ($categories as $c): ?>
            <li><a href="<?= e($link(['category' => $c['name'], 'brand' => null])) ?>" class="<?= $filterClass($category === $c['name']) ?>"><?= e($c['name']) ?> <span class="text-ink-soft">(<?= $c['count'] ?>)</span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php if (count($brands) > 1): ?>
        <div>
          <h2 class="mb-3 font-semibold">Brand</h2>
          <ul class="flex flex-wrap gap-2 lg:flex-col lg:gap-1">
            <?php foreach ($brands as $b): ?>
              <li><a href="<?= e($link(['brand' => $brand === $b ? null : $b])) ?>" class="<?= $filterClass($brand === $b) ?>"><?= e($b) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <div>
        <h2 class="mb-3 font-semibold">Availability</h2>
        <a href="<?= e($link(['stock' => $inStock ? null : '1'])) ?>" class="inline-flex items-center gap-2">
          <span class="flex h-5 w-9 items-center rounded-full p-0.5 transition-colors <?= $inStock ? 'bg-ink' : 'bg-stone-300' ?>">
            <span class="h-4 w-4 rounded-full bg-white transition-transform <?= $inStock ? 'translate-x-4' : '' ?>"></span>
          </span>
          In stock only
        </a>
      </div>
    </aside>

    <div>
      <?php if ($result['products']): ?>
        <div class="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 xl:grid-cols-4">
          <?php foreach ($result['products'] as $i => $p) echo product_card($p, $i < 4); ?>
        </div>
      <?php else: ?>
        <div class="rounded-2xl border border-dashed border-line p-12 text-center">
          <p class="font-medium">No products found</p>
          <p class="mt-1 text-sm text-ink-soft">Try a different search or clear your filters.</p>
          <a href="<?= e(url('products.php')) ?>" class="btn btn-outline mt-5">Clear filters</a>
        </div>
      <?php endif; ?>

      <?php if ($result['page_count'] > 1): ?>
        <nav class="mt-12 flex items-center justify-center gap-2" aria-label="Pagination">
          <?php if ($page > 1): ?><a href="<?= e($link(['page' => $page - 1])) ?>" class="btn btn-outline px-4 py-2">← Previous</a><?php endif; ?>
          <span class="px-3 text-sm text-ink-soft">Page <?= $page ?> of <?= $result['page_count'] ?></span>
          <?php if ($page < $result['page_count']): ?><a href="<?= e($link(['page' => $page + 1])) ?>" class="btn btn-outline px-4 py-2">Next →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php });
