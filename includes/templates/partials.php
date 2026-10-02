<?php
declare(strict_types=1);

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
/**
 * Product photo, or a linen initials tile when there is none. $cover = true (product cards)
 * fills the design's 3:4 portrait "stage" edge to edge, anchored to the top so faces and
 * collars stay in view; otherwise the whole photo sits on white (cart thumbnails etc.).
 */
function product_image(?string $src, string $alt, string $class = '', bool $eager = false, ?string $category = null, string $padding = 'p-5', bool $cover = false): string
{
    $aspect = $cover ? 'aspect-[3/4]' : 'aspect-square';
    if ($src && preg_match('#^https?://#i', $src)) {
        $fit = $cover ? 'object-cover object-top' : 'object-contain ' . e($padding);
        return '<div class="relative ' . $aspect . ' overflow-hidden ' . ($cover ? 'bg-linen' : 'bg-white') . ' ' . e($class) . '">'
            . '<img src="' . e($src) . '" alt="' . e($alt) . '" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async" class="pc-main absolute inset-0 h-full w-full ' . $fit . '">'
            . '</div>';
    }
    return '<div class="relative ' . $aspect . ' flex flex-col items-center justify-center gap-2 overflow-hidden bg-linen text-ink-soft ' . e($class) . '" role="img" aria-label="' . e($alt) . '">'
        . '<span class="opacity-50">' . icon(category_icon($category), 'h-1/6 w-1/6', 1.2) . '</span>'
        . '<span class="font-display text-[clamp(1.4rem,4vw,2.6rem)] leading-none text-ink">' . e(initials($alt)) . '</span>'
        . '</div>';
}

function stock_badge(float $available, bool $compact = false): string
{
    if ($available <= 0) return '<span class="badge b-oos">Sold out</span>';
    if ($available <= 5) {
        $q = format_quantity($available);
        return '<span class="badge b-low">' . ($compact ? 'Few left' : "Only $q left") . '</span>';
    }
    return $compact ? '' : '<span class="badge b-ok">In stock</span>';
}

/** Heart button that adds/removes a product from the wishlist (sends guests to sign in). */
function wishlist_button(string $sku, string $class = ''): string
{
    $on = in_array($sku, wishlist_skus(), true);
    return '<form method="post" action="' . e(url('wishlist.php')) . '" class="' . e($class) . '">'
        . csrf_field()
        . '<input type="hidden" name="sku" value="' . e($sku) . '">'
        . '<input type="hidden" name="return" value="' . e($_SERVER['REQUEST_URI'] ?? '/') . '">'
        . '<button type="submit" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 transition hover:bg-white '
        . ($on ? 'text-rust' : 'text-ink hover:text-rust') . '" aria-label="' . ($on ? 'Remove from wishlist' : 'Add to wishlist') . '" aria-pressed="' . ($on ? 'true' : 'false') . '">'
        . '<span class="' . ($on ? '[&_path]:fill-current' : '') . '">' . icon('heart', 'h-5 w-5', 1.6) . '</span>'
        . '</button></form>';
}

function product_card(array $p, bool $eager = false): string
{
    $out = $p['available'] <= 0;
    $low = !$out && $p['available'] <= 5;
    $second = $p['images'][1] ?? null;
    ob_start(); ?>
    <article class="pc<?= $out ? ' oos' : '' ?>">
      <div class="pc-badges">
        <?php if ($low): ?><span class="badge b-low">Few left</span><?php endif; ?>
        <?php if ($out): ?><span class="badge b-oos">Sold out</span><?php endif; ?>
      </div>
      <?= wishlist_button($p['sku'], 'pc-wish') ?>
      <div class="stage">
        <a href="<?= e(product_url($p['sku'])) ?>" class="absolute inset-0" aria-label="<?= e($p['name']) ?>">
          <?php if ($p['image_url'] && preg_match('#^https?://#i', $p['image_url'])): ?>
            <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="<?= $eager ? 'eager' : 'lazy' ?>" decoding="async" class="pc-main absolute inset-0 h-full w-full object-cover object-top<?= $second ? ' [.pc:hover_&]:opacity-0' : '' ?>">
            <?php if ($second): /* second photo on hover, like most fashion stores */ ?>
              <img src="<?= e($second) ?>" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover object-top opacity-0 transition-opacity duration-500 [.pc:hover_&]:opacity-100">
            <?php endif; ?>
          <?php else: ?>
            <?= product_image(null, $p['name'], 'absolute inset-0 !aspect-auto', false, $p['category']) ?>
          <?php endif; ?>
        </a>
        <?php if (!$out): ?>
          <form method="post" action="<?= e(url('cart.php')) ?>" class="pc-quick">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add"><input type="hidden" name="sku" value="<?= e($p['sku']) ?>"><input type="hidden" name="quantity" value="1">
            <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
            <button type="submit" class="btn btn-w btn-s w-full" aria-label="Add <?= e($p['name']) ?> to bag">Add to bag</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="pc-body">
        <p class="cap truncate !text-[10px] text-ink-soft"><?= e($p['brand'] ?: ($p['sub_category'] ?? '') ?: ($p['category'] ?: STORE_NAME)) ?></p>
        <a href="<?= e(product_url($p['sku'])) ?>" class="pc-name line-clamp-2"><?= e($p['name']) ?></a>
        <p class="mt-0.5 flex flex-wrap items-baseline gap-x-2"><span class="price"><?= e(format_price($p['price'], $p['currency'])) ?></span><?php if ($p['unit']): ?><span class="text-[11px] text-ink-soft">per <?= e($p['unit']) ?></span><?php endif; ?></p>
      </div>
    </article>
    <?php return (string) ob_get_clean();
}

function breadcrumbs(array $items): string
{
    $html = '<nav class="crumbs mb-6" aria-label="Breadcrumb">';
    $last = count($items) - 1;
    foreach ($items as $i => [$label, $href]) {
        $html .= $i < $last && $href
            ? '<a href="' . e($href) . '">' . e($label) . '</a><span aria-hidden="true">/</span>'
            : '<b>' . e($label) . '</b>';
    }
    return $html . '</nav>';
}

function section_heading(string $title, ?string $subtitle = null, ?string $linkHref = null, string $linkLabel = 'View all', ?string $eyebrow = null): string
{
    return '<div class="mb-7 flex flex-wrap items-end justify-between gap-4"><div class="flex flex-col gap-2">'
        . ($eyebrow ? '<span class="eyebrow">' . e($eyebrow) . '</span>' : '')
        . '<h2 class="h2">' . e($title) . '</h2>'
        . ($subtitle ? '<p class="text-ink-soft">' . e($subtitle) . '</p>' : '')
        . '</div>'
        . ($linkHref ? '<a href="' . e($linkHref) . '" class="btn btn-o btn-s">' . e($linkLabel) . '</a>' : '')
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
    $cls = ['pending' => 'b-low', 'confirmed' => 'b-info', 'shipped' => 'b-info', 'completed' => 'b-ok', 'cancelled' => 'b-warn'][$status] ?? 'b-low';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}

/** Empty-state panel. */
function empty_state(string $iconName, string $title, string $text, string $ctaHref, string $ctaLabel): string
{
    return '<div class="flex flex-col items-center px-6 py-16 text-center">'
        . '<div class="flex h-[104px] w-[104px] items-center justify-center rounded-full bg-linen text-ink">' . icon($iconName, 'h-10 w-10', 1.3) . '</div>'
        . '<h2 class="h3 mt-5">' . e($title) . '</h2>'
        . '<p class="mt-2 max-w-sm text-sm text-ink-soft">' . e($text) . '</p>'
        . '<a href="' . e($ctaHref) . '" class="btn btn-p mt-6">' . e($ctaLabel) . '</a></div>';
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
        return '<div class="zoom-frame overflow-hidden rounded-[4px]" data-zoom>' . product_image($images[0] ?? $p['image_url'], $p['name'], '', true, $p['category'], 'p-8', true) . '</div>';
    }
    $n = count($images);
    $html = '<div class="relative" data-gallery>'
        . '<div class="gallery-track flex snap-x snap-mandatory overflow-x-auto rounded-[4px] outline-none focus-visible:ring-4 focus-visible:ring-accent/25" data-gallery-track tabindex="0" aria-label="Product photos">';
    foreach ($images as $i => $src) {
        $html .= '<div id="img-' . ($i + 1) . '" class="zoom-frame w-full shrink-0 snap-center overflow-hidden" data-zoom data-slide="' . $i . '">'
            . '<div class="relative aspect-[3/4] bg-linen"><img src="' . e($src) . '" alt="' . e($p['name']) . ' — photo ' . ($i + 1) . ' of ' . $n . '" '
            . 'loading="' . ($i === 0 ? 'eager' : 'lazy') . '" decoding="async" class="absolute inset-0 h-full w-full object-cover object-top"></div></div>';
    }
    $html .= '</div>'
        . '<button type="button" data-gallery-prev class="absolute left-3 top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-ink transition hover:bg-ink hover:text-white sm:flex" aria-label="Previous photo">' . icon('chevron-left', 'h-5 w-5') . '</button>'
        . '<button type="button" data-gallery-next class="absolute right-3 top-1/2 z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-ink transition hover:bg-ink hover:text-white sm:flex" aria-label="Next photo">' . icon('chevron-right', 'h-5 w-5') . '</button>'
        . '<span class="absolute bottom-3 left-1/2 z-10 -translate-x-1/2 rounded-full bg-ink/80 px-3 py-1 text-xs font-semibold tracking-wide text-white" data-gallery-count>1 / ' . $n . '</span>'
        . '</div>';
    return $html;
}
