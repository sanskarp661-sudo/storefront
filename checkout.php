<?php
require __DIR__ . '/includes/bootstrap.php';

/** Per-attempt idempotency key: a double submit or a retry after an error resolves to the same order. */
function checkout_key(bool $rotate = false): string
{
    if ($rotate || empty($_SESSION['checkout_key'])) $_SESSION['checkout_key'] = bin2hex(random_bytes(16));
    return $_SESSION['checkout_key'];
}

$customer = current_customer();
$saved = $customer ? customer_addresses((int) $customer['id']) : [];
$methods = enabled_payment_methods();
$errors = [];
$formError = null;
$issueMessages = [];
$values = ['name' => $customer['name'] ?? '', 'email' => $customer['email'] ?? '', 'phone' => $customer['phone'] ?? '', 'company' => '',
    'label' => 'Home', 'address_line' => '', 'city' => '', 'state' => '', 'pincode' => '', 'notes' => '', 'payment_method' => '',
    'address_id' => $saved ? (string) $saved[0]['id'] : 'new', 'save_address' => '1'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (array_keys($values) as $k) $values[$k] = post_string($k, $k === 'notes' ? 500 : 300);
    if (post_string('website') !== '') redirect(url('checkout.php')); // honeypot

    // A saved address (signed-in customers) replaces the typed address fields.
    $useSaved = $customer && $values['address_id'] !== 'new' ? customer_address((int) $customer['id'], (int) $values['address_id']) : null;
    if ($useSaved) {
        foreach (['label', 'address_line', 'city', 'state', 'pincode'] as $k) $values[$k] = $useSaved[$k];
        if ($values['phone'] === '') $values['phone'] = $useSaved['phone'];
    } else {
        $values['address_id'] = 'new';
    }

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
            'customer_id' => $customer['id'] ?? null,
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
            if ($customer && !$useSaved && $values['save_address'] === '1') {
                save_address((int) $customer['id'], ['label' => $values['label'], 'name' => $values['name'], 'phone' => $phone,
                    'address_line' => $values['address_line'], 'city' => $values['city'], 'state' => $values['state'], 'pincode' => $values['pincode']]);
            }
            if ($customer && !$customer['phone']) db_query('UPDATE customers SET phone = ? WHERE id = ?', [$phone, $customer['id']]);
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

render_page('Checkout', function () use ($cart, $key, $values, $errors, $formError, $issueMessages, $methods, $customer, $saved) {
    $step = fn(int $n, string $title, string $sub = '') => '<div class="mb-5 flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand text-sm font-extrabold text-white">' . $n . '</span><div><h2 class="text-lg font-extrabold leading-tight">' . e($title) . '</h2>' . ($sub ? '<p class="text-xs text-ink-soft">' . e($sub) . '</p>' : '') . '</div></div>';
    ?>
<div class="container-page py-8">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <h1 class="text-3xl font-extrabold tracking-tight">Checkout</h1>
    <ol class="hidden items-center gap-2 text-xs font-bold sm:flex">
      <li class="flex items-center gap-2 text-success"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-success text-white"><?= icon('check', 'h-3.5 w-3.5', 3) ?></span> Cart</li><li class="h-px w-8 bg-success"></li>
      <li class="flex items-center gap-2 text-brand"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-white">2</span> Details</li><li class="h-px w-8 bg-line"></li>
      <li class="flex items-center gap-2 text-muted"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200">3</span> Confirmation</li>
    </ol>
  </div>

  <?php if (!$cart['lines']): ?>
    <?= empty_state('shopping-cart', 'Your cart is empty', $formError ?: 'Add a few products before checking out.', url('products.php'), 'Browse products') ?>
  <?php else: ?>
  <form method="post" action="<?= e(url('checkout.php')) ?>" class="grid gap-6 lg:grid-cols-[1fr_400px]" data-checkout novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="checkout_key" value="<?= e($key) ?>">
    <div class="absolute -left-[9999px]" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <div class="space-y-5">
      <?php if ($formError): ?>
        <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
          <p class="flex gap-2 font-bold"><?= icon('circle-alert', 'h-5 w-5 shrink-0') ?> <?= e($formError) ?></p>
          <?php if ($issueMessages): ?><ul class="ml-7 mt-2 list-disc space-y-0.5"><?php foreach ($issueMessages as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!$customer): ?>
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-brand-soft p-4 text-sm">
          <p class="flex items-center gap-2 font-semibold text-brand-dark"><?= icon('circle-user', 'h-5 w-5') ?> Checking out as a guest. Have an account?</p>
          <a href="<?= e(url('login.php', ['return' => url('checkout.php')])) ?>" class="btn btn-sm bg-white text-brand shadow-sm">Sign in for faster checkout</a>
        </div>
      <?php endif; ?>

      <section class="card p-6">
        <?= $step(1, 'Contact details', 'We\'ll use these to confirm and deliver your order') ?>
        <div class="grid gap-4 sm:grid-cols-2">
          <?= form_field('name', 'Full name', $values, $errors, ['autocomplete' => 'name', 'required' => true, 'maxlength' => 120], 'sm:col-span-2') ?>
          <?= form_field('email', 'Email', $values, $errors, ['type' => 'email', 'autocomplete' => 'email', 'required' => true, 'maxlength' => 200]) ?>
          <?= form_field('phone', 'Mobile number', $values, $errors, ['type' => 'tel', 'autocomplete' => 'tel-national', 'inputmode' => 'tel', 'required' => true, 'placeholder' => '10-digit mobile']) ?>
          <?= form_field('company', 'Company (optional)', $values, $errors, ['autocomplete' => 'organization', 'maxlength' => 120], 'sm:col-span-2') ?>
        </div>
      </section>

      <section class="card p-6">
        <?= $step(2, 'Delivery address', 'We currently deliver across India') ?>
        <?php if ($saved): ?>
          <div class="grid gap-3 sm:grid-cols-2" data-address-options>
            <?php foreach ($saved as $a): ?>
              <label class="cursor-pointer">
                <input type="radio" name="address_id" value="<?= (int) $a['id'] ?>" <?= $values['address_id'] === (string) $a['id'] ? 'checked' : '' ?> class="peer sr-only">
                <span class="block h-full rounded-2xl border-2 border-line p-4 text-sm transition peer-checked:border-brand peer-checked:bg-brand-soft/60 peer-focus-visible:ring-4 peer-focus-visible:ring-brand/20">
                  <span class="flex items-center justify-between"><span class="rounded-md bg-white px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-brand ring-1 ring-line"><?= e($a['label']) ?></span><?= $a['is_default'] ? '<span class="text-[11px] font-bold text-success">Default</span>' : '' ?></span>
                  <span class="mt-2 block font-bold"><?= e($a['name']) ?></span>
                  <span class="block text-ink-soft"><?= e($a['address_line']) ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> <?= e($a['pincode']) ?></span>
                  <span class="block text-ink-soft"><?= e($a['phone']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
            <label class="cursor-pointer">
              <input type="radio" name="address_id" value="new" <?= $values['address_id'] === 'new' ? 'checked' : '' ?> class="peer sr-only">
              <span class="flex h-full min-h-[7rem] items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-line p-4 text-sm font-bold text-brand transition peer-checked:border-brand peer-checked:bg-brand-soft/60"><?= icon('plus', 'h-5 w-5') ?> Deliver to a new address</span>
            </label>
          </div>
        <?php else: ?>
          <input type="hidden" name="address_id" value="new">
        <?php endif; ?>

        <div class="<?= $saved ? 'mt-5 border-t border-line pt-5' : '' ?> <?= $saved && $values['address_id'] !== 'new' ? 'hidden' : '' ?>" data-new-address>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <span class="field-label">Address type</span>
              <div class="flex gap-2">
                <?php foreach (['Home' => 'house', 'Office' => 'store', 'Other' => 'map-pin'] as $label => $ic): ?>
                  <label class="cursor-pointer">
                    <input type="radio" name="label" value="<?= $label ?>" <?= $values['label'] === $label ? 'checked' : '' ?> class="peer sr-only">
                    <span class="inline-flex items-center gap-1.5 rounded-xl border-2 border-line px-4 py-2 text-sm font-semibold peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand"><?= icon($ic, 'h-4 w-4') ?> <?= $label ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <?= form_field('address_line', 'Street address', $values, $errors, ['autocomplete' => 'street-address', 'maxlength' => 300, 'placeholder' => 'House / flat no., building, street, area'], 'sm:col-span-2') ?>
            <?= form_field('city', 'City', $values, $errors, ['autocomplete' => 'address-level2', 'maxlength' => 80]) ?>
            <?= form_field('pincode', 'PIN code', $values, $errors, ['autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 6]) ?>
            <?= state_select('state', $values['state'], $errors['state'] ?? null) ?>
            <div><span class="field-label">Country</span><div class="field-input bg-slate-50 text-ink-soft">India</div></div>
            <?php if ($customer): ?>
              <label class="flex items-center gap-2 text-sm font-medium sm:col-span-2"><input type="hidden" name="save_address" value="0"><input type="checkbox" name="save_address" value="1" <?= $values['save_address'] === '1' ? 'checked' : '' ?> class="h-4 w-4 accent-[#4f46e5]"> Save this address to my account</label>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="card p-6">
        <?= $step(3, 'Payment method') ?>
        <div class="grid gap-3">
          <?php foreach ($methods as $m): ?>
            <label class="cursor-pointer">
              <input type="radio" name="payment_method" value="<?= e($m['id']) ?>" <?= $values['payment_method'] === $m['id'] ? 'checked' : '' ?> class="peer sr-only">
              <span class="flex items-center gap-4 rounded-2xl border-2 border-line p-4 transition peer-checked:border-brand peer-checked:bg-brand-soft/60">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700"><?= icon('banknote', 'h-6 w-6') ?></span>
                <span class="flex-1"><span class="block font-bold"><?= e($m['label']) ?></span><span class="block text-sm text-ink-soft"><?= e($m['description']) ?></span></span>
                <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-brand bg-brand text-white"><?= icon('check', 'h-3.5 w-3.5', 3) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
          <?php if (isset($errors['payment_method'])): ?><p class="field-error"><?= e($errors['payment_method']) ?></p><?php endif; ?>
        </div>
        <label for="notes" class="field-label mt-5">Order notes (optional)</label>
        <textarea id="notes" name="notes" rows="2" maxlength="500" placeholder="Delivery instructions, preferred time, etc." class="field-input resize-y"><?= e($values['notes']) ?></textarea>
      </section>
    </div>

    <aside class="space-y-4 lg:sticky lg:top-36 lg:self-start">
      <div class="card p-6">
        <div class="flex items-center justify-between"><h2 class="text-lg font-extrabold">Order summary</h2><a href="<?= e(url('cart.php')) ?>" class="text-sm font-bold text-brand hover:underline">Edit</a></div>
        <ul class="mt-5 space-y-4">
          <?php foreach ($cart['lines'] as $l): $p = $l['product']; ?>
            <li class="flex items-center gap-3">
              <div class="relative w-16 shrink-0">
                <div class="overflow-hidden rounded-xl border border-line"><?= product_image($p['image_url'], $p['name'], '', false, $p['category'], 'p-1.5') ?></div>
                <span class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-ink px-1 text-[11px] font-bold text-white"><?= $l['quantity'] ?></span>
              </div>
              <p class="line-clamp-2 min-w-0 flex-1 text-sm font-semibold"><?= e($p['name']) ?></p>
              <p class="text-sm font-bold"><?= e(format_price($l['line_total'], $p['currency'])) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
        <dl class="mt-6 space-y-2.5 border-t border-line pt-5 text-sm">
          <div class="flex justify-between"><dt class="text-ink-soft">Subtotal</dt><dd class="font-semibold"><?= e(format_price($cart['subtotal'], $cart['currency'])) ?></dd></div>
          <div class="flex justify-between"><dt class="text-ink-soft">GST</dt><dd class="text-ink-soft">On invoice</dd></div>
        </dl>
        <div class="mt-4 flex items-end justify-between border-t border-dashed border-line pt-4">
          <span class="font-bold">Total <span class="block text-xs font-medium text-ink-soft">excl. GST · pay on delivery</span></span>
          <span class="text-2xl font-extrabold"><?= e(format_price($cart['subtotal'], $cart['currency'])) ?></span>
        </div>
        <button type="submit" class="btn btn-accent btn-lg mt-6 w-full" data-submit-label="Placing your order…"><?= icon('lock', 'h-5 w-5') ?> <span>Place order</span></button>
        <p class="mt-3 text-center text-xs leading-relaxed text-ink-soft">By placing your order you agree to pay <?= count($methods) === 1 && $methods[0]['id'] === 'cod' ? 'in cash on delivery' : 'with the method selected' ?>. Prices are confirmed by our system; applicable GST is added on your invoice.</p>
      </div>
    </aside>
  </form>
  <?php endif; ?>
</div>
<?php }, ['noindex' => true]);
