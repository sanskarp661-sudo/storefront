<?php
declare(strict_types=1);

/** Gradient palette used for image-less products and category tiles (picked by a stable hash). */
const TILE_GRADIENTS = [
    'from-indigo-500 to-violet-600',
    'from-orange-400 to-rose-500',
    'from-emerald-400 to-teal-600',
    'from-sky-400 to-blue-600',
    'from-fuchsia-500 to-pink-600',
    'from-amber-400 to-orange-500',
];

function tile_gradient(string $key): string
{
    return TILE_GRADIENTS[crc32($key) % count(TILE_GRADIENTS)];
}

/** Soft tinted backgrounds for products without a photo: [background, text colour, blob colour]. */
const TILE_TINTS = [
    ['from-indigo-50 to-violet-100', 'text-indigo-600', 'bg-indigo-200/40'],
    ['from-orange-50 to-rose-100', 'text-orange-600', 'bg-orange-200/40'],
    ['from-emerald-50 to-teal-100', 'text-emerald-600', 'bg-emerald-200/40'],
    ['from-sky-50 to-blue-100', 'text-sky-600', 'bg-sky-200/40'],
    ['from-fuchsia-50 to-pink-100', 'text-fuchsia-600', 'bg-fuchsia-200/40'],
    ['from-amber-50 to-orange-100', 'text-amber-600', 'bg-amber-200/50'],
];

/** A Lucide icon that fits a category name (falls back to a generic box). */
function category_icon(?string $category): string
{
    $c = mb_strtolower((string) $category);
    $map = [
        'laptop' => ['laptop', 'computer', 'notebook', 'macbook'],
        'smartphone' => ['mobile', 'phone', 'smartphone'],
        'headphones' => ['audio', 'headphone', 'earphone', 'speaker', 'sound'],
        'watch' => ['watch', 'wearable', 'clock'],
        'monitor' => ['monitor', 'display', 'electronic'],
        'tv' => ['tv', 'television'],
        'camera' => ['camera', 'photo'],
        'printer' => ['printer', 'office'],
        'pen-tool' => ['stationery', 'pen', 'pencil', 'paper'],
        'shirt' => ['fashion', 'cloth', 'apparel', 'wear', 'shirt'],
        'chef-hat' => ['kitchen', 'cook', 'food'],
        'sofa' => ['furniture', 'home', 'decor', 'living'],
        'wrench' => ['tool', 'hardware', 'repair'],
        'dumbbell' => ['sport', 'fitness', 'gym'],
        'book-open' => ['book', 'education'],
        'gamepad-2' => ['game', 'toy'],
        'baby' => ['baby', 'kid'],
        'sparkles' => ['beauty', 'cosmetic', 'care', 'personal'],
        'shopping-basket' => ['grocery', 'daily'],
        'gift' => ['gift'],
    ];
    foreach ($map as $icon => $words) {
        foreach ($words as $w) if (str_contains($c, $w)) return $icon;
    }
    return 'box';
}

function initials(string $name): string
{
    $words = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name)), -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach (array_slice($words, 0, 2) as $w) $out .= mb_strtoupper(mb_substr($w, 0, 1));
    return $out ?: '•';
}

/**
 * Product photo shown whole ("contain") on white, like leading electronics stores.
 * Without a photo: a branded gradient tile with the product's initials and category icon.
 */
function product_image(?string $src, string $alt, string $class = '', bool $eager = false, ?string $category = null, string $padding = 'p-5'): string
{
    if ($src && preg_match('#^https?://#i', $src)) {
        return '<div class="relative aspect-square overflow-hidden bg-white ' . e($class) . '">'
            . '<img src="' . e($src) . '" alt="' . e($alt) . '" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async" class="absolute inset-0 h-full w-full object-contain ' . e($padding) . '">'
            . '</div>';
    }
    [$bg, $fg, $blob] = TILE_TINTS[crc32($alt) % count(TILE_TINTS)];
    return '<div class="relative aspect-square overflow-hidden bg-gradient-to-br ' . $bg . ' ' . e($class) . '" role="img" aria-label="' . e($alt) . '">'
        . '<div class="absolute -right-6 -top-6 h-2/5 w-2/5 rounded-full ' . $blob . '"></div>'
        . '<div class="absolute -bottom-8 -left-4 h-1/2 w-1/2 rounded-full ' . $blob . '"></div>'
        . '<div class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 ' . $fg . '">'
        . '<span class="opacity-60">' . icon(category_icon($category), 'h-1/6 w-1/6', 1.5) . '</span>'
        . '<span class="text-[clamp(1.1rem,4vw,2.5rem)] font-extrabold tracking-tight">' . e(initials($alt)) . '</span>'
        . '</div></div>';
}

function stock_badge(float $available, bool $compact = false): string
{
    if ($available <= 0) {
        return '<span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">Out of stock</span>';
    }
    if ($available <= 5) {
        $q = format_quantity($available);
        return '<span class="inline-flex items-center gap-1 rounded-full bg-accent-soft px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-accent-dark">'
            . icon('flame', 'h-3 w-3') . ($compact ? "Only $q left" : "Hurry, only $q left") . '</span>';
    }
    return $compact ? '' : '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700">' . icon('circle-check', 'h-3 w-3') . 'In stock</span>';
}

/** Heart button that adds/removes a product from the wishlist (sends guests to sign in). */
function wishlist_button(string $sku, string $class = ''): string
{
    $on = in_array($sku, wishlist_skus(), true);
    return '<form method="post" action="' . e(url('wishlist.php')) . '" class="' . e($class) . '">'
        . csrf_field()
        . '<input type="hidden" name="sku" value="' . e($sku) . '">'
        . '<input type="hidden" name="return" value="' . e($_SERVER['REQUEST_URI'] ?? '/') . '">'
        . '<button type="submit" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 shadow-sm ring-1 ring-black/5 backdrop-blur transition hover:scale-110 '
        . ($on ? 'text-rose-500' : 'text-slate-500 hover:text-rose-500') . '" aria-label="' . ($on ? 'Remove from wishlist' : 'Add to wishlist') . '" aria-pressed="' . ($on ? 'true' : 'false') . '">'
        . '<span class="' . ($on ? '[&_path]:fill-current' : '') . '">' . icon('heart', 'h-[18px] w-[18px]') . '</span>'
        . '</button></form>';
}

function product_card(array $p, bool $eager = false): string
{
    $out = $p['available'] <= 0;
    ob_start(); ?>
    <div class="group relative flex flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-card transition duration-300 hover:-translate-y-1 hover:shadow-lift">
      <div class="absolute left-3 top-3 z-10"><?= stock_badge($p['available'], true) ?></div>
      <?= wishlist_button($p['sku'], 'absolute right-3 top-3 z-10') ?>
      <a href="<?= e(product_url($p['sku'])) ?>" class="block overflow-hidden">
        <div class="relative">
          <?= product_image($p['image_url'], $p['name'], 'transition duration-500 group-hover:scale-105' . (count($p['images'] ?? []) > 1 ? ' group-hover:opacity-0' : '') . ($out ? ' opacity-60 grayscale' : ''), $eager, $p['category']) ?>
          <?php if (count($p['images'] ?? []) > 1): /* second photo on hover, like most fashion/electronics stores */ ?>
            <img src="<?= e($p['images'][1]) ?>" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full bg-white object-contain p-5 opacity-0 transition duration-500 group-hover:scale-105 group-hover:opacity-100<?= $out ? ' grayscale' : '' ?>">
            <span class="absolute bottom-2 right-2 rounded-full bg-ink/70 px-2 py-0.5 text-[10px] font-bold text-white"><?= count($p['images']) ?> photos</span>
          <?php endif; ?>
        </div>
      </a>
      <div class="flex flex-1 flex-col gap-1.5 border-t border-line/70 p-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-brand"><?= e($p['brand'] ?: ($p['category'] ?: STORE_NAME)) ?></p>
        <a href="<?= e(product_url($p['sku'])) ?>" class="line-clamp-2 min-h-[2.5rem] text-[15px] font-semibold leading-tight text-ink hover:text-brand"><?= e($p['name']) ?></a>
        <div class="mt-auto flex items-end justify-between gap-2 pt-2">
          <p class="text-lg font-extrabold leading-none tracking-tight"><?= e(format_price($p['price'], $p['currency'])) ?>
            <?php if ($p['unit']): ?><span class="block pt-1 text-[11px] font-medium text-muted">per <?= e($p['unit']) ?> · excl. GST</span><?php endif; ?>
          </p>
          <?php if (!$out): ?>
            <form method="post" action="<?= e(url('cart.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add"><input type="hidden" name="sku" value="<?= e($p['sku']) ?>"><input type="hidden" name="quantity" value="1">
              <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
              <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft text-brand transition hover:bg-brand hover:text-white" aria-label="Add <?= e($p['name']) ?> to cart"><?= icon('shopping-cart', 'h-[18px] w-[18px]') ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php return (string) ob_get_clean();
}

function breadcrumbs(array $items): string
{
    $html = '<nav class="mb-5 flex flex-wrap items-center gap-1 text-sm text-ink-soft" aria-label="Breadcrumb">';
    $last = count($items) - 1;
    foreach ($items as $i => [$label, $href]) {
        $html .= $i < $last && $href
            ? '<a href="' . e($href) . '" class="hover:text-brand">' . e($label) . '</a>' . icon('chevron-right', 'h-3.5 w-3.5 text-muted')
            : '<span class="font-medium text-ink">' . e($label) . '</span>';
    }
    return $html . '</nav>';
}

function section_heading(string $title, ?string $subtitle = null, ?string $linkHref = null, string $linkLabel = 'View all'): string
{
    return '<div class="mb-6 flex items-end justify-between gap-4"><div>'
        . '<h2 class="text-2xl font-extrabold tracking-tight sm:text-[1.75rem]">' . e($title) . '</h2>'
        . ($subtitle ? '<p class="mt-1 text-sm text-ink-soft">' . e($subtitle) . '</p>' : '')
        . '</div>'
        . ($linkHref ? '<a href="' . e($linkHref) . '" class="inline-flex shrink-0 items-center gap-1 text-sm font-bold text-brand hover:gap-2 transition-all">' . e($linkLabel) . icon('arrow-right', 'h-4 w-4') . '</a>' : '')
        . '</div>';
}

/** Text input with label and error, for forms that keep values in $values / errors in $errors. */
function form_field(string $name, string $label, array $values, array $errors, array $attrs = [], string $class = '', string $hint = ''): string
{
    $attrHtml = '';
    foreach ($attrs as $k => $v) $attrHtml .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e((string) $v) . '"';
    $error = $errors[$name] ?? null;
    return '<div class="' . e($class) . '"><label class="field-label" for="' . e($name) . '">' . e($label) . '</label>'
        . '<input id="' . e($name) . '" name="' . e($name) . '" value="' . e((string) ($values[$name] ?? '')) . '" class="field-input"'
        . ($error ? ' aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '') . $attrHtml . '>'
        . ($error ? '<p id="' . e($name) . '-error" class="field-error">' . e($error) . '</p>' : ($hint ? '<p class="mt-1 text-xs text-ink-soft">' . e($hint) . '</p>' : ''))
        . '</div>';
}

function state_select(string $name, string $value, ?string $error): string
{
    $html = '<div><label class="field-label" for="' . e($name) . '">State</label><select id="' . e($name) . '" name="' . e($name) . '" class="field-input"' . ($error ? ' aria-invalid="true"' : '') . '>'
        . '<option value="" disabled' . ($value === '' ? ' selected' : '') . '>Select state</option>';
    foreach (INDIAN_STATES as $s) $html .= '<option value="' . e($s) . '"' . ($value === $s ? ' selected' : '') . '>' . e($s) . '</option>';
    return $html . '</select>' . ($error ? '<p class="field-error">' . e($error) . '</p>' : '') . '</div>';
}

function order_status_pill(string $status): string
{
    $cls = [
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'confirmed' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'shipped' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'cancelled' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ][$status] ?? 'bg-amber-50 text-amber-700 ring-amber-200';
    return '<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold capitalize ring-1 ' . $cls . '">' . e($status) . '</span>';
}

/** Empty-state panel. */
function empty_state(string $iconName, string $title, string $text, string $ctaHref, string $ctaLabel): string
{
    return '<div class="card flex flex-col items-center px-6 py-16 text-center">'
        . '<div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-brand-soft text-brand">' . icon($iconName, 'h-9 w-9', 1.75) . '</div>'
        . '<h2 class="mt-5 text-xl font-extrabold">' . e($title) . '</h2>'
        . '<p class="mt-1 max-w-sm text-sm text-ink-soft">' . e($text) . '</p>'
        . '<a href="' . e($ctaHref) . '" class="btn btn-primary mt-6">' . e($ctaLabel) . '</a></div>';
}

/**
 * Product photo gallery: a swipeable strip of full-size images (CSS scroll-snap,
 * works without JS) with prev/next arrows and a counter; thumbnails are rendered
 * by product.php below it. Each image zooms on hover.
 */
function product_gallery(array $p): string
{
    $images = $p['images'] ?? [];
    if (count($images) <= 1) {
        return '<div class="zoom-frame overflow-hidden rounded-2xl" data-zoom>' . product_image($images[0] ?? $p['image_url'], $p['name'], 'rounded-2xl', true, $p['category'], 'p-8') . '</div>';
    }
    $n = count($images);
    $html = '<div class="relative" data-gallery>'
        . '<div class="gallery-track flex snap-x snap-mandatory overflow-x-auto rounded-2xl outline-none focus-visible:ring-4 focus-visible:ring-brand/25" data-gallery-track tabindex="0" aria-label="Product photos">';
    foreach ($images as $i => $src) {
        $html .= '<div id="img-' . ($i + 1) . '" class="zoom-frame w-full shrink-0 snap-center overflow-hidden" data-zoom data-slide="' . $i . '">'
            . '<div class="relative aspect-square bg-white"><img src="' . e($src) . '" alt="' . e($p['name']) . ' — photo ' . ($i + 1) . ' of ' . $n . '" '
            . 'loading="' . ($i === 0 ? 'eager' : 'lazy') . '" decoding="async" class="absolute inset-0 h-full w-full object-contain p-8"></div></div>';
    }
    $html .= '</div>'
        . '<button type="button" data-gallery-prev class="absolute left-3 top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/95 text-ink shadow-md ring-1 ring-black/5 transition hover:scale-110 sm:flex" aria-label="Previous photo">' . icon('chevron-left', 'h-5 w-5') . '</button>'
        . '<button type="button" data-gallery-next class="absolute right-3 top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/95 text-ink shadow-md ring-1 ring-black/5 transition hover:scale-110 sm:flex" aria-label="Next photo">' . icon('chevron-right', 'h-5 w-5') . '</button>'
        . '<span class="absolute bottom-3 left-1/2 z-10 -translate-x-1/2 rounded-full bg-ink/75 px-3 py-1 text-xs font-bold text-white" data-gallery-count>1 / ' . $n . '</span>'
        . '</div>';
    return $html;
}
