<?php
/** @var string $pageTitle @var bool $noindex @var string $description @var string $activeNav */
$navTree = nav_tree();
$navCategories = array_column($navTree, 'name');
$cartCount = defined('NO_SESSION') ? 0 : cart_count();
$flashes = defined('NO_SESSION') ? [] : take_flashes();
$customer = defined('NO_SESSION') ? null : current_customer();
$wishCount = $customer ? count(wishlist_skus($customer)) : 0;
$q = get_string('q', 100);
$activeNav = $activeNav ?? '';
$badge = fn(int $n) => $n > 0 ? '<span class="dot-n">' . ($n > 99 ? '99+' : $n) . '</span>' : '';
?><!doctype html>
<html lang="en-IN" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#111111">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<link rel="icon" href="<?= e(url('favicon.ico')) ?>">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('assets/icons/apple-touch-icon.png')) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(STORE_NAME) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/store.css')) ?>">
<script src="<?= e(asset('assets/js/store.js')) ?>" defer></script>
</head>
<body class="flex min-h-full flex-col font-sans pb-16 lg:pb-0" data-base="<?= e(base_path()) ?>">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>

<div class="bg-[#141414] text-[12px] tracking-[.06em] text-[#ede8e0]">
  <div class="container-page flex min-h-[38px] items-center justify-center gap-3 sm:justify-between">
    <a href="<?= e(url('track.php')) ?>" class="hidden text-[#cfc8bd] hover:text-white sm:inline">Track your order</a>
    <span>Cash on delivery available across India</span>
    <?php if (SUPPORT_EMAIL): ?><a href="mailto:<?= e(SUPPORT_EMAIL) ?>" class="hidden text-[#cfc8bd] hover:text-white sm:inline"><?= e(SUPPORT_EMAIL) ?></a><?php else: ?><span class="hidden sm:inline"></span><?php endif; ?>
  </div>
</div>

<header class="sticky top-0 z-40 border-b border-line bg-surface">
  <div class="container-page grid min-h-16 grid-cols-[1fr_auto_1fr] items-center gap-4 lg:min-h-[76px]">
    <div class="flex items-center gap-1.5">
      <details class="mobile-menu lg:hidden">
        <summary class="icon-btn ghost -ml-2 cursor-pointer" aria-label="Open menu"><?= icon('menu', 'h-5 w-5', 1.6) ?></summary>
        <div class="fixed inset-0 z-50">
          <div class="absolute inset-0 bg-ink/50" data-close-menu></div>
          <aside class="absolute inset-y-0 left-0 flex w-[21rem] max-w-[88vw] flex-col overflow-y-auto bg-surface shadow-lift">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
              <a href="<?= e(url('')) ?>" class="logo !text-2xl"><?php require __DIR__ . '/logo.php'; ?><span><?= e(STORE_NAME) ?></span></a>
              <button type="button" data-close-menu aria-label="Close menu" class="icon-btn ghost"><?= icon('x', 'h-5 w-5') ?></button>
            </div>
            <div class="border-b border-line px-5 py-4">
              <?php if ($customer): ?>
                <p class="text-sm">Hello, <b><?= e(explode(' ', $customer['name'])[0]) ?></b></p>
              <?php else: ?>
                <div class="grid grid-cols-2 gap-2">
                  <a href="<?= e(url('login.php')) ?>" class="btn btn-p btn-s">Sign in</a>
                  <a href="<?= e(url('register.php')) ?>" class="btn btn-l btn-s">Join</a>
                </div>
              <?php endif; ?>
            </div>
            <nav class="flex flex-col px-5 py-3" aria-label="Shop">
              <p class="cap py-2 text-ink-soft">Shop</p>
              <a href="<?= e(url('products.php', ['sort' => 'newest'])) ?>" class="flex min-h-12 items-center justify-between border-b border-line font-medium">New in <?= icon('chevron-right', 'h-4 w-4 text-ink-soft') ?></a>
              <?php foreach ($navTree as $c): ?>
                <a href="<?= e(category_url($c['name'])) ?>" class="flex min-h-12 items-center justify-between border-b border-line font-medium"><?= e($c['name']) ?> <?= icon('chevron-right', 'h-4 w-4 text-ink-soft') ?></a>
                <?php foreach ($c['subs'] as $sc): ?>
                  <a href="<?= e(url('products.php', ['category' => $c['name'], 'sub' => $sc['name']])) ?>" class="flex min-h-10 items-center border-b border-line pl-4 text-sm text-ink-soft"><?= e($sc['name']) ?></a>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <a href="<?= e(url('products.php')) ?>" class="flex min-h-12 items-center justify-between border-b border-line font-medium">All products <?= icon('chevron-right', 'h-4 w-4 text-ink-soft') ?></a>
              <p class="cap pb-2 pt-6 text-ink-soft">Your account</p>
              <?php foreach ([['account.php', null, 'My account'], ['account.php', 'orders', 'My orders'], ['account.php', 'wishlist', 'Wishlist'], ['track.php', null, 'Track an order']] as [$page, $tab, $label]): ?>
                <a href="<?= e(url($page, ['tab' => $tab])) ?>" class="flex min-h-11 items-center border-b border-line text-sm"><?= e($label) ?></a>
              <?php endforeach; ?>
            </nav>
          </aside>
        </div>
      </details>
      <form action="<?= e(url('products.php')) ?>" class="search-box relative hidden w-[260px] md:block" role="search" autocomplete="off">
        <div class="input-group bg-transparent">
          <span class="text-ink-soft"><?= icon('search', 'h-4 w-4') ?></span>
          <input name="q" type="search" value="<?= e($q) ?>" placeholder="Search products, brands…" aria-label="Search products" data-suggest>
        </div>
        <div class="suggest-panel absolute left-0 top-full z-50 mt-2 hidden w-[24rem] overflow-hidden rounded-2xl border border-line bg-white shadow-lift"></div>
      </form>
    </div>

    <a href="<?= e(url('')) ?>" class="logo !text-[24px] lg:!text-[28px]" aria-label="<?= e(STORE_NAME) ?> home">
      <?php require __DIR__ . '/logo.php'; ?><span><?= e(STORE_NAME) ?></span>
    </a>

    <div class="flex items-center justify-end gap-0.5">
      <div class="group relative hidden md:block">
        <a href="<?= e(url($customer ? 'account.php' : 'login.php')) ?>" class="icon-btn ghost" aria-label="<?= $customer ? 'Your account' : 'Sign in' ?>">
          <?php if ($customer): ?><span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-soft font-display text-[17px] text-accent"><?= e(initials($customer['name'])) ?></span><?php else: ?><?= icon('user', 'h-5 w-5', 1.6) ?><?php endif; ?>
        </a>
        <div class="invisible absolute right-0 top-full z-50 w-64 pt-2 opacity-0 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
          <div class="card p-2 shadow-lift">
            <?php if (!$customer): ?>
              <div class="p-3">
                <a href="<?= e(url('login.php')) ?>" class="btn btn-p w-full">Sign in</a>
                <p class="mt-3 text-center text-xs text-ink-soft">New here? <a href="<?= e(url('register.php')) ?>" class="font-semibold text-ink underline underline-offset-4">Create an account</a></p>
              </div>
              <hr class="my-1 border-line">
            <?php else: ?>
              <p class="px-3 pb-2 pt-2 text-sm">Hello, <b><?= e(explode(' ', $customer['name'])[0]) ?></b></p>
            <?php endif; ?>
            <?php foreach ([['account.php', null, 'My profile'], ['account.php', 'orders', 'My orders'], ['account.php', 'wishlist', 'Wishlist'], ['account.php', 'addresses', 'Saved addresses'], ['track.php', null, 'Track an order']] as [$page, $tab, $label]): ?>
              <a href="<?= e(url($page, ['tab' => $tab])) ?>" class="flex min-h-10 items-center rounded-md px-3 text-sm font-medium hover:bg-linen hover:text-ink"><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($customer): ?>
              <hr class="my-1 border-line">
              <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="flex min-h-10 w-full items-center rounded-md px-3 text-sm font-medium text-rust hover:bg-rust-soft">Sign out</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <a href="<?= e(url('account.php', ['tab' => 'wishlist'])) ?>" class="icon-btn ghost hidden md:inline-flex" aria-label="Wishlist<?= $wishCount ? ", $wishCount items" : '' ?>"><?= icon('heart', 'h-5 w-5', 1.6) ?><?= $badge($wishCount) ?></a>
      <a href="<?= e(url('cart.php')) ?>" class="icon-btn ghost" aria-label="Bag<?= $cartCount ? ", $cartCount items" : '' ?>"><?= icon('shopping-bag', 'h-5 w-5', 1.6) ?><?= $badge($cartCount) ?></a>
    </div>
  </div>

  <div class="container-page pb-3 md:hidden">
    <form action="<?= e(url('products.php')) ?>" class="search-box relative" role="search" autocomplete="off">
      <div class="input-group">
        <span class="text-ink-soft"><?= icon('search', 'h-4 w-4') ?></span>
        <input name="q" type="search" value="<?= e($q) ?>" placeholder="Search products, brands…" aria-label="Search products" data-suggest>
      </div>
      <div class="suggest-panel absolute inset-x-0 top-full z-50 mt-2 hidden overflow-hidden rounded-2xl border border-line bg-white shadow-lift"></div>
    </form>
  </div>

  <nav class="hidden border-t border-line lg:block" aria-label="Main">
    <div class="container-page flex min-h-12 items-center justify-center gap-2">
      <a href="<?= e(url('products.php', ['sort' => 'newest'])) ?>" class="nav-a<?= $activeNav === 'new' ? ' on' : '' ?>">New In</a>
      <?php foreach (array_slice($navTree, 0, 7) as $c): ?>
        <div class="group/m">
          <a href="<?= e(category_url($c['name'])) ?>" class="nav-a<?= $activeNav === $c['name'] ? ' on' : '' ?>"><?= e($c['name']) ?><?php if ($c['subs']): ?><?= icon('chevron-down', 'h-3.5 w-3.5 text-ink-soft') ?><?php endif; ?></a>
          <?php if ($c['subs']): /* Dropdown: the category's sub-categories (ERP Item Categories) */ ?>
            <div class="invisible absolute inset-x-0 top-full z-40 border-b border-line bg-white opacity-0 shadow-card transition group-hover/m:visible group-hover/m:opacity-100 group-focus-within/m:visible group-focus-within/m:opacity-100">
              <div class="container-page grid grid-cols-[1fr_1fr_1.3fr] gap-8 py-8">
                <div class="flex flex-col gap-2.5">
                  <span class="cap text-ink-soft"><?= e($c['name']) ?></span>
                  <a href="<?= e(category_url($c['name'])) ?>" class="font-medium">All <?= e($c['name']) ?> <span class="text-xs text-ink-soft"><?= $c['count'] ?></span></a>
                  <?php foreach ($c['subs'] as $sc): ?>
                    <a href="<?= e(url('products.php', ['category' => $c['name'], 'sub' => $sc['name']])) ?>"><?= e($sc['name']) ?> <span class="text-xs text-ink-soft"><?= $sc['count'] ?></span></a>
                  <?php endforeach; ?>
                </div>
                <div class="flex flex-col gap-2.5">
                  <span class="cap text-ink-soft">Shop by</span>
                  <a href="<?= e(url('products.php', ['category' => $c['name'], 'sort' => 'newest'])) ?>">New in</a>
                  <a href="<?= e(url('products.php', ['category' => $c['name'], 'stock' => '1'])) ?>">In stock now</a>
                  <a href="<?= e(url('products.php', ['category' => $c['name'], 'sort' => 'price-asc'])) ?>">Price: low to high</a>
                </div>
                <a href="<?= e(category_url($c['name'])) ?>" class="flex flex-col gap-2.5 text-ink">
                  <span class="stage !aspect-[4/3]"><?php if ($c['image_url']): ?><img src="<?= e($c['image_url']) ?>" alt="" loading="lazy"><?php endif; ?></span>
                  <span class="h4"><?= e($c['name']) ?></span>
                  <span class="text-[13px] text-ink-soft">Shop all <?= $c['count'] ?> style<?= $c['count'] === 1 ? '' : 's' ?> →</span>
                </a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <a href="<?= e(url('products.php')) ?>" class="nav-a<?= $activeNav === 'all' ? ' on' : '' ?>">All products</a>
    </div>
  </nav>
</header>

<main id="main" class="flex-1">
<?php if ($flashes): ?>
  <div class="container-page mt-5 space-y-2">
    <?php foreach ($flashes as $f):
      $tone = ['error' => 'err', 'warning' => 'warn'][$f['type']] ?? 'ok'; ?>
      <div role="status" class="alert <?= $tone ?> items-center">
        <?= icon($f['type'] === 'success' ? 'circle-check' : 'triangle-alert', 'h-5 w-5 shrink-0') ?>
        <span class="flex-1"><?= e($f['message']) ?></span>
        <?php if ($f['type'] === 'success' && str_contains($f['message'], 'cart')): ?><a href="<?= e(url('cart.php')) ?>" class="shrink-0 font-semibold underline underline-offset-4">View bag</a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
