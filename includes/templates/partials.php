<?php
declare(strict_types=1);

/** Square product photo, or a placeholder when the ERP has no image. */
function product_image(?string $src, string $alt, string $class = '', bool $eager = false): string
{
    $html = '<div class="relative aspect-square overflow-hidden bg-[#f3efe9] ' . e($class) . '">';
    if ($src && preg_match('#^https?://#i', $src)) {
        $html .= '<img src="' . e($src) . '" alt="' . e($alt) . '" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async" class="absolute inset-0 h-full w-full object-cover">';
    } else {
        $html .= '<div class="absolute inset-0 flex items-center justify-center text-ink-soft/40">' . icon('package', 'h-1/4 w-1/4', 1.25)
            . '<span class="sr-only">' . e($alt) . '</span></div>';
    }
    return $html . '</div>';
}

function stock_badge(float $available, bool $compact = false): string
{
    if ($available <= 0) {
        return '<span class="rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Out of stock</span>';
    }
    if ($available <= 5) {
        $q = format_quantity($available);
        return '<span class="rounded-full bg-accent-soft px-2 py-0.5 text-xs font-medium text-accent-dark">' . ($compact ? "$q left" : "Only $q left") . '</span>';
    }
    return $compact ? '' : '<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">In stock</span>';
}

function product_card(array $p, bool $eager = false): string
{
    ob_start(); ?>
    <a href="<?= e(product_url($p['sku'])) ?>" class="group flex flex-col">
      <div class="overflow-hidden rounded-2xl border border-line bg-card">
        <?= product_image($p['image_url'], $p['name'], 'transition-transform duration-500 group-hover:scale-[1.03]', $eager) ?>
      </div>
      <div class="mt-3 flex flex-1 flex-col gap-1 px-0.5">
        <?php if ($p['brand']): ?><p class="text-xs font-medium uppercase tracking-wide text-ink-soft"><?= e($p['brand']) ?></p><?php endif; ?>
        <h3 class="line-clamp-2 text-[15px] font-medium leading-snug group-hover:underline group-hover:underline-offset-4"><?= e($p['name']) ?></h3>
        <div class="mt-auto flex items-center justify-between gap-2 pt-1">
          <p class="font-semibold"><?= e(format_price($p['price'], $p['currency'])) ?><?php if ($p['unit']): ?><span class="ml-1 text-xs font-normal text-ink-soft">/ <?= e($p['unit']) ?></span><?php endif; ?></p>
          <?= stock_badge($p['available'], true) ?>
        </div>
      </div>
    </a>
    <?php return (string) ob_get_clean();
}

function breadcrumbs(array $items): string
{
    $html = '<nav class="mb-6 text-sm text-ink-soft" aria-label="Breadcrumb">';
    $last = count($items) - 1;
    foreach ($items as $i => [$label, $href]) {
        $html .= $i < $last && $href
            ? '<a href="' . e($href) . '" class="hover:text-ink">' . e($label) . '</a> <span class="mx-1">/</span> '
            : '<span class="text-ink">' . e($label) . '</span>';
    }
    return $html . '</nav>';
}
