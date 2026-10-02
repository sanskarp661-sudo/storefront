</main>
<?php $customerF = defined('NO_SESSION') ? null : current_customer(); $logoLight = true; ?>
<section class="mt-20 bg-linen">
  <div class="container-page grid grid-cols-2 gap-6 py-12 lg:grid-cols-4">
    <?php foreach ([
        ['banknote', 'Cash on delivery', 'Pay when your order arrives.'],
        ['shield-check', 'Genuine products', 'Supplied and invoiced with GST.'],
        ['zap', 'Live stock', 'Availability straight from our warehouse.'],
        ['truck', 'Track every step', 'From confirmation to your door.'],
    ] as [$ic, $t, $d]): ?>
      <div class="flex flex-col gap-1.5">
        <?= icon($ic, 'h-7 w-7', 1.4) ?>
        <p class="mt-1 font-semibold"><?= e($t) ?></p>
        <p class="text-[13px] text-ink-soft"><?= e($d) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<footer class="foot bg-[#141414] text-[#ede8e0]">
  <div class="container-page flex flex-col gap-14 pb-8 pt-16 lg:pt-[72px]">
    <div class="flex flex-wrap items-end justify-between gap-8 border-b border-white/10 pb-12">
      <div class="flex max-w-[520px] flex-col gap-2.5">
        <h2 class="h2 text-white">Questions? We’re here to help.</h2>
        <p class="text-sm text-[#b5aea4]">Write to us about an order, a product or delivery to your pincode.</p>
      </div>
      <div class="flex flex-wrap gap-2.5">
        <?php if (SUPPORT_EMAIL): ?><a href="mailto:<?= e(SUPPORT_EMAIL) ?>" class="btn btn-w btn-s !text-ink hover:!text-white"><?= icon('mail', 'h-4 w-4') ?> Email us</a><?php endif; ?>
        <a href="<?= e(url('track.php')) ?>" class="btn btn-s border-white/30 !text-white hover:border-white">Track an order</a>
      </div>
    </div>
    <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
      <div class="flex flex-col gap-3.5">
        <a href="<?= e(url('')) ?>" class="logo !text-white"><?php require __DIR__ . '/logo.php'; ?><span><?= e(STORE_NAME) ?></span></a>
        <p class="max-w-[30ch] text-[13px] text-[#b5aea4]">Live stock from our warehouse, delivered across India. Pay cash on delivery and track your order online.</p>
      </div>
      <nav class="flex flex-col gap-3" aria-label="Shop">
        <h5>Shop</h5>
        <a href="<?= e(url('products.php', ['sort' => 'newest'])) ?>">New in</a>
        <?php foreach (array_slice($navCategories, 0, 5) as $c): ?><a href="<?= e(category_url($c)) ?>"><?= e($c) ?></a><?php endforeach; ?>
        <a href="<?= e(url('products.php')) ?>">All products</a>
      </nav>
      <nav class="flex flex-col gap-3" aria-label="Your account">
        <h5>Your account</h5>
        <?php if (!$customerF): ?><a href="<?= e(url('login.php')) ?>">Sign in</a><a href="<?= e(url('register.php')) ?>">Create account</a><?php endif; ?>
        <a href="<?= e(url('account.php', ['tab' => 'orders'])) ?>">My orders</a>
        <a href="<?= e(url('account.php', ['tab' => 'wishlist'])) ?>">Wishlist</a>
        <a href="<?= e(url('account.php', ['tab' => 'addresses'])) ?>">Saved addresses</a>
      </nav>
      <nav class="flex flex-col gap-3" aria-label="Help">
        <h5>Help</h5>
        <a href="<?= e(url('track.php')) ?>">Track your order</a>
        <a href="<?= e(url('cart.php')) ?>">Your bag</a>
        <?php if (SUPPORT_EMAIL): ?><a href="mailto:<?= e(SUPPORT_EMAIL) ?>" class="break-all"><?= e(SUPPORT_EMAIL) ?></a><?php endif; ?>
        <p class="text-[13px] text-[#9c958b]">Prices exclude GST; taxes are shown on your invoice.</p>
      </nav>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-5 border-t border-white/10 pt-7">
      <div class="flex flex-wrap items-center gap-2"><span class="cap mr-1.5 text-[#9c958b]">We accept</span><span class="badge bg-white/10 text-white">Cash on delivery</span></div>
      <p class="text-xs text-[#9c958b]">© <?= gmdate('Y') ?> <?= e(STORE_NAME) ?></p>
    </div>
  </div>
</footer>

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Quick links">
  <div class="grid h-16 grid-cols-5 text-[11px] font-medium tracking-wide text-ink-soft">
    <?php foreach ([
        ['', null, 'house', 'Home', 'home'],
        ['products.php', null, 'layout-grid', 'Shop', 'all'],
        ['account.php', 'wishlist', 'heart', 'Wishlist', 'wishlist'],
        ['account.php', null, 'user', 'Account', 'account'],
        ['cart.php', null, 'shopping-bag', 'Bag', 'cart'],
    ] as [$page, $tab, $ic, $label, $key]): $on = ($activeNav ?? '') === $key; ?>
      <a href="<?= e(url($page, ['tab' => $tab])) ?>" class="relative flex flex-col items-center justify-center gap-1 <?= $on ? 'text-ink' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>>
        <span class="relative"><?= icon($ic, 'h-[22px] w-[22px]', 1.6) ?><?= $key === 'cart' && $cartCount ? '<span class="dot-n -right-2.5 -top-1.5">' . $cartCount . '</span>' : '' ?></span><?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
</body>
</html>
