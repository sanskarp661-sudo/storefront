<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
$email = '';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = post_string('email', 200);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        request_password_reset($email);
        $sent = true;
    }
}

render_auth_page('Reset password', 'Forgot your password?', "Enter your account email and we'll send you a link to choose a new one.", function () use ($sent, $email, $error) { ?>
  <?php if ($sent): ?>
    <div class="rounded-2xl bg-emerald-50 p-5 text-sm text-emerald-800 ring-1 ring-emerald-200">
      <p class="flex items-center gap-2 font-bold"><?= icon('mail', 'h-5 w-5') ?> Check your inbox</p>
      <p class="mt-1">If an account exists for <strong><?= e($email) ?></strong>, a reset link is on its way. It works for <?= RESET_TOKEN_MINUTES ?> minutes. Don't forget to check your spam folder.</p>
    </div>
    <a href="<?= e(url('login.php')) ?>" class="btn btn-outline mt-6 w-full"><?= icon('arrow-left', 'h-4 w-4') ?> Back to sign in</a>
  <?php else: ?>
    <form method="post" action="<?= e(url('forgot-password.php')) ?>" class="space-y-4">
      <?= csrf_field() ?>
      <?= form_field('email', 'Email address', ['email' => $email], $error ? ['email' => $error] : [], ['type' => 'email', 'autocomplete' => 'email', 'required' => true, 'autofocus' => true]) ?>
      <button type="submit" class="btn btn-primary btn-lg w-full"><?= icon('mail', 'h-5 w-5') ?> Send reset link</button>
      <a href="<?= e(url('login.php')) ?>" class="block text-center text-sm font-bold text-brand hover:underline">Back to sign in</a>
    </form>
  <?php endif; ?>
<?php });
