<?php
require __DIR__ . '/includes/bootstrap.php';

$error = null;
$reference = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $reference = post_string('reference', 40);
    $email = post_string('email', 200);

    // Light brute-force protection: at most 10 lookups per session per 10 minutes.
    $now = time();
    $_SESSION['track_attempts'] = array_values(array_filter($_SESSION['track_attempts'] ?? [], fn($t) => $t > $now - 600));
    if (count($_SESSION['track_attempts']) >= 10) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } elseif (strlen($reference) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter your order number and the email you used at checkout.';
    } else {
        $_SESSION['track_attempts'][] = $now;
        $link = find_order_link($reference, $email);
        if ($link) redirect(order_url($link['website_order_id'], $link['access_token']));
        $error = "We couldn't find an order matching that number and email.";
    }
}

$recent = $_SESSION['recent_orders'] ?? [];

render_page('Track your order', function () use ($error, $reference, $email, $recent) {
    $customer = current_customer(); ?>
<div class="container-page py-8 lg:py-12">
  <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1.1fr_1fr]">
    <div class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-brand via-indigo-600 to-violet-700 p-8 text-white shadow-lift sm:p-10">
      <div class="hero-grid absolute inset-0"></div>
      <div class="relative">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur"><?= icon('truck', 'h-7 w-7') ?></span>
        <h1 class="mt-6 text-3xl font-extrabold tracking-tight sm:text-4xl">Track your order</h1>
        <p class="mt-2 max-w-sm text-white/80">Enter your order number and the email you used at checkout to see live status, delivery and invoice details.</p>
        <ul class="mt-8 space-y-3 text-sm">
          <?php foreach (['Your order number starts with SO- or WEB-', "It's shown on your order confirmation page", 'Status updates as soon as our team processes it'] as $t): ?>
            <li class="flex items-center gap-2"><span class="text-emerald-300"><?= icon('circle-check', 'h-5 w-5') ?></span> <?= e($t) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="space-y-6">
      <form method="post" action="<?= e(url('track.php')) ?>" class="card space-y-4 p-7">
        <?= csrf_field() ?>
        <?php if ($error): ?><p role="alert" class="flex items-center gap-2 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800"><?= icon('circle-alert', 'h-4 w-4 shrink-0') ?> <?= e($error) ?></p><?php endif; ?>
        <div>
          <label for="reference" class="field-label">Order number</label>
          <input id="reference" name="reference" required value="<?= e($reference) ?>" placeholder="SO-000123" autocapitalize="characters" class="field-input font-mono uppercase">
        </div>
        <div>
          <label for="email" class="field-label">Email</label>
          <input id="email" name="email" type="email" required value="<?= e($email) ?>" autocomplete="email" placeholder="you@example.com" class="field-input">
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-full"><?= icon('search', 'h-5 w-5') ?> Find my order</button>
        <?php if (!$customer): ?><p class="text-center text-sm text-ink-soft">Have an account? <a href="<?= e(url('login.php', ['return' => url('account.php', ['tab' => 'orders'])])) ?>" class="font-bold text-brand hover:underline">Sign in to see all your orders</a></p><?php endif; ?>
      </form>
      <?php if ($recent): ?>
        <section class="card overflow-hidden">
          <h2 class="border-b border-line px-6 py-4 font-extrabold">Orders placed in this browser</h2>
          <ul class="divide-y divide-line">
            <?php foreach (array_reverse($recent, true) as $id => $o): ?>
              <li><a href="<?= e(order_url((string) $id, $o['key'])) ?>" class="flex items-center justify-between px-6 py-4 text-sm hover:bg-slate-50">
                <span class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-soft text-brand"><?= icon('package', 'h-4 w-4') ?></span><span class="font-mono font-bold"><?= e((string) $id) ?></span></span>
                <span class="flex items-center gap-2 text-ink-soft"><?= e(format_date($o['placed_at'])) ?> <?= icon('chevron-right', 'h-4 w-4') ?></span>
              </a></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php });
