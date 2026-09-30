</main>
<footer class="mt-24 border-t border-line bg-white">
  <div class="container-page grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
    <div class="space-y-3">
      <a href="<?= e(url('')) ?>" class="flex items-center gap-2.5 font-semibold tracking-tight">
        <?php require __DIR__ . '/logo.php'; ?>
        <span class="text-lg"><?= e(STORE_NAME) ?></span>
      </a>
      <p class="max-w-xs text-sm text-ink-soft">Quality products, delivered across India.</p>
    </div>
    <div>
      <h3 class="mb-3 text-sm font-semibold">Shop</h3>
      <ul class="space-y-2 text-sm text-ink-soft">
        <li><a href="<?= e(url('products.php')) ?>" class="hover:text-ink">All products</a></li>
        <?php foreach (array_slice($navCategories, 0, 6) as $c): ?>
          <li><a href="<?= e(category_url($c)) ?>" class="hover:text-ink"><?= e($c) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h3 class="mb-3 text-sm font-semibold">Orders</h3>
      <ul class="space-y-2 text-sm text-ink-soft">
        <li><a href="<?= e(url('track.php')) ?>" class="hover:text-ink">Track your order</a></li>
        <li><a href="<?= e(url('cart.php')) ?>" class="hover:text-ink">Your cart</a></li>
      </ul>
    </div>
    <div>
      <h3 class="mb-3 text-sm font-semibold">Good to know</h3>
      <ul class="space-y-2 text-sm text-ink-soft">
        <li>Cash on Delivery available.</li>
        <li>Prices shown exclude GST. Applicable taxes are confirmed on your invoice.</li>
        <?php if (SUPPORT_EMAIL): ?>
          <li><a href="mailto:<?= e(SUPPORT_EMAIL) ?>" class="hover:text-ink"><?= e(SUPPORT_EMAIL) ?></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="border-t border-line">
    <div class="container-page py-5 text-xs text-ink-soft">© <?= gmdate('Y') ?> <?= e(STORE_NAME) ?>. All rights reserved.</div>
  </div>
</footer>
</body>
</html>
