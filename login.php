<?php
require __DIR__ . '/includes/bootstrap.php';

$return = safe_return_url(get_string('return', 500) ?: post_string('return', 500));
if (current_customer()) redirect($return);

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = post_string('email', 200);
    $result = attempt_login($email, (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        login_customer($result['id']);
        flash('success', 'Welcome back! You are signed in.');
        redirect($return);
    }
    $error = $result['error'];
}

render_auth_page('Sign in', 'Welcome back', 'New to ' . e(STORE_NAME) . '? <a href="' . e(url('register.php', ['return' => $return])) . '" class="font-semibold text-ink underline underline-offset-4 hover:text-accent">Create an account</a>', function () use ($error, $email, $return) { ?>
  <form method="post" action="<?= e(url('login.php')) ?>" class="space-y-4">
    <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($return) ?>">
    <?php if ($error): ?><p role="alert" class="flex items-center gap-2 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800"><?= icon('circle-alert', 'h-4 w-4 shrink-0') ?> <?= e($error) ?></p><?php endif; ?>
    <?= form_field('email', 'Email address', ['email' => $email], [], ['type' => 'email', 'autocomplete' => 'email', 'required' => true, 'autofocus' => true]) ?>
    <?= password_field('password', 'Password', null, 'current-password') ?>
    <div class="flex justify-end"><a href="<?= e(url('forgot-password.php')) ?>" class="text-sm font-semibold text-ink underline underline-offset-4 hover:text-accent">Forgot password?</a></div>
    <button type="submit" class="btn btn-primary btn-lg w-full"><?= icon('log-in', 'h-5 w-5') ?> Sign in</button>
  </form>
  <div class="my-6 flex items-center gap-3 text-xs font-semibold text-muted"><span class="h-px flex-1 bg-line"></span>OR<span class="h-px flex-1 bg-line"></span></div>
  <a href="<?= e(url('track.php')) ?>" class="btn btn-outline w-full"><?= icon('truck', 'h-5 w-5') ?> Track an order without signing in</a>
<?php });
