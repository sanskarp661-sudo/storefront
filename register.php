<?php
require __DIR__ . '/includes/bootstrap.php';

$return = safe_return_url(get_string('return', 500) ?: post_string('return', 500));
if (current_customer()) redirect($return);

$errors = [];
$values = ['name' => '', 'email' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (array_keys($values) as $k) $values[$k] = post_string($k, 200);
    if (post_string('website') === '') {
        $result = register_customer($values['name'], $values['email'], $values['phone'], (string) ($_POST['password'] ?? ''));
        if ($result['ok']) {
            login_customer($result['id']);
            flash('success', 'Your account is ready. Welcome to ' . STORE_NAME . '!');
            redirect($return);
        }
        $errors = $result['errors'];
    }
}

render_auth_page('Create account', 'Create your account', 'Already have one? <a href="' . e(url('login.php', ['return' => $return])) . '" class="font-semibold text-ink underline underline-offset-4 hover:text-accent">Sign in</a>', function () use ($errors, $values, $return) { ?>
  <form method="post" action="<?= e(url('register.php')) ?>" class="space-y-4" novalidate>
    <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($return) ?>">
    <div class="absolute -left-[9999px]" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
    <?= form_field('name', 'Full name', $values, $errors, ['autocomplete' => 'name', 'required' => true, 'autofocus' => true]) ?>
    <?= form_field('email', 'Email address', $values, $errors, ['type' => 'email', 'autocomplete' => 'email', 'required' => true]) ?>
    <?= form_field('phone', 'Mobile number (optional)', $values, $errors, ['type' => 'tel', 'autocomplete' => 'tel-national', 'inputmode' => 'tel', 'placeholder' => '10-digit mobile']) ?>
    <?= password_field('password', 'Password', $errors['password'] ?? null, 'new-password', 'At least ' . PASSWORD_MIN_LENGTH . ' characters') ?>
    <button type="submit" class="btn btn-primary btn-lg w-full">Create account</button>
    <p class="text-center text-xs text-ink-soft">Your details are only used to process your orders.</p>
  </form>
<?php });
