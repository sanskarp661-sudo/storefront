<?php
/** @var string $pageTitle @var bool $noindex @var string $description */
$navCategories = nav_categories();
$cartCount = defined('NO_SESSION') ? 0 : cart_count();
$flashes = defined('NO_SESSION') ? [] : take_flashes();
?><!doctype html>
<html lang="en-IN" class="h-full antialiased">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<link rel="icon" href="<?= e(url('favicon.ico')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/store.css')) ?>">
<script src="<?= e(asset('assets/js/store.js')) ?>" defer></script>
</head>
<body class="flex min-h-full flex-col font-sans">
<header class="sticky top-0 z-40 border-b border-line bg-paper/90 backdrop-blur">
  <div class="container-page flex h-16 items-center gap-4">
    <details class="mobile-nav lg:hidden">
      <summary class="-ml-2 inline-flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-full hover:bg-black/5" aria-label="Open menu">
        <?= icon('menu') ?>
      </summary>
      <div class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/30" data-close-menu></div>
        <div class="absolute inset-y-0 left-0 flex w-80 max-w-[85vw] flex-col gap-6 overflow-y-auto bg-paper p-6 shadow-xl">
          <div class="flex items-center justify-between">
            <span class="font-semibold">Menu</span>
            <button type="button" data-close-menu aria-label="Close menu" class="rounded-full p-2 hover:bg-black/5"><?= icon('x') ?></button>
          </div>
          <form action="<?= e(url('products.php')) ?>" class="relative" role="search">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft"><?= icon('search', 'h-4 w-4') ?></span>
            <input name="q" type="search" placeholder="Search products" aria-label="Search products" class="field-input pl-9">
          </form>
          <nav class="flex flex-col gap-1 text-[15px]">
            <a href="<?= e(url('products.php')) ?>" class="rounded-lg px-2 py-2 font-medium hover:bg-black/5">Shop all</a>
            <?php foreach ($navCategories as $c): ?>
              <a href="<?= e(category_url($c)) ?>" class="rounded-lg px-2 py-2 hover:bg-black/5"><?= e($c) ?></a>
            <?php endforeach; ?>
            <hr class="my-2 border-line">
            <a href="<?= e(url('track.php')) ?>" class="rounded-lg px-2 py-2 hover:bg-black/5">Track your order</a>
            <a href="<?= e(url('cart.php')) ?>" class="rounded-lg px-2 py-2 hover:bg-black/5">Cart</a>
          </nav>
        </div>
      </div>
    </details>

    <a href="<?= e(url('')) ?>" class="flex items-center gap-2.5 font-semibold tracking-tight">
      <?php require __DIR__ . '/logo.php'; ?>
      <span class="text-lg"><?= e(STORE_NAME) ?></span>
    </a>

    <nav class="ml-6 hidden items-center gap-6 text-sm font-medium text-ink-soft lg:flex">
      <a href="<?= e(url('products.php')) ?>" class="hover:text-ink">Shop all</a>
      <?php foreach (array_slice($navCategories, 0, 5) as $c): ?>
        <a href="<?= e(category_url($c)) ?>" class="hover:text-ink"><?= e($c) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="ml-auto flex items-center gap-1 sm:gap-2">
      <form action="<?= e(url('products.php')) ?>" class="relative hidden md:block" role="search">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft"><?= icon('search', 'h-4 w-4') ?></span>
        <input name="q" type="search" placeholder="Search products" aria-label="Search products"
               class="h-10 w-56 rounded-full border border-line bg-white pl-9 pr-4 text-sm outline-none focus:border-ink xl:w-72">
      </form>
      <a href="<?= e(url('track.php')) ?>" class="hidden h-10 items-center gap-2 rounded-full px-3 text-sm font-medium hover:bg-black/5 sm:inline-flex">
        <?= icon('truck', 'h-4 w-4') ?> Track order
      </a>
      <a href="<?= e(url('cart.php')) ?>" class="relative inline-flex h-10 w-10 items-center justify-center rounded-full hover:bg-black/5"
         aria-label="Cart<?= $cartCount ? ', ' . $cartCount . ' item' . ($cartCount === 1 ? '' : 's') : '' ?>">
        <?= icon('shopping-bag') ?>
        <?php if ($cartCount > 0): ?>
          <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-semibold text-white"><?= $cartCount > 99 ? '99+' : $cartCount ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>
</header>
<main class="flex-1">
<?php if ($flashes): ?>
  <div class="container-page mt-6 space-y-2">
    <?php foreach ($flashes as $f): ?>
      <div role="status" class="flex items-start gap-2 rounded-2xl border p-4 text-sm <?= $f['type'] === 'error' ? 'border-red-200 bg-red-50 text-red-800' : ($f['type'] === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-800') ?>">
        <?= icon($f['type'] === 'success' ? 'circle-check' : 'triangle-alert', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= e($f['message']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
