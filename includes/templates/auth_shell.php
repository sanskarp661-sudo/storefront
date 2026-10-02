<?php
declare(strict_types=1);

/** Two-column layout for sign-in / register / password pages. */
function render_auth_page(string $title, string $heading, string $sub, callable $form): void
{
    render_page($title, function () use ($heading, $sub, $form) {
        $art = null;
        try {
            foreach (get_categories() as $c) if ($c['image_url']) { $art = $c['image_url']; break; }
        } catch (Throwable $e) {
        }
        $logoLight = true; ?>
    <div class="grid border-b border-line lg:min-h-[760px] lg:grid-cols-2">
      <div class="relative hidden overflow-hidden bg-ink text-white lg:block">
        <?php if ($art): ?><img src="<?= e($art) ?>" alt="" class="absolute inset-0 h-full w-full object-cover object-top opacity-70"><?php else: ?><div class="ph-dk absolute inset-0"></div><?php endif; ?>
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-black/30"></div>
        <div class="relative flex h-full flex-col justify-between p-12">
          <a href="<?= e(url('')) ?>" class="logo !text-white"><?php require __DIR__ . '/logo.php'; ?><span><?= e(STORE_NAME) ?></span></a>
          <div class="flex max-w-[460px] flex-col gap-3.5">
            <h2 class="font-display text-[64px] leading-none text-white">Your orders, wishlist and addresses.</h2>
            <p class="text-base text-[#cfc8bd]">Track every order, save addresses for faster checkout, and keep a wishlist. Pay cash on delivery.</p>
          </div>
        </div>
      </div>
      <div class="flex items-center justify-center px-4 py-10 sm:px-8 lg:py-12">
        <div class="flex w-full max-w-[440px] flex-col gap-6">
          <div class="flex flex-col gap-1.5"><h1 class="h2"><?= e($heading) ?></h1><p class="text-ink-soft [&_a]:font-semibold [&_a]:text-ink [&_a]:underline [&_a]:underline-offset-4"><?= $sub ?></p></div>
          <div><?php $form(); ?></div>
        </div>
      </div>
    </div>
    <?php }, ['noindex' => true, 'active' => 'account']);
}

function password_field(string $name, string $label, ?string $error, string $autocomplete, string $hint = ''): string
{
    return '<div><label class="field-label" for="' . e($name) . '">' . e($label) . '</label>'
        . '<div class="relative"><input id="' . e($name) . '" name="' . e($name) . '" type="password" autocomplete="' . e($autocomplete) . '" class="field-input pr-12"' . ($error ? ' aria-invalid="true"' : '') . '>'
        . '<button type="button" data-toggle-password="' . e($name) . '" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-ink-soft hover:text-ink" aria-label="Show password">' . icon('eye', 'h-5 w-5') . '</button></div>'
        . ($error ? '<p class="field-error">' . e($error) . '</p>' : ($hint ? '<p class="mt-1 text-xs text-ink-soft">' . e($hint) . '</p>' : '')) . '</div>';
}
