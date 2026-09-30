<?php
declare(strict_types=1);

/** Two-column layout for sign-in / register / password pages. */
function render_auth_page(string $title, string $heading, string $sub, callable $form): void
{
    render_page($title, function () use ($heading, $sub, $form) { ?>
    <div class="container-page py-8 lg:py-12">
      <div class="mx-auto grid max-w-5xl overflow-hidden rounded-[1.75rem] bg-white shadow-lift ring-1 ring-line lg:grid-cols-2">
        <div class="relative hidden overflow-hidden bg-gradient-to-br from-brand via-indigo-600 to-violet-700 p-10 text-white lg:block">
          <div class="hero-grid absolute inset-0"></div>
          <div class="absolute -bottom-20 -right-20 h-72 w-72 rounded-full bg-accent/30 blur-3xl"></div>
          <div class="relative">
            <a href="<?= e(url('')) ?>" class="flex items-center gap-2.5"><?php require __DIR__ . '/logo.php'; ?><span class="text-xl font-extrabold"><?= e(STORE_NAME) ?></span></a>
            <h2 class="mt-12 text-3xl font-extrabold leading-tight">Your account,<br>all in one place.</h2>
            <ul class="mt-8 space-y-5">
              <?php foreach ([['package', 'Track every order', 'See live status, delivery and invoice details.'], ['map-pin', 'Save your addresses', 'Check out in seconds next time.'], ['heart', 'Keep a wishlist', 'Save products you love for later.'], ['banknote', 'Pay on delivery', 'No cards needed — pay cash when it arrives.']] as [$ic, $t, $d]): ?>
                <li class="flex gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 backdrop-blur"><?= icon($ic, 'h-5 w-5') ?></span><div><p class="font-bold"><?= e($t) ?></p><p class="text-sm text-white/75"><?= e($d) ?></p></div></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <div class="p-7 sm:p-10">
          <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl"><?= e($heading) ?></h1>
          <p class="mt-1.5 text-sm text-ink-soft"><?= $sub ?></p>
          <div class="mt-7"><?php $form(); ?></div>
        </div>
      </div>
    </div>
    <?php }, ['noindex' => true, 'active' => 'account']);
}

function password_field(string $name, string $label, ?string $error, string $autocomplete, string $hint = ''): string
{
    return '<div><label class="field-label" for="' . e($name) . '">' . e($label) . '</label>'
        . '<div class="relative"><input id="' . e($name) . '" name="' . e($name) . '" type="password" autocomplete="' . e($autocomplete) . '" class="field-input pr-12"' . ($error ? ' aria-invalid="true"' : '') . '>'
        . '<button type="button" data-toggle-password="' . e($name) . '" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-muted hover:text-ink" aria-label="Show password">' . icon('eye', 'h-5 w-5') . '</button></div>'
        . ($error ? '<p class="field-error">' . e($error) . '</p>' : ($hint ? '<p class="mt-1 text-xs text-ink-soft">' . e($hint) . '</p>' : '')) . '</div>';
}
