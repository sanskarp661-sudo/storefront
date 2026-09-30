<?php
/** @var array $order @var bool $delivered */
if ($order['status'] === 'cancelled'): ?>
  <div class="flex items-center gap-3 rounded-2xl bg-stone-100 p-4 text-sm">
    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-stone-700 text-white"><?= icon('x', 'h-4 w-4') ?></span>
    <div><p class="font-medium">Order cancelled</p><p class="text-ink-soft">This order has been cancelled. Contact us if you have questions.</p></div>
  </div>
<?php else:
  $steps = [
      ['Order placed', "We've received your order."],
      ['Confirmed', 'Your order is confirmed and being prepared.'],
      ['Shipped', 'Your order is on its way.'],
      ['Delivered', 'Your order has been delivered.'],
  ];
  $reached = $delivered ? 3 : (['pending' => 0, 'confirmed' => 1, 'shipped' => 2, 'completed' => 3][$order['status']] ?? 0);
?>
  <ol class="grid gap-6 sm:grid-cols-4 sm:gap-2">
    <?php foreach ($steps as $i => [$label, $desc]): $done = $i <= $reached; $current = $i === $reached; ?>
      <li class="relative flex gap-3 sm:flex-col">
        <?php if ($i < count($steps) - 1): ?>
          <span aria-hidden="true" class="absolute left-4 top-8 h-[calc(100%-8px)] w-0.5 sm:left-8 sm:top-4 sm:h-0.5 sm:w-[calc(100%-16px)] <?= $i < $reached ? 'bg-ink' : 'bg-line' ?>"></span>
        <?php endif; ?>
        <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 <?= $done ? 'border-ink bg-ink text-white' : 'border-line bg-white text-ink-soft' ?> <?= $current ? 'ring-4 ring-ink/10' : '' ?>">
          <?= $done ? icon('check', 'h-4 w-4') : '<span class="text-xs">' . ($i + 1) . '</span>' ?>
        </span>
        <div class="text-sm">
          <p class="font-medium <?= $done ? '' : 'text-ink-soft' ?>"><?= e($label) ?></p>
          <?php if ($current): ?><p class="mt-0.5 text-ink-soft"><?= e($desc) ?></p><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif;
