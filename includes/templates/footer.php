</main>
<?php $customerF = defined('NO_SESSION') ? null : current_customer(); ?>
<section class="mt-20 border-y border-line bg-white">
  <div class="container-page grid grid-cols-2 gap-6 py-8 lg:grid-cols-4">
    <?php foreach ([
        ['banknote', 'Cash on Delivery', 'Pay when your order arrives'],
        ['shield-check', 'Genuine products', 'Sourced & invoiced with GST'],
        ['zap', 'Live stock', 'Availability straight from our warehouse'],
        ['truck', 'Track every step', 'From confirmation to your door'],
    ] as [$ic, $t, $d]): ?>
      <div class="flex items-center gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand"><?= icon($ic, 'h-5 w-5') ?></span>
        <div><p class="text-sm font-bold"><?= e($t) ?></p><p class="text-xs text-ink-soft"><?= e($d) ?></p></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<footer class="bg-ink text-white/70">
  <div class="container-page grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
    <div class="space-y-4">
      <a href="<?= e(url('')) ?>" class="flex items-center gap-2.5 text-white">
        <?php require __DIR__ . '/logo.php'; ?>
        <span class="text-xl font-extrabold tracking-tight"><?= e(STORE_NAME) ?></span>
      </a>
      <p class="max-w-xs text-sm leading-relaxed">Quality products with live stock, delivered across India. Pay cash on delivery and track your order online.</p>
      <?php if (SUPPORT_EMAIL): ?>
        <a href="mailto:<?= e(SUPPORT_EMAIL) ?>" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/15"><?= icon('mail', 'h-4 w-4') ?> <?= e(SUPPORT_EMAIL) ?></a>
      <?php endif; ?>
    </div>
    <div>
      <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-white">Shop</h3>
      <ul class="space-y-2.5 text-sm">
        <li><a href="<?= e(url('products.php')) ?>" class="hover:text-white">All products</a></li>
        <li><a href="<?= e(url('products.php', ['sort' => 'newest'])) ?>" class="hover:text-white">New arrivals</a></li>
        <?php foreach (array_slice($navCategories, 0, 5) as $c): ?><li><a href="<?= e(category_url($c)) ?>" class="hover:text-white"><?= e($c) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-white">Your account</h3>
      <ul class="space-y-2.5 text-sm">
        <?php if (!$customerF): ?>
          <li><a href="<?= e(url('login.php')) ?>" class="hover:text-white">Sign in</a></li>
          <li><a href="<?= e(url('register.php')) ?>" class="hover:text-white">Create account</a></li>
        <?php endif; ?>
        <li><a href="<?= e(url('account.php', ['tab' => 'orders'])) ?>" class="hover:text-white">My orders</a></li>
        <li><a href="<?= e(url('account.php', ['tab' => 'wishlist'])) ?>" class="hover:text-white">Wishlist</a></li>
        <li><a href="<?= e(url('account.php', ['tab' => 'addresses'])) ?>" class="hover:text-white">Saved addresses</a></li>
      </ul>
    </div>
    <div>
      <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-white">Help</h3>
      <ul class="space-y-2.5 text-sm">
        <li><a href="<?= e(url('track.php')) ?>" class="hover:text-white">Track your order</a></li>
        <li><a href="<?= e(url('cart.php')) ?>" class="hover:text-white">Your cart</a></li>
        <li class="pt-1 text-white/50">Prices exclude GST. Applicable taxes are shown on your invoice.</li>
      </ul>
    </div>
  </div>
  <div class="border-t border-white/10">
    <div class="container-page flex flex-col items-center justify-between gap-3 py-5 text-xs text-white/50 sm:flex-row">
      <p>© <?= gmdate('Y') ?> <?= e(STORE_NAME) ?>. All rights reserved.</p>
      <p class="flex items-center gap-2"><span class="rounded-md bg-white/10 px-2 py-1 font-semibold text-white/80">Cash on Delivery</span></p>
    </div>
  </div>
</footer>

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" aria-label="Quick links">
  <div class="grid h-16 grid-cols-5 text-[11px] font-semibold text-ink-soft">
    <?php foreach ([
        ['', null, 'house', 'Home', 'home'],
        ['products.php', null, 'layout-grid', 'Shop', 'all'],
        ['account.php', 'wishlist', 'heart', 'Wishlist', 'wishlist'],
        ['account.php', null, 'user', 'Account', 'account'],
        ['cart.php', null, 'shopping-cart', 'Cart', 'cart'],
    ] as [$page, $tab, $ic, $label, $key]): $on = ($activeNav ?? '') === $key; ?>
      <a href="<?= e(url($page, ['tab' => $tab])) ?>" class="relative flex flex-col items-center justify-center gap-1 <?= $on ? 'text-brand' : '' ?>">
        <span class="relative"><?= icon($ic, 'h-[22px] w-[22px]') ?><?= $key === 'cart' ? $badge($cartCount) : '' ?></span><?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
</body>
</html>
