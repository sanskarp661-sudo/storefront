<?php
/** @var string $pageTitle @var bool $noindex @var string $description @var string $activeNav */
$navCategories = nav_categories();
$cartCount = defined('NO_SESSION') ? 0 : cart_count();
$flashes = defined('NO_SESSION') ? [] : take_flashes();
$customer = defined('NO_SESSION') ? null : current_customer();
$wishCount = $customer ? count(wishlist_skus($customer)) : 0;
$q = get_string('q', 100);
$activeNav = $activeNav ?? '';
$badge = fn(int $n) => $n > 0 ? '<span class="absolute -right-1.5 -top-1.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold text-white ring-2 ring-white">' . ($n > 99 ? '99+' : $n) . '</span>' : '';
?><!doctype html>
<html lang="en-IN" class="h-full antialiased">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#4f46e5">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<link rel="icon" href="<?= e(url('favicon.ico')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/store.css')) ?>">
<script src="<?= e(asset('assets/js/store.js')) ?>" defer></script>
</head>
<body class="flex min-h-full flex-col font-sans pb-16 lg:pb-0" data-base="<?= e(base_path()) ?>">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>

<div class="bg-ink text-[12px] font-medium text-white/80">
  <div class="container-page flex h-9 items-center justify-center gap-6 sm:justify-between">
    <p class="flex items-center gap-2"><?= icon('banknote', 'h-4 w-4 text-accent') ?> Cash on Delivery available across India</p>
    <div class="hidden items-center gap-5 sm:flex">
      <a href="<?= e(url('track.php')) ?>" class="flex items-center gap-1.5 hover:text-white"><?= icon('truck', 'h-3.5 w-3.5') ?> Track order</a>
      <?php if (SUPPORT_EMAIL): ?><a href="mailto:<?= e(SUPPORT_EMAIL) ?>" class="flex items-center gap-1.5 hover:text-white"><?= icon('headset', 'h-3.5 w-3.5') ?> <?= e(SUPPORT_EMAIL) ?></a><?php endif; ?>
    </div>
  </div>
</div>

<header class="sticky top-0 z-40 bg-white shadow-[0_1px_0_rgb(15_23_42/0.06)]">
  <div class="container-page flex h-16 items-center gap-3 lg:h-20 lg:gap-6">
    <details class="mobile-menu lg:hidden">
      <summary class="-ml-2 flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl hover:bg-slate-100" aria-label="Open menu"><?= icon('menu', 'h-6 w-6') ?></summary>
      <div class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-ink/40 backdrop-blur-sm" data-close-menu></div>
        <aside class="absolute inset-y-0 left-0 flex w-[21rem] max-w-[88vw] flex-col overflow-y-auto bg-white shadow-2xl">
          <div class="bg-gradient-to-br from-brand to-violet-600 p-5 text-white">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2 font-bold"><?= icon('circle-user', 'h-6 w-6') ?> <?= $customer ? 'Hello, ' . e(explode(' ', $customer['name'])[0]) : 'Hello, sign in' ?></span>
              <button type="button" data-close-menu aria-label="Close menu" class="rounded-lg p-1.5 hover:bg-white/15"><?= icon('x') ?></button>
            </div>
            <?php if (!$customer): ?>
              <div class="mt-4 flex gap-2">
                <a href="<?= e(url('login.php')) ?>" class="btn btn-sm flex-1 bg-white text-brand">Sign in</a>
                <a href="<?= e(url('register.php')) ?>" class="btn btn-sm flex-1 border border-white/40 text-white">Create account</a>
              </div>
            <?php endif; ?>
          </div>
          <nav class="flex flex-col p-3 text-[15px]">
            <p class="px-3 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wider text-muted">Shop by category</p>
            <a href="<?= e(url('products.php')) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold hover:bg-slate-50"><?= icon('layout-grid', 'h-5 w-5 text-brand') ?> All products</a>
            <?php foreach ($navCategories as $c): ?>
              <a href="<?= e(category_url($c)) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-slate-50"><?= icon(category_icon($c), 'h-5 w-5 text-brand') ?> <?= e($c) ?></a>
            <?php endforeach; ?>
            <p class="px-3 pb-1 pt-5 text-[11px] font-bold uppercase tracking-wider text-muted">Your account</p>
            <a href="<?= e(url('account.php')) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-slate-50"><?= icon('user', 'h-5 w-5 text-ink-soft') ?> My account</a>
            <a href="<?= e(url('account.php', ['tab' => 'orders'])) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-slate-50"><?= icon('package', 'h-5 w-5 text-ink-soft') ?> My orders</a>
            <a href="<?= e(url('account.php', ['tab' => 'wishlist'])) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-slate-50"><?= icon('heart', 'h-5 w-5 text-ink-soft') ?> Wishlist</a>
            <a href="<?= e(url('track.php')) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-slate-50"><?= icon('truck', 'h-5 w-5 text-ink-soft') ?> Track an order</a>
          </nav>
        </aside>
      </div>
    </details>

    <a href="<?= e(url('')) ?>" class="flex shrink-0 items-center gap-2.5" aria-label="<?= e(STORE_NAME) ?> home">
      <?php require __DIR__ . '/logo.php'; ?>
      <span class="text-lg font-extrabold tracking-tight lg:text-xl"><?= e(STORE_NAME) ?></span>
    </a>

    <form action="<?= e(url('products.php')) ?>" class="search-box relative hidden flex-1 md:block" role="search" autocomplete="off">
      <div class="flex h-12 items-center rounded-2xl border-2 border-transparent bg-slate-100 transition focus-within:border-brand focus-within:bg-white focus-within:shadow-[0_0_0_4px_rgb(79_70_229/0.1)]">
        <span class="pl-4 text-muted"><?= icon('search', 'h-5 w-5') ?></span>
        <input name="q" type="search" value="<?= e($q) ?>" placeholder="Search for products, brands and more" aria-label="Search products" data-suggest
               class="h-full min-w-0 flex-1 bg-transparent px-3 text-[15px] outline-none placeholder:text-muted">
        <button type="submit" class="mr-1.5 hidden h-9 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark lg:block">Search</button>
      </div>
      <div class="suggest-panel absolute inset-x-0 top-full z-50 mt-2 hidden overflow-hidden rounded-2xl border border-line bg-white shadow-lift"></div>
    </form>

    <div class="ml-auto flex items-center gap-1 md:ml-0 lg:gap-2">
      <div class="group relative">
        <a href="<?= e(url($customer ? 'account.php' : 'login.php')) ?>" class="flex items-center gap-2.5 rounded-xl px-2 py-1.5 hover:bg-slate-100 lg:px-3">
          <span class="flex h-9 w-9 items-center justify-center rounded-full <?= $customer ? 'bg-brand text-sm font-bold text-white' : 'bg-slate-100 text-ink' ?>">
            <?= $customer ? e(initials($customer['name'])) : icon('user', 'h-5 w-5') ?>
          </span>
          <span class="hidden text-left leading-tight lg:block">
            <span class="block text-[11px] text-ink-soft"><?= $customer ? 'Hello, ' . e(explode(' ', $customer['name'])[0]) : 'Hello, sign in' ?></span>
            <span class="flex items-center gap-0.5 text-sm font-bold">Account & orders <?= icon('chevron-down', 'h-3.5 w-3.5') ?></span>
          </span>
        </a>
        <div class="invisible absolute right-0 top-full z-50 w-64 pt-2 opacity-0 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
          <div class="card overflow-hidden p-2">
            <?php if (!$customer): ?>
              <div class="p-3">
                <a href="<?= e(url('login.php')) ?>" class="btn btn-primary w-full">Sign in</a>
                <p class="mt-2 text-center text-xs text-ink-soft">New here? <a href="<?= e(url('register.php')) ?>" class="font-bold text-brand hover:underline">Create an account</a></p>
              </div>
              <hr class="my-1 border-line">
            <?php endif; ?>
            <?php foreach ([['account.php', null, 'user', 'My profile'], ['account.php', 'orders', 'package', 'My orders'], ['account.php', 'wishlist', 'heart', 'Wishlist'], ['account.php', 'addresses', 'map-pin', 'Saved addresses'], ['track.php', null, 'truck', 'Track an order']] as [$page, $tab, $ic, $label]): ?>
              <a href="<?= e(url($page, ['tab' => $tab])) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium hover:bg-slate-50"><?= icon($ic, 'h-4 w-4 text-ink-soft') ?> <?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($customer): ?>
              <hr class="my-1 border-line">
              <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"><?= icon('log-out', 'h-4 w-4') ?> Sign out</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <a href="<?= e(url('account.php', ['tab' => 'wishlist'])) ?>" class="relative hidden h-11 w-11 items-center justify-center rounded-xl hover:bg-slate-100 sm:flex" aria-label="Wishlist<?= $wishCount ? ", $wishCount items" : '' ?>">
        <?= icon('heart', 'h-6 w-6') ?><?= $badge($wishCount) ?>
      </a>
      <a href="<?= e(url('cart.php')) ?>" class="relative flex h-11 items-center gap-2 rounded-xl px-2.5 hover:bg-slate-100 lg:px-3" aria-label="Cart<?= $cartCount ? ", $cartCount items" : '' ?>">
        <span class="relative"><?= icon('shopping-cart', 'h-6 w-6') ?><?= $badge($cartCount) ?></span>
        <span class="hidden text-sm font-bold lg:block">Cart</span>
      </a>
    </div>
  </div>

  <div class="container-page pb-3 md:hidden">
    <form action="<?= e(url('products.php')) ?>" class="search-box relative" role="search" autocomplete="off">
      <div class="flex h-11 items-center rounded-xl bg-slate-100 focus-within:bg-white focus-within:ring-2 focus-within:ring-brand">
        <span class="pl-3.5 text-muted"><?= icon('search', 'h-5 w-5') ?></span>
        <input name="q" type="search" value="<?= e($q) ?>" placeholder="Search products" aria-label="Search products" data-suggest class="h-full min-w-0 flex-1 bg-transparent px-3 text-[15px] outline-none">
      </div>
      <div class="suggest-panel absolute inset-x-0 top-full z-50 mt-2 hidden overflow-hidden rounded-2xl border border-line bg-white shadow-lift"></div>
    </form>
  </div>

  <nav class="hidden border-t border-line/70 lg:block" aria-label="Categories">
    <div class="container-page flex h-12 items-center gap-1 overflow-x-auto text-sm font-semibold">
      <a href="<?= e(url('products.php')) ?>" class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 <?= $activeNav === 'all' ? 'bg-brand-soft text-brand' : 'text-ink hover:bg-slate-100' ?>"><?= icon('layout-grid', 'h-4 w-4') ?> All products</a>
      <?php foreach (array_slice($navCategories, 0, 8) as $c): ?>
        <a href="<?= e(category_url($c)) ?>" class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 <?= $activeNav === $c ? 'bg-brand-soft text-brand' : 'text-ink-soft hover:bg-slate-100 hover:text-ink' ?>"><?= icon(category_icon($c), 'h-4 w-4') ?> <?= e($c) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('products.php', ['sort' => 'newest'])) ?>" class="ml-auto flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-accent-dark hover:bg-accent-soft"><?= icon('sparkles', 'h-4 w-4') ?> New arrivals</a>
    </div>
  </nav>
</header>

<main id="main" class="flex-1">
<?php if ($flashes): ?>
  <div class="container-page mt-5 space-y-2">
    <?php foreach ($flashes as $f):
      $tone = ['error' => 'border-rose-200 bg-rose-50 text-rose-800', 'warning' => 'border-amber-200 bg-amber-50 text-amber-900'][$f['type']] ?? 'border-emerald-200 bg-emerald-50 text-emerald-800'; ?>
      <div role="status" class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-medium <?= $tone ?>">
        <?= icon($f['type'] === 'success' ? 'circle-check' : 'triangle-alert', 'h-5 w-5 shrink-0') ?>
        <span class="flex-1"><?= e($f['message']) ?></span>
        <?php if ($f['type'] === 'success' && str_contains($f['message'], 'cart')): ?><a href="<?= e(url('cart.php')) ?>" class="shrink-0 font-bold underline underline-offset-2">View cart</a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
