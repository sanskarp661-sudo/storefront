<?php
declare(strict_types=1);

/** HTML-escape. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** URL path prefix when the store lives in a sub-folder (empty when at the domain root). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        // Pages live in the app root; scripts in webhooks/ and cron/ never build URLs.
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
        $base = ($docRoot !== '' && str_starts_with($appRoot, $docRoot))
            ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($appRoot, strlen($docRoot))), '/')
            : '';
    }
    return $base;
}

/** Builds a site URL, e.g. url('products.php', ['category' => 'Tools']). Null/empty params are dropped. */
function url(string $path = '', array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== null && $v !== '' && $v !== false);
    $query = $params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
    return base_path() . '/' . ltrim($path, '/') . $query;
}

function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $v;
}

function product_url(string $sku): string
{
    return url('product.php', ['sku' => $sku]);
}

function category_url(string $category): string
{
    return url('products.php', ['category' => $category]);
}

function order_url(string $websiteOrderId, string $token, bool $placed = false): string
{
    return url('order.php', ['id' => $websiteOrderId, 'key' => $token, 'placed' => $placed ? '1' : null]);
}

function redirect(string $to, int $code = 303): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Formats money with Indian digit grouping, e.g. ₹1,23,456.78. */
function format_price(float $amount, string $currency = 'INR'): string
{
    $negative = $amount < 0;
    $fixed = number_format(abs($amount), 2, '.', '');
    [$int, $dec] = explode('.', $fixed);
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    }
    $symbol = $currency === 'INR' ? '₹' : $currency . ' ';
    return ($negative ? '-' : '') . $symbol . $int . '.' . $dec;
}

function format_quantity(float $q): string
{
    return floor($q) == $q ? (string) (int) $q : rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');
}

/** "30 Sep 2026" from a date or datetime string. */
function format_date(?string $value): string
{
    if (!$value) return '';
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('j M Y');
    } catch (Exception $e) {
        return $value;
    }
}

/** Inline SVG icon. */
function icon(string $name, string $class = 'h-5 w-5', float $stroke = 2): string
{
    $inner = ICONS[$name] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'
        . $stroke . '" stroke-linecap="round" stroke-linejoin="round" class="' . e($class) . '" aria-hidden="true">' . $inner . '</svg>';
}

// ---------------------------------------------------------------------------
// Sessions: CSRF tokens and flash messages

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Rejects POSTs without a valid CSRF token (protects cart and checkout from cross-site forms). */
function require_csrf(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if ($sent === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        flash('error', 'Your session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? url(''));
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function post_string(string $key, int $maxLength = 1000): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $maxLength) : '';
}

function get_string(string $key, int $maxLength = 200): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $maxLength) : '';
}

// ---------------------------------------------------------------------------
// Page rendering

/** Renders a page body inside the shared header/footer layout. */
function render_page(string $title, callable $body, array $options = []): void
{
    $pageTitle = $title === '' ? STORE_NAME : $title . ' · ' . STORE_NAME;
    $noindex = $options['noindex'] ?? false;
    $description = $options['description'] ?? 'Quality products, delivered across India.';
    $activeNav = $options['active'] ?? '';
    require __DIR__ . '/templates/header.php';
    $body();
    require __DIR__ . '/templates/footer.php';
}

function render_error_page(int $code): void
{
    $messages = [
        404 => ["We couldn't find that page", 'The product may have been discontinued, or the link might be wrong.'],
        500 => ['Something went wrong', "We're having trouble loading this page. Please try again in a moment."],
    ];
    [$heading, $text] = $messages[$code] ?? $messages[500];
    http_response_code($code);
    try {
        render_page($heading, function () use ($code, $heading, $text) {
            echo '<div class="container-page max-w-2xl py-12">' . empty_state($code === 404 ? 'search' : 'triangle-alert', ($code === 404 ? '404 · ' : '') . $heading, $text, url('products.php'), 'Browse products') . '</div>';
        });
    } catch (Throwable $e) {
        // The layout itself failed (e.g. database down): fall back to plain text.
        error_log('[storefront] error page failed: ' . $e);
        echo '<!doctype html><meta charset="utf-8"><title>' . e($heading) . '</title><p style="font-family:sans-serif;padding:2rem">' . e($text) . '</p>';
    }
}

function not_found(): never
{
    render_error_page(404);
    exit;
}
