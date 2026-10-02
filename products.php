<?php
require __DIR__ . '/includes/bootstrap.php';

$q = get_string('q', 100);
$category = get_string('category');
$sub = $category !== '' ? get_string('sub') : '';
$brand = get_string('brand');
$inStock = get_string('stock') === '1';
$sort = array_key_exists(get_string('sort'), PRODUCT_SORTS) ? get_string('sort') : 'featured';
$page = max(1, (int) get_string('page'));
$minPrice = is_numeric(get_string('min')) ? max(0, (float) get_string('min')) : null;
$maxPrice = is_numeric(get_string('max')) ? max(0, (float) get_string('max')) : null;
$perPage = 24;

$result = search_products(['q' => $q, 'category' => $category, 'sub' => $sub, 'brand' => $brand, 'in_stock' => $inStock, 'sort' => $sort, 'page' => $page, 'per_page' => $perPage, 'min_price' => $minPrice, 'max_price' => $maxPrice]);
$categories = get_categories();
$subcategories = $category ? get_subcategories($category) : [];
$brands = get_brands($category ?: null);
[$lo, $hi] = price_bounds($category ?: null);

$state = ['q' => $q, 'category' => $category, 'sub' => $sub ?: null, 'brand' => $brand, 'stock' => $inStock ? '1' : null, 'sort' => $sort === 'featured' ? null : $sort,
    'min' => $minPrice !== null ? (string) $minPrice : null, 'max' => $maxPrice !== null ? (string) $maxPrice : null];
$link = fn(array $overrides = []) => url('products.php', array_merge($state, ['page' => null], $overrides));

$chips = [];
if ($q !== '') $chips[] = ['“' . $q . '”', $link(['q' => null])];
if ($category) $chips[] = [$category, $link(['category' => null, 'sub' => null, 'brand' => null])];
if ($sub) $chips[] = [$sub, $link(['sub' => null])];
if ($brand) $chips[] = [$brand, $link(['brand' => null])];
if ($minPrice !== null || $maxPrice !== null) $chips[] = [($minPrice !== null ? format_price($minPrice) : '₹0') . ' – ' . ($maxPrice !== null ? format_price($maxPrice) : 'any'), $link(['min' => null, 'max' => null])];
if ($inStock) $chips[] = ['In stock', $link(['stock' => null])];

$heading = $q !== '' ? "Results for “{$q}”" : ($sub ?: ($category ?: ($sort === 'newest' ? 'New in' : 'All products')));
$activeNav = $category ?: ($sort === 'newest' && $q === '' ? 'new' : 'all');

render_page($q !== '' ? "Search: $q" : ($sub ? "$sub · $category" : ($category ?: 'Shop all')), function () use ($q, $category, $sub, $subcategories, $brand, $inStock, $sort, $page, $perPage, $result, $categories, $brands, $link, $heading, $chips, $minPrice, $maxPrice, $lo, $hi, $state) {
    $total = $result['total'];
    $from = $total ? ($page - 1) * $perPage + 1 : 0;
    $to = min($total, $page * $perPage);
    // A check-box style row that is really a link (filters work without JavaScript).
    $row = fn(bool $on, string $href, string $label, string $count = '', string $extra = '') =>
        '<a href="' . e($href) . '" class="check ' . $extra . ($on ? ' font-semibold' : '') . '"' . ($on ? ' aria-current="true"' : '') . '>'
        . '<span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-[2px] border ' . ($on ? 'border-ink bg-ink text-white' : 'border-[#8a847c] bg-white') . '">' . ($on ? icon('check', 'h-3 w-3', 3) : '') . '</span>'
        . '<span class="flex-1">' . e($label) . '</span>' . ($count !== '' ? '<span class="text-xs text-ink-soft">' . e($count) . '</span>' : '') . '</a>';
    $filters = function (string $idPrefix) use ($categories, $subcategories, $brands, $category, $sub, $brand, $inStock, $link, $minPrice, $maxPrice, $lo, $hi, $state, $chips, $row) {
        ob_start(); ?>
        <div class="flex flex-col" aria-label="Filters">
          <div class="flex items-center justify-between pb-3.5"><h2 class="cap">Filter</h2><?php if ($chips): ?><a href="<?= e(url('products.php', $state['q'] !== '' ? ['q' => $state['q']] : [])) ?>" class="btn btn-t btn-s">Clear all</a><?php endif; ?></div>
          <fieldset class="flex flex-col border-t border-line py-[18px]">
            <legend class="cap float-left mb-2.5 w-full">Category</legend>
            <?= $row($category === '', $link(['category' => null, 'sub' => null, 'brand' => null]), 'All categories') ?>
            <?php foreach ($categories as $c): ?>
              <?= $row($category === $c['name'] && !$sub, $link(['category' => $c['name'], 'sub' => null, 'brand' => null]), $c['name'], (string) $c['count']) ?>
              <?php if ($category === $c['name'] && $subcategories): ?>
                <div class="ml-2 border-l border-line pl-4">
                  <?php foreach ($subcategories as $sc) echo $row($sub === $sc['name'], $link(['sub' => $sub === $sc['name'] ? null : $sc['name']]), $sc['name'], (string) $sc['count']); ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </fieldset>
          <form method="get" action="<?= e(url('products.php')) ?>" class="flex flex-col gap-3 border-t border-line py-[18px]">
            <span class="cap">Price</span>
            <?php foreach ($state as $k => $v): if ($v !== null && !in_array($k, ['min', 'max'], true)) { ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php } endforeach; ?>
            <div class="flex items-end gap-2">
              <div class="flex-1"><label for="<?= $idPrefix ?>min" class="field-label !text-[10px]">Min ₹</label><input id="<?= $idPrefix ?>min" name="min" type="number" min="0" step="1" inputmode="numeric" value="<?= $minPrice !== null ? e((string) $minPrice) : '' ?>" placeholder="<?= e((string) floor($lo)) ?>" class="input !min-h-[42px]"></div>
              <div class="flex-1"><label for="<?= $idPrefix ?>max" class="field-label !text-[10px]">Max ₹</label><input id="<?= $idPrefix ?>max" name="max" type="number" min="0" step="1" inputmode="numeric" value="<?= $maxPrice !== null ? e((string) $maxPrice) : '' ?>" placeholder="<?= e((string) ceil($hi)) ?>" class="input !min-h-[42px]"></div>
            </div>
            <button class="btn btn-l btn-s w-full">Apply price</button>
          </form>
          <?php if ($brands): ?>
            <fieldset class="flex flex-col border-t border-line py-[18px]">
              <legend class="cap float-left mb-2.5 w-full">Brand</legend>
              <?php foreach ($brands as $b) echo $row($brand === $b, $link(['brand' => $brand === $b ? null : $b]), $b); ?>
            </fieldset>
          <?php endif; ?>
          <fieldset class="flex flex-col border-t border-line py-[18px]">
            <legend class="cap float-left mb-2.5 w-full">Availability</legend>
            <?= $row($inStock, $link(['stock' => $inStock ? null : '1']), 'In stock only') ?>
          </fieldset>
        </div>
        <?php return (string) ob_get_clean();
    };
    $sortForm = function (string $id) use ($state, $sort) {
        ob_start(); ?>
        <form method="get" action="<?= e(url('products.php')) ?>" class="flex items-center gap-2.5">
          <?php foreach ($state as $k => $v): if ($v !== null && $k !== 'sort') { ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php } endforeach; ?>
          <label for="<?= $id ?>" class="hidden text-[13px] font-semibold sm:block">Sort by</label>
          <select id="<?= $id ?>" name="sort" data-autosubmit class="input !min-h-[44px] w-full !rounded-full sm:w-[220px]">
            <?php foreach (['featured' => 'Recommended', 'newest' => 'Newest', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'name' => 'Name: A–Z'] as $v => $label): ?>
              <option value="<?= e($v) ?>"<?= $sort === $v ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-l btn-s">Apply</button></noscript>
        </form>
        <?php return (string) ob_get_clean();
    };
    ?>
<div class="container-page flex flex-col gap-7 pb-4 pt-7">
  <?= str_replace('mb-6', '', breadcrumbs($category
      ? array_merge([['Home', url('')], ['Shop', url('products.php')]], $sub ? [[$category, $link(['sub' => null])], [$sub, null]] : [[$category, null]])
      : [['Home', url('')], ['Shop', null]])) ?>

  <div class="flex flex-col gap-2">
    <?php if ($sub): ?><span class="cap text-ink-soft"><?= e($category) ?></span><?php endif; ?>
    <h1 class="h1"><?= e($heading) ?></h1>
    <p class="text-ink-soft"><?= $total ?> style<?= $total === 1 ? '' : 's' ?><?= $subcategories && !$sub ? ' · ' . e(implode(', ', array_slice(array_map('mb_strtolower', array_column($subcategories, 'name')), 0, 4))) . (count($subcategories) > 4 ? ' and more' : '') : '' ?></p>
  </div>

  <?php if ($subcategories): /* Shop-by-type: round photo tiles, like the design's sub-category row */ ?>
    <nav class="scroll-x -mx-1 gap-3.5 px-1 py-1" aria-label="<?= e($category) ?> types">
      <?php foreach (array_merge([['name' => '', 'label' => 'All', 'count' => null, 'image_url' => null]], $subcategories) as $sc):
          $on = $sub === $sc['name']; ?>
        <a href="<?= e($link(['sub' => $sc['name'] ?: null])) ?>"<?= $on ? ' aria-current="page"' : '' ?> class="flex w-[104px] flex-none flex-col items-center gap-2 text-center text-ink sm:w-[112px]">
          <span class="stage !aspect-square w-full !rounded-full ring-offset-2 ring-offset-surface <?= $on ? 'ring-2 ring-ink' : '' ?>">
            <?php if ($sc['image_url']): ?><img src="<?= e($sc['image_url']) ?>" alt="" loading="lazy"><?php else: ?><span class="absolute inset-0 flex items-center justify-center font-display text-2xl"><?= $sc['name'] === '' ? 'All' : e(initials($sc['name'])) ?></span><?php endif; ?>
          </span>
          <span class="text-[13px] <?= $on ? 'font-semibold underline underline-offset-4' : 'font-medium' ?>"><?= e($sc['label'] ?? $sc['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php elseif (!$category && $categories): ?>
    <nav class="scroll-x gap-2" aria-label="Categories">
      <a href="<?= e($link(['category' => null, 'sub' => null, 'brand' => null])) ?>" class="chip"<?= !$category ? ' aria-current="page"' : '' ?>>All</a>
      <?php foreach ($categories as $c): ?><a href="<?= e($link(['category' => $c['name'], 'sub' => null, 'brand' => null])) ?>" class="chip"><?= e($c['name']) ?></a><?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <div class="grid items-start gap-12 lg:grid-cols-[272px_minmax(0,1fr)]">
    <aside class="sticky top-40 hidden max-h-[calc(100vh-11rem)] overflow-y-auto pr-1 lg:block"><?= $filters('d') ?></aside>

    <div class="flex min-w-0 flex-col gap-5">
      <div class="sticky top-[118px] z-20 -mx-4 flex gap-2 bg-surface px-4 py-2 lg:hidden">
        <details class="filter-drawer flex-1">
          <summary class="btn btn-l btn-s w-full cursor-pointer"><?= icon('sliders-horizontal', 'h-4 w-4') ?> Filter<?= $chips ? ' · ' . count($chips) : '' ?></summary>
          <div class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-ink/50" data-close-menu></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-xl bg-surface px-5 pb-8 pt-3 shadow-lift">
              <div class="mx-auto mb-3 h-1 w-11 rounded-full bg-stone-2"></div>
              <div class="mb-1 flex items-center justify-between"><span class="h3">Filters</span><button type="button" data-close-menu class="icon-btn ghost" aria-label="Close filters"><?= icon('x', 'h-5 w-5') ?></button></div>
              <?= $filters('m') ?>
            </div>
          </div>
        </details>
        <div class="flex-1"><?= $sortForm('sort-m') ?></div>
      </div>

      <div class="hidden flex-wrap items-center justify-between gap-3 lg:flex">
        <p class="text-[13px] text-ink-soft"><?= $total ? 'Showing <b class="text-ink">' . $from . '–' . $to . '</b> of <b class="text-ink">' . $total . '</b> products' : 'No products' ?></p>
        <?= $sortForm('sort') ?>
      </div>

      <?php if ($chips): ?>
        <div class="flex flex-wrap items-center gap-2">
          <?php foreach ($chips as [$label, $href]): ?>
            <a href="<?= e($href) ?>" class="chip pine" aria-label="Remove filter <?= e($label) ?>"><?= e($label) ?> <?= icon('x', 'h-3.5 w-3.5') ?></a>
          <?php endforeach; ?>
          <?php if (count($chips) > 1): ?><a href="<?= e(url('products.php')) ?>" class="btn btn-t btn-s">Clear all</a><?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($result['products']): ?>
        <div class="grid grid-cols-2 gap-x-3 gap-y-8 md:grid-cols-3 lg:gap-x-5">
          <?php foreach ($result['products'] as $i => $p) echo product_card($p, $i < 3); ?>
        </div>
      <?php else: ?>
        <?= empty_state('search', 'No products match', $q !== '' ? "We couldn't find anything for “{$q}”. Try a different word or clear your filters." : 'Try removing a filter to see more products.', url('products.php'), 'Clear filters') ?>
      <?php endif; ?>

      <?php if ($result['page_count'] > 1): ?>
        <nav class="pager pt-4" aria-label="Pagination">
          <?php if ($page > 1): ?><a href="<?= e($link(['page' => $page - 1])) ?>" aria-label="Previous page">‹</a><?php endif; ?>
          <?php for ($n = max(1, $page - 2); $n <= min($result['page_count'], $page + 2); $n++): ?>
            <a href="<?= e($link(['page' => $n])) ?>"<?= $n === $page ? ' class="on" aria-current="page"' : '' ?>><?= $n ?></a>
          <?php endfor; ?>
          <?php if ($page < $result['page_count']): ?><a href="<?= e($link(['page' => $page + 1])) ?>" aria-label="Next page">›</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php }, ['active' => $activeNav]);
