<?php
/** @var array $order @var bool $delivered */
if ($order['status'] === 'cancelled'): ?>
  <div class="flex items-center gap-4 rounded-2xl bg-slate-100 p-5 text-sm">
    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-700 text-white"><?= icon('x', 'h-5 w-5') ?></span>
    <div><p class="font-bold">Order cancelled</p><p class="text-ink-soft">This order has been cancelled. Contact us if you have questions.</p></div>
  </div>
<?php else:
  $steps = [
      ['receipt-text', 'Order placed', "We've received your order."],
      ['circle-check', 'Confirmed', 'Your order is confirmed and being prepared.'],
      ['truck', 'Shipped', 'Your order is on its way.'],
      ['package-check', 'Delivered', 'Your order has been delivered.'],
  ];
  $reached = $delivered ? 3 : (['pending' => 0, 'confirmed' => 1, 'shipped' => 2, 'completed' => 3][$order['status']] ?? 0);
?>
  <ol class="grid gap-6 sm:grid-cols-4 sm:gap-2">
    <?php foreach ($steps as $i => [$ic, $label, $desc]): $done = $i <= $reached; $current = $i === $reached; ?>
      <li class="relative flex gap-4 sm:flex-col sm:items-center sm:text-center">
        <?php if ($i < count($steps) - 1): ?>
          <span aria-hidden="true" class="absolute left-[1.35rem] top-12 h-[calc(100%-1.5rem)] w-1 rounded sm:left-[calc(50%+1.75rem)] sm:top-[1.35rem] sm:h-1 sm:w-[calc(100%-3.5rem)] <?= $i < $reached ? 'bg-brand' : 'bg-line' ?>"></span>
        <?php endif; ?>
        <span class="relative z-10 flex h-11 w-11 shrink-0 items-center justify-center rounded-full <?= $done ? 'bg-brand text-white shadow-[0_6px_16px_-6px_rgb(79_70_229/0.7)]' : 'bg-slate-100 text-muted' ?> <?= $current ? 'ring-8 ring-brand/15' : '' ?>">
          <?= icon($ic, 'h-5 w-5') ?>
        </span>
        <div class="text-sm sm:mt-1">
          <p class="font-bold <?= $done ? '' : 'text-muted' ?>"><?= e($label) ?></p>
          <?php if ($current): ?><p class="mt-0.5 text-ink-soft"><?= e($desc) ?></p><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif;
