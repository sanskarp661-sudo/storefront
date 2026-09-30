<?php
require __DIR__ . '/includes/bootstrap.php';

$order = get_order_for_viewer(get_string('id', 20), get_string('key', 64));
$placed = get_string('placed') === '1';
$customer = current_customer();
$accountError = null;

if ($order) {
    // A signed-in customer viewing their own guest order (same email) gets it added to their account.
    if ($customer && $order['customer_id'] === null && strtolower($order['customer_email']) === strtolower($customer['email'])) {
        db_query('UPDATE orders SET customer_id = ? WHERE id = ?', [$customer['id'], $order['id']]);
        $order['customer_id'] = $customer['id'];
    }
    // Post-purchase sign-up: the holder of the private order link can turn their details into an account.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_string('action', 20) === 'create_account' && !$customer && $order['customer_id'] === null) {
        require_csrf();
        $result = register_customer($order['customer_name'], $order['customer_email'], (string) $order['customer_phone'], (string) ($_POST['password'] ?? ''));
        if ($result['ok']) {
            db_query('UPDATE orders SET customer_id = ? WHERE id = ?', [$result['id'], $order['id']]);
            save_address($result['id'], ['label' => $order['ship_label'] ?: 'Home', 'name' => $order['customer_name'], 'phone' => (string) $order['customer_phone'],
                'address_line' => $order['ship_address_line'], 'city' => $order['ship_city'], 'state' => $order['ship_state'], 'pincode' => $order['ship_pincode']]);
            login_customer($result['id']);
            flash('success', 'Your account is ready — this order and your address are saved to it.');
            redirect(order_url($order['website_order_id'], $order['access_token']));
        }
        $accountError = $result['errors']['email'] ?? $result['errors']['password'] ?? reset($result['errors']);
    }
}

if (!$order) {
    http_response_code(404);
    render_page('Order not found', function () {
        echo '<div class="container-page max-w-2xl py-12">' . empty_state('search', "We couldn't find that order", 'The link may be incomplete. You can look up your order with its number and your email.', url('track.php'), 'Track an order') . '</div>';
    }, ['noindex' => true]);
    exit;
}

$items = load_order_items((int) $order['id']);
$ref = $order['erp_order_no'] ?: $order['website_order_id'];
$state = $order['submit_state'];
$delivered = $order['delivered_at'] !== null || $order['dn_status'] === 'delivered';
$method = payment_method($order['payment_method']);
$currency = $order['currency'];
$total = $order['erp_total_amount'] !== null ? (float) $order['erp_total_amount'] : (float) $order['subtotal_estimate'];
$paidInFull = $order['invoice_total'] !== null && (float) $order['invoice_total'] > 0
    && (float) $order['invoice_amount_paid'] >= (float) $order['invoice_total'];

render_page('Your order', function () use ($order, $items, $ref, $state, $placed, $delivered, $method, $currency, $total, $paidInFull, $customer, $accountError) {
    $banner = function (string $icon, string $tone, string $title, string $html) {
        $tones = ['neutral' => 'bg-white ring-line', 'warn' => 'bg-amber-50 text-amber-900 ring-amber-200', 'error' => 'bg-rose-50 text-rose-900 ring-rose-200'];
        echo '<div class="mb-6 flex gap-4 rounded-3xl p-6 ring-1 ' . $tones[$tone] . '"><div class="shrink-0">' . icon($icon, 'h-7 w-7') . '</div>'
            . '<div><h1 class="text-lg font-extrabold">' . e($title) . '</h1><p class="mt-1 text-sm opacity-90">' . $html . '</p></div></div>';
    };
    ?>
<div class="container-page max-w-6xl py-8">
  <?php if ($state === 'submitted' && $placed): ?>
    <div class="relative mb-6 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-emerald-500 to-teal-600 p-7 text-white shadow-lift sm:p-9">
      <div class="hero-grid absolute inset-0"></div>
      <div class="relative flex flex-wrap items-center gap-5">
        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white text-emerald-600 shadow-lg"><?= icon('circle-check', 'h-9 w-9') ?></span>
        <div class="flex-1">
          <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Order placed — thank you, <?= e(explode(' ', $order['customer_name'])[0]) ?>!</h1>
          <p class="mt-1 text-white/85">Order <span class="font-mono font-bold text-white"><?= e($ref) ?></span> is confirmed in our system. Pay in cash when it's delivered.</p>
        </div>
        <a href="<?= e(url('products.php')) ?>" class="btn bg-white text-emerald-700 hover:bg-emerald-50">Continue shopping</a>
      </div>
    </div>
  <?php elseif ($state === 'submitting'): ?>
    <?php $banner('clock', 'neutral', 'Confirming your order…', "We're confirming your order with our system. Refresh this page in a few seconds."); ?>
  <?php elseif ($state === 'unknown'): ?>
    <?php $banner('clock', 'warn', "We haven't been able to confirm this order yet", "Our order system didn't respond in time. We'll keep checking — refresh this page shortly. If it still isn't confirmed, <a href=\"" . e(url('checkout.php')) . "\" class=\"font-bold underline\">return to checkout</a> to try again (this won't create a duplicate order)."); ?>
  <?php elseif ($state === 'rejected'): ?>
    <?php $banner('triangle-alert', 'error', 'This order could not be placed', e($order['submit_error'] ?: 'Our system rejected this order.') . ' <a href="' . e(url('cart.php')) . '" class="font-bold underline">Review your cart</a>.'); ?>
  <?php endif; ?>

  <div class="card mb-6 flex flex-wrap items-center justify-between gap-4 p-6">
    <div>
      <p class="text-xs font-bold uppercase tracking-wider text-muted">Order</p>
      <h2 class="font-mono text-2xl font-extrabold"><?= e($ref) ?></h2>
      <p class="mt-1 text-sm text-ink-soft">Placed <?= e(format_date($order['order_date'] ?: $order['created_at'])) ?><?php if ($order['erp_order_no']): ?> · Reference <span class="font-mono"><?= e($order['website_order_id']) ?></span><?php endif; ?></p>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($state === 'submitted'): ?><?= order_status_pill($order['status']) ?><?php endif; ?>
      <?php if ($customer): ?><a href="<?= e(url('account.php', ['tab' => 'orders'])) ?>" class="btn btn-outline btn-sm"><?= icon('list-ordered', 'h-4 w-4') ?> All my orders</a><?php endif; ?>
    </div>
  </div>

  <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
    <div class="space-y-6">
      <?php if ($state === 'submitted'): ?>
        <section class="card p-6 sm:p-8">
          <h2 class="mb-7 text-lg font-extrabold">Order status</h2>
          <?php require __DIR__ . '/includes/templates/status_timeline.php'; ?>
          <?php if ($order['dn_no'] || $order['invoice_no']): ?>
            <div class="mt-8 grid gap-4 border-t border-line pt-6 sm:grid-cols-2">
              <?php if ($order['dn_no']): ?>
                <div class="flex gap-3 rounded-2xl bg-surface p-4"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand shadow-sm"><?= icon('truck', 'h-5 w-5') ?></span><div class="text-sm">
                  <p class="font-bold">Delivery <?= e($order['dn_no']) ?></p>
                  <p class="capitalize text-ink-soft"><?= e($order['dn_status'] ?: 'pending') ?><?= $order['delivered_at'] ? ' · ' . e(format_date($order['delivered_at'])) : '' ?></p>
                </div></div>
              <?php endif; ?>
              <?php if ($order['invoice_no']): ?>
                <div class="flex gap-3 rounded-2xl bg-surface p-4"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand shadow-sm"><?= icon('file-text', 'h-5 w-5') ?></span><div class="text-sm">
                  <p class="font-bold">Invoice <?= e($order['invoice_no']) ?></p>
                  <p class="text-ink-soft"><span class="capitalize"><?= e($order['invoice_status']) ?></span> · <?= e(format_price((float) $order['invoice_amount_paid'], $currency)) ?> paid of <?= e(format_price((float) $order['invoice_total'], $currency)) ?></p>
                </div></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="card overflow-hidden">
        <h2 class="border-b border-line px-6 py-4 text-lg font-extrabold">Items (<?= array_sum(array_map(fn($i) => (int) $i['quantity'], $items)) ?>)</h2>
        <ul class="divide-y divide-line">
          <?php foreach ($items as $it): ?>
            <li class="flex items-center gap-4 px-6 py-4">
              <a href="<?= e(product_url($it['sku'])) ?>" class="w-16 shrink-0 overflow-hidden rounded-xl border border-line"><?= product_image($it['image_url'], $it['name'], '', false, null, 'p-1.5') ?></a>
              <div class="min-w-0 flex-1">
                <a href="<?= e(product_url($it['sku'])) ?>" class="font-bold hover:text-brand"><?= e($it['name']) ?></a>
                <p class="text-sm text-ink-soft">Qty <?= (int) $it['quantity'] ?> × <?= e(format_price((float) $it['unit_price'], $currency)) ?></p>
              </div>
              <p class="font-extrabold"><?= e(format_price((float) $it['unit_price'] * (int) $it['quantity'], $currency)) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>

    <aside class="space-y-6">
      <?php if (!$customer && $order['customer_id'] === null && $state !== 'rejected'): ?>
        <section class="relative overflow-hidden rounded-[1.5rem] bg-ink p-6 text-white shadow-card">
          <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-brand/50 blur-2xl"></div>
          <div class="relative">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10"><?= icon('gift', 'h-5 w-5') ?></span>
            <h2 class="mt-4 text-lg font-extrabold">Save your details for next time</h2>
            <p class="mt-1 text-sm text-white/70">Set a password to create your account. This order and your address will be saved to it.</p>
            <form method="post" action="<?= e($_SERVER['REQUEST_URI']) ?>" class="mt-4 space-y-3">
              <?= csrf_field() ?><input type="hidden" name="action" value="create_account">
              <p class="rounded-xl bg-white/10 px-3 py-2 text-sm"><?= e($order['customer_email']) ?></p>
              <?php if ($accountError): ?><p class="text-sm font-semibold text-rose-300"><?= e($accountError) ?><?= str_contains((string) $accountError, 'already exists') ? ' <a class="underline" href="' . e(url('login.php', ['return' => $_SERVER['REQUEST_URI']])) . '">Sign in</a>' : '' ?></p><?php endif; ?>
              <input type="password" name="password" autocomplete="new-password" placeholder="Choose a password (8+ characters)" aria-label="Choose a password" class="field-input border-white/10 bg-white text-ink">
              <button class="btn btn-accent w-full">Create my account</button>
            </form>
          </div>
        </section>
      <?php endif; ?>
      <section class="card p-6 text-sm">
        <h2 class="mb-4 text-base font-extrabold">Payment summary</h2>
        <div class="flex justify-between"><span class="text-ink-soft">Order total (excl. GST)</span><span class="text-lg font-extrabold"><?= e(format_price($total, $currency)) ?></span></div>
        <?php if ($order['erp_total_amount'] === null): ?><p class="mt-1 text-xs text-ink-soft">Estimated — final amount is confirmed by our team.</p><?php endif; ?>
        <p class="mt-2 text-xs text-ink-soft">Applicable GST will be shown on your invoice.</p>
        <div class="mt-5 flex items-start gap-3 rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
          <span class="text-emerald-700"><?= icon('banknote', 'h-6 w-6') ?></span>
          <div><p class="font-bold text-emerald-900"><?= e($method['label'] ?? $order['payment_method']) ?></p>
            <p class="mt-0.5 text-xs text-emerald-800"><?= $paidInFull ? 'Paid in full.' : e($method['order_page_note'] ?? '') ?></p></div>
        </div>
      </section>
      <section class="card p-6 text-sm">
        <h2 class="mb-3 flex items-center gap-2 text-base font-extrabold"><?= icon('map-pin', 'h-5 w-5 text-brand') ?> Delivery address</h2>
        <address class="not-italic leading-relaxed text-ink-soft">
          <span class="font-bold text-ink"><?= e($order['customer_name']) ?></span><br>
          <?= e($order['ship_address_line']) ?><br>
          <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> <?= e($order['ship_pincode']) ?><br>
          <?= e($order['ship_country']) ?>
          <?php if ($order['customer_phone']): ?><br><?= e($order['customer_phone']) ?><?php endif; ?>
        </address>
        <?php if ($order['notes']): ?><h3 class="mb-1 mt-4 font-bold">Notes</h3><p class="whitespace-pre-line text-ink-soft"><?= e($order['notes']) ?></p><?php endif; ?>
      </section>
      <?php if (SUPPORT_EMAIL): ?>
        <a href="mailto:<?= e(SUPPORT_EMAIL) ?>?subject=<?= rawurlencode('Order ' . $ref) ?>" class="card flex items-center gap-3 p-5 text-sm transition hover:shadow-lift"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft text-brand"><?= icon('headset', 'h-5 w-5') ?></span><span><span class="block font-bold">Need help with this order?</span><span class="text-ink-soft"><?= e(SUPPORT_EMAIL) ?></span></span></a>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php }, ['noindex' => true]);
