<?php
require __DIR__ . '/includes/bootstrap.php';

$customer = require_login();
$cid = (int) $customer['id'];
$tabs = ['overview' => ['layout-grid', 'Overview'], 'orders' => ['package', 'My orders'], 'wishlist' => ['heart', 'Wishlist'], 'addresses' => ['map-pin', 'Saved addresses'], 'profile' => ['settings', 'Profile & security']];
$tab = array_key_exists(get_string('tab'), $tabs) ? get_string('tab') : 'overview';
$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post_string('action', 30);

    if ($action === 'profile') {
        $name = post_string('name', 120);
        $phoneRaw = post_string('phone', 20);
        $phone = $phoneRaw === '' ? null : normalize_indian_phone($phoneRaw);
        if (mb_strlen($name) < 2) $errors['name'] = 'Please enter your full name';
        if ($phoneRaw !== '' && $phone === null) $errors['phone'] = 'Please enter a valid 10-digit mobile number';
        if (!$errors) {
            db_query('UPDATE customers SET name = ?, phone = ?, updated_at = UTC_TIMESTAMP(3) WHERE id = ?', [$name, $phone, $cid]);
            flash('success', 'Your profile has been updated.');
            redirect(url('account.php', ['tab' => 'profile']));
        }
        $values = ['name' => $name, 'phone' => $phoneRaw];
        $tab = 'profile';
    } elseif ($action === 'password') {
        $new = (string) ($_POST['new_password'] ?? '');
        $err = $new !== (string) ($_POST['confirm_password'] ?? '') ? "The two new passwords don't match." : change_password($cid, (string) ($_POST['current_password'] ?? ''), $new);
        if ($err === null) {
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            redirect(url('account.php', ['tab' => 'profile']));
        }
        $errors['password'] = $err;
        $tab = 'profile';
    } elseif ($action === 'address_save') {
        [$v, $errs] = validate_address($_POST);
        $editId = (int) post_string('address_id', 12) ?: null;
        if ($editId && !customer_address($cid, $editId)) $editId = null;
        if (!$errs) {
            save_address($cid, $v, $editId, post_string('make_default') === '1');
            flash('success', $editId ? 'Address updated.' : 'Address saved.');
            redirect(url('account.php', ['tab' => 'addresses']));
        }
        $errors = $errs;
        $values = $v + ['address_id' => (string) $editId, 'phone_raw' => post_string('phone', 20)];
        $values['phone'] = $values['phone'] ?: $values['phone_raw'];
        $tab = 'addresses';
    } elseif ($action === 'address_default') {
        set_default_address($cid, (int) post_string('address_id', 12));
        redirect(url('account.php', ['tab' => 'addresses']));
    } elseif ($action === 'address_delete') {
        delete_address($cid, (int) post_string('address_id', 12));
        flash('success', 'Address removed.');
        redirect(url('account.php', ['tab' => 'addresses']));
    }
}

$orders = customer_orders($cid);
$addresses = customer_addresses($cid);
$wishSkus = wishlist_skus($customer);
$wishProducts = get_products_by_skus($wishSkus);
$wishlist = array_values(array_filter(array_map(fn($s) => $wishProducts[$s] ?? null, $wishSkus)));
$editAddress = $tab === 'addresses' && get_string('edit') !== '' ? customer_address($cid, (int) get_string('edit')) : null;
$showAddressForm = $tab === 'addresses' && ($editAddress || get_string('new') === '1' || $errors);

render_page('My account', function () use ($customer, $tabs, $tab, $orders, $addresses, $wishlist, $errors, $values, $editAddress, $showAddressForm) {
    $first = explode(' ', $customer['name'])[0];
    $orderRow = function (array $o) {
        $ref = $o['erp_order_no'] ?: $o['website_order_id'];
        $total = $o['erp_total_amount'] !== null ? (float) $o['erp_total_amount'] : (float) $o['subtotal_estimate'];
        ob_start(); ?>
        <a href="<?= e(order_url($o['website_order_id'], $o['access_token'])) ?>" class="flex items-center gap-4 p-4 transition hover:bg-linen sm:px-6">
          <div class="w-14 shrink-0 overflow-hidden rounded-xl border border-line"><?= product_image($o['first_image'], (string) $o['first_name'], '', false, null, 'p-1', true) ?></div>
          <div class="min-w-0 flex-1">
            <p class="font-mono text-sm font-bold"><?= e($ref) ?></p>
            <p class="truncate text-sm text-ink-soft"><?= e((string) $o['first_name']) ?><?= $o['item_count'] > 1 ? ' + ' . ($o['item_count'] - 1) . ' more' : '' ?></p>
            <p class="text-xs text-muted">Placed <?= e(format_date($o['created_at'])) ?></p>
          </div>
          <div class="hidden text-right sm:block"><p class="font-semibold"><?= e(format_price($total, $o['currency'])) ?></p><p class="text-xs text-ink-soft">Cash on Delivery</p></div>
          <div class="flex flex-col items-end gap-1">
            <?= $o['submit_state'] === 'submitted' ? order_status_pill($o['status']) : '<span class="rounded-full bg-linen px-3 py-1 text-xs font-bold text-ink-soft ring-1 ring-slate-200">' . ($o['submit_state'] === 'rejected' ? 'Not placed' : 'Confirming') . '</span>' ?>
            <span class="text-muted"><?= icon('chevron-right', 'h-5 w-5') ?></span>
          </div>
        </a>
        <?php return (string) ob_get_clean();
    };
    ?>
<div class="container-page py-8">
  <div class="relative mb-8 overflow-hidden rounded-[6px] bg-[#141414] p-6 text-white sm:p-8">
    <div class="relative flex flex-wrap items-center gap-5">
      <span class="flex h-16 w-16 items-center justify-center rounded-full bg-accent-soft font-display text-3xl text-accent"><?= e(initials($customer['name'])) ?></span>
      <div class="flex-1">
        <p class="cap text-[#b5aea4]">Welcome back</p>
        <h1 class="h2 text-white"><?= e($customer['name']) ?></h1>
        <p class="text-sm text-[#b5aea4]"><?= e($customer['email']) ?> · Member since <?= e(format_date($customer['created_at'])) ?></p>
      </div>
      <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-sm border-white/30 text-white hover:border-white"><?= icon('log-out', 'h-4 w-4') ?> Sign out</button></form>
    </div>
  </div>

  <div class="grid gap-6 lg:grid-cols-[260px_1fr]">
    <nav class="flex gap-0.5 overflow-x-auto lg:sticky lg:top-40 lg:flex-col lg:self-start" aria-label="Account">
      <?php foreach ($tabs as $key => [$ic, $label]): $on = $tab === $key; ?>
        <a href="<?= e(url('account.php', ['tab' => $key === 'overview' ? null : $key])) ?>" class="flex min-h-[46px] shrink-0 items-center gap-3 rounded-[2px] px-3.5 text-sm font-medium <?= $on ? 'bg-ink text-white hover:text-white' : 'text-ink hover:bg-linen hover:text-ink' ?>" <?= $on ? 'aria-current="page"' : '' ?>>
          <?= icon($ic, 'h-5 w-5') ?> <?= e($label) ?>
          <?php if ($key === 'orders' && $orders): ?><span class="ml-auto rounded-full <?= $on ? 'bg-white/20' : 'bg-linen' ?> px-2 text-xs"><?= count($orders) ?></span><?php endif; ?>
          <?php if ($key === 'wishlist' && $wishlist): ?><span class="ml-auto rounded-full <?= $on ? 'bg-white/20' : 'bg-linen' ?> px-2 text-xs"><?= count($wishlist) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="min-w-0 space-y-6">
    <?php if ($tab === 'overview'): ?>
      <div class="grid gap-4 sm:grid-cols-3">
        <?php foreach ([['package', count($orders), 'Orders', 'orders', 'bg-indigo-50 text-ink'], ['heart', count($wishlist), 'Wishlist items', 'wishlist', 'bg-rose-50 text-rose-600'], ['map-pin', count($addresses), 'Saved addresses', 'addresses', 'bg-emerald-50 text-emerald-700']] as [$ic, $n, $label, $t, $tone]): ?>
          <a href="<?= e(url('account.php', ['tab' => $t])) ?>" class="card flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-lift">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl <?= $tone ?>"><?= icon($ic, 'h-6 w-6') ?></span>
            <div><p class="text-2xl font-semibold leading-none"><?= $n ?></p><p class="mt-1 text-sm text-ink-soft"><?= e($label) ?></p></div>
          </a>
        <?php endforeach; ?>
      </div>
      <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-6 py-4"><h2 class="h4">Recent orders</h2><?php if ($orders): ?><a href="<?= e(url('account.php', ['tab' => 'orders'])) ?>" class="text-sm font-semibold text-ink underline underline-offset-4 hover:text-accent">View all</a><?php endif; ?></div>
        <?php if ($orders): ?>
          <div class="divide-y divide-line"><?php foreach (array_slice($orders, 0, 3) as $o) echo $orderRow($o); ?></div>
        <?php else: ?>
          <div class="p-8 text-center text-sm text-ink-soft">You haven't placed any orders yet. <a href="<?= e(url('products.php')) ?>" class="font-semibold text-ink underline underline-offset-4 hover:text-accent">Start shopping</a></div>
        <?php endif; ?>
      </section>
      <div class="grid gap-4 sm:grid-cols-2">
        <?php $def = $addresses[0] ?? null; ?>
        <section class="card p-6">
          <div class="flex items-center justify-between"><h2 class="h4">Default address</h2><a href="<?= e(url('account.php', ['tab' => 'addresses'])) ?>" class="text-sm font-semibold text-ink underline underline-offset-4 hover:text-accent"><?= $def ? 'Manage' : 'Add' ?></a></div>
          <?php if ($def): ?><p class="mt-3 text-sm leading-relaxed text-ink-soft"><span class="font-bold text-ink"><?= e($def['name']) ?></span><br><?= e($def['address_line']) ?><br><?= e($def['city']) ?>, <?= e($def['state']) ?> <?= e($def['pincode']) ?><br><?= e($def['phone']) ?></p>
          <?php else: ?><p class="mt-3 text-sm text-ink-soft">Save an address for one-tap checkout.</p><?php endif; ?>
        </section>
        <section class="card p-6">
          <div class="flex items-center justify-between"><h2 class="h4">Profile</h2><a href="<?= e(url('account.php', ['tab' => 'profile'])) ?>" class="text-sm font-semibold text-ink underline underline-offset-4 hover:text-accent">Edit</a></div>
          <dl class="mt-3 space-y-1 text-sm"><div class="flex gap-2"><dt class="w-16 text-ink-soft">Name</dt><dd class="font-semibold"><?= e($customer['name']) ?></dd></div><div class="flex gap-2"><dt class="w-16 text-ink-soft">Email</dt><dd class="break-all font-semibold"><?= e($customer['email']) ?></dd></div><div class="flex gap-2"><dt class="w-16 text-ink-soft">Mobile</dt><dd class="font-semibold"><?= e($customer['phone'] ?: '—') ?></dd></div></dl>
        </section>
      </div>

    <?php elseif ($tab === 'orders'): ?>
      <section class="card overflow-hidden">
        <div class="border-b border-line px-6 py-4"><h2 class="h4">My orders</h2><p class="text-sm text-ink-soft">Orders placed while signed in to this account</p></div>
        <?php if ($orders): ?>
          <div class="divide-y divide-line"><?php foreach ($orders as $o) echo $orderRow($o); ?></div>
        <?php else: ?>
          <div class="p-10 text-center"><p class="font-bold">No orders yet</p><p class="mt-1 text-sm text-ink-soft">Orders you place while signed in will show up here.</p><a href="<?= e(url('products.php')) ?>" class="btn btn-primary mt-5">Start shopping</a></div>
        <?php endif; ?>
      </section>
      <p class="text-sm text-ink-soft">Ordered as a guest? <a href="<?= e(url('track.php')) ?>" class="font-semibold text-ink underline underline-offset-4 hover:text-accent">Find it with your order number and email</a>.</p>

    <?php elseif ($tab === 'wishlist'): ?>
      <div><h2 class="h4">Wishlist</h2><p class="text-sm text-ink-soft">Products you've saved for later</p></div>
      <?php if ($wishlist): ?>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3"><?php foreach ($wishlist as $p) echo product_card($p); ?></div>
      <?php else: ?>
        <?= empty_state('heart', 'Your wishlist is empty', 'Tap the heart on any product to save it here.', url('products.php'), 'Discover products') ?>
      <?php endif; ?>

    <?php elseif ($tab === 'addresses'): ?>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="h4">Saved addresses</h2><p class="text-sm text-ink-soft">Choose one at checkout — no retyping</p></div>
        <?php if (!$showAddressForm): ?><a href="<?= e(url('account.php', ['tab' => 'addresses', 'new' => '1'])) ?>" class="btn btn-primary btn-sm"><?= icon('plus', 'h-4 w-4') ?> Add address</a><?php endif; ?>
      </div>
      <?php if ($showAddressForm):
        $v = $values ?: ($editAddress ?: ['label' => 'Home', 'name' => $customer['name'], 'phone' => $customer['phone'] ?? '', 'address_line' => '', 'city' => '', 'state' => '', 'pincode' => '']);
        $editingId = $editAddress['id'] ?? ($values['address_id'] ?? ''); ?>
        <form method="post" action="<?= e(url('account.php', ['tab' => 'addresses'])) ?>" class="card p-6" novalidate>
          <?= csrf_field() ?><input type="hidden" name="action" value="address_save"><input type="hidden" name="address_id" value="<?= e((string) $editingId) ?>">
          <h3 class="h4 mb-5"><?= $editingId ? 'Edit address' : 'New address' ?></h3>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><span class="field-label">Address type</span><div class="flex gap-2">
              <?php foreach (['Home' => 'house', 'Office' => 'store', 'Other' => 'map-pin'] as $label => $ic): ?>
                <label class="cursor-pointer"><input type="radio" name="label" value="<?= $label ?>" <?= ($v['label'] ?? 'Home') === $label ? 'checked' : '' ?> class="peer sr-only">
                  <span class="inline-flex items-center gap-1.5 rounded-xl border-2 border-line px-4 py-2 text-sm font-semibold peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-ink"><?= icon($ic, 'h-4 w-4') ?> <?= $label ?></span></label>
              <?php endforeach; ?>
            </div></div>
            <?= form_field('name', 'Full name', $v, $errors, ['autocomplete' => 'name']) ?>
            <?= form_field('phone', 'Mobile number', $v, $errors, ['type' => 'tel', 'inputmode' => 'tel', 'autocomplete' => 'tel-national']) ?>
            <?= form_field('address_line', 'Street address', $v, $errors, ['autocomplete' => 'street-address', 'placeholder' => 'House / flat no., building, street, area'], 'sm:col-span-2') ?>
            <?= form_field('city', 'City', $v, $errors, ['autocomplete' => 'address-level2']) ?>
            <?= form_field('pincode', 'PIN code', $v, $errors, ['inputmode' => 'numeric', 'maxlength' => 6, 'autocomplete' => 'postal-code']) ?>
            <?= state_select('state', (string) ($v['state'] ?? ''), $errors['state'] ?? null) ?>
            <label class="flex items-center gap-2 self-end pb-3 text-sm font-medium"><input type="checkbox" name="make_default" value="1" class="h-4 w-4 accent-[#4f46e5]" <?= !empty($editAddress['is_default']) ? 'checked' : '' ?>> Make this my default address</label>
          </div>
          <div class="mt-6 flex gap-3"><button class="btn btn-primary">Save address</button><a href="<?= e(url('account.php', ['tab' => 'addresses'])) ?>" class="btn btn-ghost">Cancel</a></div>
        </form>
      <?php endif; ?>
      <?php if ($addresses): ?>
        <div class="grid gap-4 sm:grid-cols-2">
          <?php foreach ($addresses as $a): ?>
            <div class="card flex flex-col p-5 <?= $a['is_default'] ? 'ring-2 ring-brand' : '' ?>">
              <div class="flex items-center justify-between">
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-brand-soft px-2.5 py-1 text-xs font-bold text-ink"><?= icon(['Home' => 'house', 'Office' => 'store'][$a['label']] ?? 'map-pin', 'h-3.5 w-3.5') ?> <?= e($a['label']) ?></span>
                <?= $a['is_default'] ? '<span class="text-xs font-bold text-success">Default</span>' : '' ?>
              </div>
              <p class="mt-3 font-bold"><?= e($a['name']) ?></p>
              <p class="text-sm leading-relaxed text-ink-soft"><?= e($a['address_line']) ?><br><?= e($a['city']) ?>, <?= e($a['state']) ?> <?= e($a['pincode']) ?><br><?= e($a['phone']) ?></p>
              <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4 text-sm font-bold">
                <a href="<?= e(url('account.php', ['tab' => 'addresses', 'edit' => $a['id']])) ?>" class="inline-flex items-center gap-1 text-ink hover:underline"><?= icon('pencil', 'h-4 w-4') ?> Edit</a>
                <?php if (!$a['is_default']): ?>
                  <form method="post" action="<?= e(url('account.php', ['tab' => 'addresses'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="address_default"><input type="hidden" name="address_id" value="<?= (int) $a['id'] ?>"><button class="ml-3 text-ink-soft hover:text-ink">Set as default</button></form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('account.php', ['tab' => 'addresses'])) ?>" class="ml-auto" data-confirm="Remove this address?"><?= csrf_field() ?><input type="hidden" name="action" value="address_delete"><input type="hidden" name="address_id" value="<?= (int) $a['id'] ?>"><button class="inline-flex items-center gap-1 text-rose-600 hover:underline"><?= icon('trash-2', 'h-4 w-4') ?> Remove</button></form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php elseif (!$showAddressForm): ?>
        <?= empty_state('map-pin', 'No saved addresses', 'Add an address now, or save one when you check out.', url('account.php', ['tab' => 'addresses', 'new' => '1']), 'Add address') ?>
      <?php endif; ?>

    <?php elseif ($tab === 'profile'): ?>
      <form method="post" action="<?= e(url('account.php', ['tab' => 'profile'])) ?>" class="card p-6" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <h2 class="h4">Personal details</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <?= form_field('name', 'Full name', $values + ['name' => $customer['name']], $errors, ['autocomplete' => 'name']) ?>
          <?= form_field('phone', 'Mobile number', $values + ['phone' => $customer['phone']], $errors, ['type' => 'tel', 'inputmode' => 'tel', 'autocomplete' => 'tel-national']) ?>
          <div class="sm:col-span-2"><span class="field-label">Email address</span><div class="field-input flex items-center justify-between bg-linen text-ink-soft"><?= e($customer['email']) ?> <?= icon('lock', 'h-4 w-4') ?></div><p class="mt-1 text-xs text-ink-soft">Your email is your sign-in and can't be changed here<?= SUPPORT_EMAIL ? ' — contact ' . e(SUPPORT_EMAIL) . ' if you need to' : '' ?>.</p></div>
        </div>
        <button class="btn btn-primary mt-6">Save changes</button>
      </form>
      <form method="post" action="<?= e(url('account.php', ['tab' => 'profile'])) ?>" class="card p-6">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <h2 class="h4">Change password</h2>
        <?php if (isset($errors['password'])): ?><p role="alert" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800"><?= e($errors['password']) ?></p><?php endif; ?>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
          <?= password_field('current_password', 'Current password', null, 'current-password') ?>
          <?= password_field('new_password', 'New password', null, 'new-password', 'At least ' . PASSWORD_MIN_LENGTH . ' characters') ?>
          <?= password_field('confirm_password', 'Confirm new password', null, 'new-password') ?>
        </div>
        <button class="btn btn-dark mt-6"><?= icon('lock', 'h-4 w-4') ?> Update password</button>
      </form>
    <?php endif; ?>
    </div>
  </div>
</div>
<?php }, ['noindex' => true, 'active' => $tab === 'wishlist' ? 'wishlist' : 'account']);
