<?php
require __DIR__ . '/includes/bootstrap.php';

/** Per-attempt idempotency key: a double submit or a retry after an error resolves to the same order. */
function checkout_key(bool $rotate = false): string
{
    if ($rotate || empty($_SESSION['checkout_key'])) $_SESSION['checkout_key'] = bin2hex(random_bytes(16));
    return $_SESSION['checkout_key'];
}

$errors = [];
$formError = null;
$issueMessages = [];
$values = ['name' => '', 'email' => '', 'phone' => '', 'company' => '', 'label' => 'Home', 'address_line' => '', 'city' => '', 'state' => '', 'pincode' => '', 'notes' => '', 'payment_method' => ''];
$methods = enabled_payment_methods();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (array_keys($values) as $k) $values[$k] = post_string($k, $k === 'notes' ? 500 : 300);

    // Honeypot: real customers never see or fill this field.
    if (post_string('website') !== '') redirect(url('checkout.php'));

    $phone = normalize_indian_phone($values['phone']);
    if (mb_strlen($values['name']) < 2) $errors['name'] = 'Please enter your full name';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 200) $errors['email'] = 'Please enter a valid email address';
    if ($phone === null) $errors['phone'] = 'Please enter a valid 10-digit mobile number';
    if (mb_strlen($values['address_line']) < 5) $errors['address_line'] = 'Please enter your street address';
    if (mb_strlen($values['city']) < 2) $errors['city'] = 'Please enter your city';
    if (!in_array($values['state'], INDIAN_STATES, true)) $errors['state'] = 'Please choose your state';
    if (!preg_match('/^[1-9][0-9]{5}$/', $values['pincode'])) $errors['pincode'] = 'Please enter a valid 6-digit PIN code';
    if (!in_array($values['label'], ['Home', 'Office', 'Other'], true)) $values['label'] = 'Home';
    if (!in_array($values['payment_method'], array_column($methods, 'id'), true)) $errors['payment_method'] = 'Please choose a payment method';

    $items = cart_raw();
    if (!$items) {
        $formError = 'Your cart is empty.';
    } elseif ($errors) {
        $formError = 'Please fix the highlighted fields.';
    } else {
        $postedKey = post_string('checkout_key', 32);
        $key = preg_match('/^[0-9a-f]{32}$/', $postedKey) ? $postedKey : checkout_key();

        $result = place_order([
            'checkout_key' => $key,
            'name' => $values['name'],
            'email' => $values['email'],
            'phone' => $phone,
            'company' => $values['company'] !== '' ? mb_substr($values['company'], 0, 120) : null,
            'label' => $values['label'],
            'address_line' => $values['address_line'],
            'city' => mb_substr($values['city'], 0, 80),
            'state' => $values['state'],
            'pincode' => $values['pincode'],
            'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            'payment_method' => $values['payment_method'],
            'items' => $items,
        ]);

        if ($result['ok']) {
            $order = $result['order'];
            cart_clear();
            checkout_key(true);
            $_SESSION['recent_orders'][$order['website_order_id']] = ['key' => $order['access_token'], 'placed_at' => gmdate('c')];
            redirect(order_url($order['website_order_id'], $order['access_token'], true));
        }

        $formError = $result['error'];
        foreach ($result['issues'] ?? [] as $sku => $issue) {
            cart_set((string) $sku, (int) $issue['available']);
            $issueMessages[] = $issue['message'] . ($issue['available'] > 0 ? ' Your cart has been updated.' : ' It was removed from your cart.');
        }
        if (!empty($result['rotate_key'])) checkout_key(true);
    }
}

$cart = cart_contents();
$key = checkout_key();
if (!in_array($values['payment_method'], array_column($methods, 'id'), true)) $values['payment_method'] = $methods[0]['id'];

$field = function (string $name, string $label, array $attrs = [], string $class = '', string $hint = '') use ($values, $errors): string {
    $attrHtml = '';
    foreach ($attrs as $k => $v) $attrHtml .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e((string) $v) . '"';
    $error = $errors[$name] ?? null;
    return '<div class="' . e($class) . '"><label class="field-label" for="' . e($name) . '">' . e($label) . '</label>'
        . '<input id="' . e($name) . '" name="' . e($name) . '" value="' . e($values[$name]) . '" class="field-input"'
        . ($error ? ' aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '') . $attrHtml . '>'
        . ($error ? '<p id="' . e($name) . '-error" class="mt-1 text-xs text-red-600">' . e($error) . '</p>' : ($hint ? '<p class="mt-1 text-xs text-ink-soft">' . e($hint) . '</p>' : ''))
        . '</div>';
};

render_page('Checkout', function () use ($cart, $key, $values, $errors, $formError, $issueMessages, $methods, $field) { ?>
<div class="container-page py-10">
  <h1 class="text-3xl font-semibold tracking-tight">Checkout</h1>
  <?php if (!$cart['lines']): ?>
    <div class="mt-8 rounded-3xl border border-dashed border-line bg-white p-14 text-center">
      <?php if ($formError): ?><p class="mb-4 text-sm text-red-700"><?= e($formError) ?></p><?php endif; ?>
      <p class="text-lg font-medium">Your cart is empty</p>
      <a href="<?= e(url('products.php')) ?>" class="btn btn-primary mt-6">Browse products</a>
    </div>
  <?php else: ?>
  <form method="post" action="<?= e(url('checkout.php')) ?>" class="mt-8 grid gap-10 lg:grid-cols-[1fr_400px]" data-checkout novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="checkout_key" value="<?= e($key) ?>">
    <div class="absolute -left-[9999px]" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <div class="space-y-8">
      <?php if ($formError): ?>
        <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
          <p class="flex gap-2 font-medium"><?= icon('circle-alert', 'mt-0.5 h-4 w-4 shrink-0') ?> <?= e($formError) ?></p>
          <?php if ($issueMessages): ?>
            <ul class="ml-6 mt-2 list-disc space-y-0.5"><?php foreach ($issueMessages as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <fieldset class="rounded-3xl border border-line bg-white p-6">
        <legend class="px-2 text-lg font-semibold">Contact details</legend>
        <div class="mt-2 grid gap-4 sm:grid-cols-2">
          <?= $field('name', 'Full name', ['autocomplete' => 'name', 'required' => true, 'maxlength' => 120], 'sm:col-span-2') ?>
          <?= $field('email', 'Email', ['type' => 'email', 'autocomplete' => 'email', 'required' => true, 'maxlength' => 200], '', "We'll use this to look up your order.") ?>
          <?= $field('phone', 'Mobile number', ['type' => 'tel', 'autocomplete' => 'tel-national', 'inputmode' => 'tel', 'required' => true, 'placeholder' => '10-digit mobile']) ?>
          <?= $field('company', 'Company (optional)', ['autocomplete' => 'organization', 'maxlength' => 120], 'sm:col-span-2') ?>
        </div>
      </fieldset>

      <fieldset class="rounded-3xl border border-line bg-white p-6">
        <legend class="px-2 text-lg font-semibold">Shipping address</legend>
        <div class="mt-2 grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <span class="field-label">Address type</span>
            <div class="flex gap-2">
              <?php foreach (['Home', 'Office', 'Other'] as $label): ?>
                <label class="cursor-pointer">
                  <input type="radio" name="label" value="<?= $label ?>" <?= $values['label'] === $label ? 'checked' : '' ?> class="peer sr-only">
                  <span class="inline-block rounded-full border border-line px-4 py-1.5 text-sm peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-ink/30"><?= $label ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?= $field('address_line', 'Street address', ['autocomplete' => 'street-address', 'required' => true, 'maxlength' => 300, 'placeholder' => 'House / flat no., building, street, area'], 'sm:col-span-2') ?>
          <?= $field('city', 'City', ['autocomplete' => 'address-level2', 'required' => true, 'maxlength' => 80]) ?>
          <?= $field('pincode', 'PIN code', ['autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 6, 'required' => true]) ?>
          <div>
            <label class="field-label" for="state">State</label>
            <select id="state" name="state" required class="field-input" <?= isset($errors['state']) ? 'aria-invalid="true"' : '' ?>>
              <option value="" disabled <?= $values['state'] === '' ? 'selected' : '' ?>>Select state</option>
              <?php foreach (INDIAN_STATES as $s): ?><option value="<?= e($s) ?>" <?= $values['state'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select>
            <?php if (isset($errors['state'])): ?><p class="mt-1 text-xs text-red-600"><?= e($errors['state']) ?></p><?php endif; ?>
          </div>
          <div><span class="field-label">Country</span><div class="field-input bg-stone-50 text-ink-soft">India</div></div>
        </div>
      </fieldset>

      <fieldset class="rounded-3xl border border-line bg-white p-6">
        <legend class="px-2 text-lg font-semibold">Payment</legend>
        <div class="mt-2 grid gap-3">
          <?php foreach ($methods as $m): ?>
            <label class="cursor-pointer">
              <input type="radio" name="payment_method" value="<?= e($m['id']) ?>" <?= $values['payment_method'] === $m['id'] ? 'checked' : '' ?> class="peer sr-only">
              <span class="flex items-start gap-3 rounded-2xl border border-line p-4 peer-checked:border-ink peer-checked:ring-1 peer-checked:ring-ink peer-focus-visible:ring-2 peer-focus-visible:ring-ink/30">
                <span class="mt-0.5 text-accent"><?= icon('banknote', 'h-5 w-5 shrink-0') ?></span>
                <span><span class="block font-medium"><?= e($m['label']) ?></span><span class="block text-sm text-ink-soft"><?= e($m['description']) ?></span></span>
              </span>
            </label>
          <?php endforeach; ?>
          <?php if (isset($errors['payment_method'])): ?><p class="text-xs text-red-600"><?= e($errors['payment_method']) ?></p><?php endif; ?>
        </div>
      </fieldset>

      <fieldset class="rounded-3xl border border-line bg-white p-6">
        <legend class="px-2 text-lg font-semibold">Order notes</legend>
        <textarea name="notes" rows="3" maxlength="500" placeholder="Delivery instructions, preferred time, etc. (optional)" class="field-input mt-2 resize-y"><?= e($values['notes']) ?></textarea>
      </fieldset>
    </div>

    <aside class="h-fit rounded-3xl border border-line bg-white p-6 lg:sticky lg:top-24">
      <h2 class="text-lg font-semibold">Your order</h2>
      <ul class="mt-5 space-y-4">
        <?php foreach ($cart['lines'] as $l): $p = $l['product']; ?>
          <li class="flex items-center gap-3">
            <div class="w-14 shrink-0 overflow-hidden rounded-lg border border-line"><?= product_image($p['image_url'], $p['name']) ?></div>
            <div class="min-w-0 flex-1">
              <p class="line-clamp-1 text-sm font-medium"><?= e($p['name']) ?></p>
              <p class="text-xs text-ink-soft">Qty <?= $l['quantity'] ?> × <?= e(format_price($p['price'], $p['currency'])) ?></p>
            </div>
            <p class="text-sm font-medium"><?= e(format_price($l['line_total'], $p['currency'])) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
      <dl class="mt-6 space-y-2 border-t border-line pt-5 text-sm">
        <div class="flex justify-between"><dt class="text-ink-soft">Subtotal</dt><dd><?= e(format_price($cart['subtotal'], $cart['currency'])) ?></dd></div>
        <div class="flex justify-between"><dt class="text-ink-soft">GST</dt><dd class="text-ink-soft">Confirmed on invoice</dd></div>
      </dl>
      <div class="mt-4 flex justify-between border-t border-line pt-4 font-semibold"><span>Total (excl. GST)</span><span><?= e(format_price($cart['subtotal'], $cart['currency'])) ?></span></div>
      <button type="submit" class="btn btn-accent mt-6 w-full" data-submit-label="Placing your order…"><?= icon('lock', 'h-4 w-4') ?> <span>Place order</span></button>
      <p class="mt-3 text-xs leading-relaxed text-ink-soft">
        Prices are confirmed by our system when your order is placed. Applicable GST is added on your invoice.
        <?php if (count($methods) === 1 && $methods[0]['id'] === 'cod'): ?>You pay in cash when your order is delivered.<?php endif; ?>
      </p>
    </aside>
  </form>
  <?php endif; ?>
</div>
<?php }, ['noindex' => true]);
