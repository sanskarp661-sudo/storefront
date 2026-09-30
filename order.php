<?php
require __DIR__ . '/includes/bootstrap.php';

$order = get_order_for_viewer(get_string('id', 20), get_string('key', 64));
$placed = get_string('placed') === '1';

if (!$order) {
    http_response_code(404);
    render_page('Order not found', function () { ?>
      <div class="container-page max-w-xl py-20 text-center">
        <h1 class="text-2xl font-semibold">We couldn't find that order</h1>
        <p class="mt-2 text-ink-soft">The link may be incomplete. You can look up your order with its number and your email.</p>
        <a href="<?= e(url('track.php')) ?>" class="btn btn-primary mt-6">Track an order</a>
      </div>
    <?php }, ['noindex' => true]);
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

$pill = [
    'pending' => 'bg-amber-50 text-amber-800 border-amber-200',
    'confirmed' => 'bg-sky-50 text-sky-800 border-sky-200',
    'shipped' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
    'completed' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'cancelled' => 'bg-stone-100 text-stone-700 border-stone-300',
][$order['status']] ?? 'bg-amber-50 text-amber-800 border-amber-200';

render_page('Your order', function () use ($order, $items, $ref, $state, $placed, $delivered, $method, $currency, $total, $paidInFull, $pill) {
    $banner = function (string $icon, string $tone, string $title, string $html) {
        $tones = ['neutral' => 'border-line bg-white', 'warn' => 'border-amber-200 bg-amber-50 text-amber-900', 'error' => 'border-red-200 bg-red-50 text-red-900'];
        echo '<div class="mb-8 flex gap-4 rounded-3xl border p-6 ' . $tones[$tone] . '"><div class="shrink-0">' . icon($icon, 'h-6 w-6') . '</div>'
            . '<div><h1 class="text-lg font-semibold">' . e($title) . '</h1><p class="mt-1 text-sm opacity-90">' . $html . '</p></div></div>';
    };
    ?>
<div class="container-page max-w-5xl py-10">
  <?php if ($state === 'submitted' && $placed): ?>
    <div class="mb-8 flex items-start gap-4 rounded-3xl bg-ink p-6 text-white sm:p-8">
      <span class="text-emerald-400"><?= icon('circle-check', 'h-8 w-8 shrink-0') ?></span>
      <div>
        <h1 class="text-2xl font-semibold sm:text-3xl">Thank you, <?= e(explode(' ', $order['customer_name'])[0]) ?>!</h1>
        <p class="mt-1 text-white/75">Your order <span class="font-mono text-white"><?= e($ref) ?></span> has been received. Bookmark this page to track it any time, or look it up later with your order number and <?= e($order['customer_email']) ?>.</p>
      </div>
    </div>
  <?php elseif ($state === 'submitting'): ?>
    <?php $banner('clock', 'neutral', 'Confirming your order…', "We're confirming your order with our system. Refresh this page in a few seconds."); ?>
  <?php elseif ($state === 'unknown'): ?>
    <?php $banner('clock', 'warn', "We haven't been able to confirm this order yet", "Our order system didn't respond in time. We'll keep checking — refresh this page shortly. If it still isn't confirmed, <a href=\"" . e(url('checkout.php')) . "\" class=\"underline\">return to checkout</a> to try again (this won't create a duplicate order)."); ?>
  <?php elseif ($state === 'rejected'): ?>
    <?php $banner('triangle-alert', 'error', 'This order could not be placed', e($order['submit_error'] ?: 'Our system rejected this order.') . ' <a href="' . e(url('cart.php')) . '" class="underline">Review your cart</a>.'); ?>
  <?php endif; ?>

  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-sm text-ink-soft">Order</p>
      <h2 class="font-mono text-2xl font-semibold"><?= e($ref) ?></h2>
      <p class="mt-1 text-sm text-ink-soft">Placed <?= e(format_date($order['order_date'] ?: $order['created_at'])) ?><?php if ($order['erp_order_no']): ?> · Reference <span class="font-mono"><?= e($order['website_order_id']) ?></span><?php endif; ?></p>
    </div>
    <?php if ($state === 'submitted'): ?><span class="rounded-full border px-4 py-1.5 text-sm font-medium capitalize <?= $pill ?>"><?= e($order['status']) ?></span><?php endif; ?>
  </div>

  <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_340px]">
    <div class="space-y-8">
      <?php if ($state === 'submitted'): ?>
        <section class="rounded-3xl border border-line bg-white p-6">
          <h2 class="mb-6 font-semibold">Order status</h2>
          <?php require __DIR__ . '/includes/templates/status_timeline.php'; ?>
          <?php if ($order['dn_no'] || $order['invoice_no']): ?>
            <div class="mt-8 grid gap-4 border-t border-line pt-6 sm:grid-cols-2">
              <?php if ($order['dn_no']): ?>
                <div class="flex gap-3"><span class="mt-0.5 text-ink-soft"><?= icon('truck') ?></span><div class="text-sm">
                  <p class="font-medium">Delivery <?= e($order['dn_no']) ?></p>
                  <p class="capitalize text-ink-soft"><?= e($order['dn_status'] ?: 'pending') ?><?= $order['delivered_at'] ? ' · ' . e(format_date($order['delivered_at'])) : '' ?></p>
                </div></div>
              <?php endif; ?>
              <?php if ($order['invoice_no']): ?>
                <div class="flex gap-3"><span class="mt-0.5 text-ink-soft"><?= icon('file-text') ?></span><div class="text-sm">
                  <p class="font-medium">Invoice <?= e($order['invoice_no']) ?></p>
                  <p class="text-ink-soft"><span class="capitalize"><?= e($order['invoice_status']) ?></span> · <?= e(format_price((float) $order['invoice_amount_paid'], $currency)) ?> paid of <?= e(format_price((float) $order['invoice_total'], $currency)) ?></p>
                </div></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="rounded-3xl border border-line bg-white p-6">
        <h2 class="mb-4 font-semibold">Items</h2>
        <ul class="divide-y divide-line">
          <?php foreach ($items as $it): ?>
            <li class="flex items-center gap-4 py-4 first:pt-0 last:pb-0">
              <a href="<?= e(product_url($it['sku'])) ?>" class="w-16 shrink-0 overflow-hidden rounded-xl border border-line"><?= product_image($it['image_url'], $it['name']) ?></a>
              <div class="min-w-0 flex-1">
                <p class="font-medium"><?= e($it['name']) ?></p>
                <p class="text-sm text-ink-soft">Qty <?= (int) $it['quantity'] ?> · SKU <span class="font-mono"><?= e($it['sku']) ?></span></p>
              </div>
              <p class="text-sm font-medium"><?= e(format_price((float) $it['unit_price'] * (int) $it['quantity'], $currency)) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>

    <aside class="space-y-6">
      <section class="rounded-3xl border border-line bg-white p-6 text-sm">
        <h2 class="mb-4 text-base font-semibold">Summary</h2>
        <div class="flex justify-between"><span class="text-ink-soft">Order total (excl. GST)</span><span class="font-semibold"><?= e(format_price($total, $currency)) ?></span></div>
        <?php if ($order['erp_total_amount'] === null): ?><p class="mt-1 text-xs text-ink-soft">Estimated — final amount is confirmed by our team.</p><?php endif; ?>
        <p class="pt-2 text-xs text-ink-soft">Applicable GST will be shown on your invoice.</p>
        <div class="mt-5 border-t border-line pt-5">
          <h3 class="font-semibold">Payment</h3>
          <p class="mt-1"><?= e($method['label'] ?? $order['payment_method']) ?></p>
          <?php if ($paidInFull): ?>
            <p class="mt-1 text-xs text-emerald-700">Paid in full.</p>
          <?php elseif (!empty($method['order_page_note'])): ?>
            <p class="mt-1 text-xs text-ink-soft"><?= e($method['order_page_note']) ?></p>
          <?php endif; ?>
        </div>
      </section>
      <section class="rounded-3xl border border-line bg-white p-6 text-sm">
        <h2 class="mb-3 text-base font-semibold">Shipping to</h2>
        <address class="not-italic leading-relaxed text-ink-soft">
          <span class="font-medium text-ink"><?= e($order['customer_name']) ?></span><br>
          <?= e($order['ship_address_line']) ?><br>
          <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> <?= e($order['ship_pincode']) ?><br>
          <?= e($order['ship_country']) ?>
          <?php if ($order['customer_phone']): ?><br><?= e($order['customer_phone']) ?><?php endif; ?>
        </address>
        <?php if ($order['notes']): ?>
          <h3 class="mb-1 mt-4 font-semibold">Notes</h3>
          <p class="whitespace-pre-line text-ink-soft"><?= e($order['notes']) ?></p>
        <?php endif; ?>
      </section>
    </aside>
  </div>
</div>
<?php }, ['noindex' => true]);
