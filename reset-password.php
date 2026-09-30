<?php
require __DIR__ . '/includes/bootstrap.php';

$token = get_string('token', 64) ?: post_string('token', 64);
$valid = find_reset_token($token) !== null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    require_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if ($password !== (string) ($_POST['password_confirm'] ?? '')) {
        $error = "The two passwords don't match.";
    } else {
        $error = complete_password_reset($token, $password);
        if ($error === null) {
            flash('success', 'Your password has been changed and you are signed in.');
            redirect(url('account.php'));
        }
    }
}

render_auth_page('Choose a new password', 'Choose a new password', $valid ? 'Pick something you haven\'t used here before.' : 'This link has expired or was already used.', function () use ($valid, $token, $error) { ?>
  <?php if (!$valid): ?>
    <a href="<?= e(url('forgot-password.php')) ?>" class="btn btn-primary w-full">Request a new link</a>
  <?php else: ?>
    <form method="post" action="<?= e(url('reset-password.php')) ?>" class="space-y-4">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <?php if ($error): ?><p role="alert" class="rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800"><?= e($error) ?></p><?php endif; ?>
      <?= password_field('password', 'New password', null, 'new-password', 'At least ' . PASSWORD_MIN_LENGTH . ' characters') ?>
      <?= password_field('password_confirm', 'Confirm new password', null, 'new-password') ?>
      <button type="submit" class="btn btn-primary btn-lg w-full">Save new password</button>
    </form>
  <?php endif; ?>
<?php });
