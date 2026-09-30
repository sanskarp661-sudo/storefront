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

render_page('Track your order', function () use ($error, $reference, $email, $recent) { ?>
<div class="container-page max-w-xl py-16">
  <h1 class="text-3xl font-semibold tracking-tight">Track your order</h1>
  <p class="mt-2 text-ink-soft">Enter your order number (it starts with <span class="font-mono">SO-</span> or <span class="font-mono">WEB-</span>) and the email address you used at checkout.</p>

  <form method="post" action="<?= e(url('track.php')) ?>" class="mt-8 space-y-4 rounded-3xl border border-line bg-white p-6">
    <?= csrf_field() ?>
    <?php if ($error): ?><p role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-800"><?= e($error) ?></p><?php endif; ?>
    <div>
      <label for="reference" class="field-label">Order number</label>
      <input id="reference" name="reference" required value="<?= e($reference) ?>" placeholder="SO-000123" autocapitalize="characters" class="field-input font-mono uppercase">
    </div>
    <div>
      <label for="email" class="field-label">Email</label>
      <input id="email" name="email" type="email" required value="<?= e($email) ?>" autocomplete="email" class="field-input">
    </div>
    <button type="submit" class="btn btn-primary w-full"><?= icon('search', 'h-4 w-4') ?> Find my order</button>
  </form>

  <?php if ($recent): ?>
    <section class="mt-10">
      <h2 class="mb-3 font-semibold">Orders placed in this browser</h2>
      <ul class="divide-y divide-line rounded-3xl border border-line bg-white">
        <?php foreach (array_reverse($recent, true) as $id => $o): ?>
          <li><a href="<?= e(order_url((string) $id, $o['key'])) ?>" class="flex items-center justify-between px-5 py-4 text-sm hover:bg-black/[0.02]">
            <span class="font-mono"><?= e((string) $id) ?></span>
            <span class="flex items-center gap-2 text-ink-soft"><?= e(format_date($o['placed_at'])) ?> <?= icon('chevron-right', 'h-4 w-4') ?></span>
          </a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</div>
<?php });
