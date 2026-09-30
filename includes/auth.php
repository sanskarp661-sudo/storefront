<?php
declare(strict_types=1);

/**
 * Customer accounts. Passwords are stored with password_hash(); sessions are
 * regenerated on sign-in; 5 wrong passwords lock an account for 15 minutes.
 */

const LOGIN_MAX_FAILURES = 5;
const LOGIN_LOCK_MINUTES = 15;
const PASSWORD_MIN_LENGTH = 8;
const RESET_TOKEN_MINUTES = 60;

function current_customer(): ?array
{
    static $customer = false;
    if ($customer !== false) return $customer;
    $id = (int) ($_SESSION['customer_id'] ?? 0);
    $customer = $id ? db_one('SELECT id, name, email, phone, created_at FROM customers WHERE id = ?', [$id]) : null;
    if ($id && !$customer) unset($_SESSION['customer_id']);
    return $customer;
}

/** Only same-site relative paths are allowed as post-login destinations. */
function safe_return_url(?string $url, string $fallback = ''): string
{
    $url = (string) $url;
    if ($url !== '' && $url[0] === '/' && !str_starts_with($url, '//') && !str_contains($url, '\\') && !preg_match('/[\r\n]/', $url)) {
        return $url;
    }
    return $fallback !== '' ? $fallback : url('account.php');
}

function require_login(): array
{
    $c = current_customer();
    if (!$c) redirect(url('login.php', ['return' => $_SERVER['REQUEST_URI'] ?? '']));
    return $c;
}

function login_customer(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $id;
    db_query('UPDATE customers SET failed_logins = 0, locked_until = NULL, last_login_at = UTC_TIMESTAMP(3) WHERE id = ?', [$id]);
}

function logout_customer(): void
{
    unset($_SESSION['customer_id']);
    session_regenerate_id(true);
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/** Returns ['ok' => true, 'id' => n] or ['ok' => false, 'errors' => [field => message]]. */
function register_customer(string $name, string $email, string $phone, string $password): array
{
    $errors = [];
    $email = normalize_email($email);
    $phoneNorm = $phone === '' ? null : normalize_indian_phone($phone);
    if (mb_strlen(trim($name)) < 2) $errors['name'] = 'Please enter your full name';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) $errors['email'] = 'Please enter a valid email address';
    if ($phone !== '' && $phoneNorm === null) $errors['phone'] = 'Please enter a valid 10-digit mobile number';
    if (strlen($password) < PASSWORD_MIN_LENGTH) $errors['password'] = 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters';
    if (!$errors && db_one('SELECT id FROM customers WHERE email = ?', [$email])) {
        $errors['email'] = 'An account with this email already exists. Sign in instead.';
    }
    if ($errors) return ['ok' => false, 'errors' => $errors];

    db_query(
        'INSERT INTO customers (name, email, phone, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))',
        [mb_substr(trim($name), 0, 120), $email, $phoneNorm, password_hash($password, PASSWORD_DEFAULT)]
    );
    return ['ok' => true, 'id' => (int) db()->lastInsertId()];
}

/** Returns ['ok' => true, 'id' => n] or ['ok' => false, 'error' => message]. */
function attempt_login(string $email, string $password): array
{
    $generic = ['ok' => false, 'error' => 'That email and password don\'t match. Please try again.'];
    $c = db_one('SELECT id, password_hash, failed_logins, locked_until FROM customers WHERE email = ?', [normalize_email($email)]);
    if (!$c) {
        password_verify($password, '$2y$12$Z8ex3q/FktFB8zrM5pWqhOk6suHJB1j8kfXSVhWVJf.LbC1tJcN8y'); // equalise timing with a real bcrypt hash
        return $generic;
    }
    if ($c['locked_until'] && strtotime($c['locked_until'] . ' UTC') > time()) {
        return ['ok' => false, 'error' => 'Too many attempts. Please wait ' . LOGIN_LOCK_MINUTES . ' minutes or reset your password.'];
    }
    if (!password_verify($password, $c['password_hash'])) {
        $failures = (int) $c['failed_logins'] + 1;
        db_query(
            'UPDATE customers SET failed_logins = ?, locked_until = ? WHERE id = ?',
            [$failures >= LOGIN_MAX_FAILURES ? 0 : $failures, $failures >= LOGIN_MAX_FAILURES ? gmdate('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60) : null, $c['id']]
        );
        return $generic;
    }
    if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
        db_query('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $c['id']]);
    }
    return ['ok' => true, 'id' => (int) $c['id']];
}

function change_password(int $customerId, string $current, string $new): ?string
{
    $c = db_one('SELECT password_hash FROM customers WHERE id = ?', [$customerId]);
    if (!$c || !password_verify($current, $c['password_hash'])) return 'Your current password is incorrect.';
    if (strlen($new) < PASSWORD_MIN_LENGTH) return 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters for your new password.';
    db_query('UPDATE customers SET password_hash = ?, updated_at = UTC_TIMESTAMP(3) WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $customerId]);
    return null;
}

/** Emails a reset link if the account exists. Always behaves the same, so it can't be used to discover accounts. */
function request_password_reset(string $email): void
{
    $c = db_one('SELECT id, name, email FROM customers WHERE email = ?', [normalize_email($email)]);
    if (!$c) return;
    $recent = (int) db_one('SELECT COUNT(*) AS n FROM password_resets WHERE customer_id = ? AND created_at > UTC_TIMESTAMP(3) - INTERVAL 1 HOUR', [$c['id']])['n'];
    if ($recent >= 3) return;

    $token = bin2hex(random_bytes(32));
    db_query(
        'INSERT INTO password_resets (token_hash, customer_id, expires_at, created_at) VALUES (?, ?, UTC_TIMESTAMP(3) + INTERVAL ' . RESET_TOKEN_MINUTES . ' MINUTE, UTC_TIMESTAMP(3))',
        [hash('sha256', $token), $c['id']]
    );
    $link = rtrim(SITE_URL ?: ((is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '')), '/') . url('reset-password.php', ['token' => $token]);
    send_store_mail(
        $c['email'],
        'Reset your ' . STORE_NAME . ' password',
        "Hi {$c['name']},\n\nWe received a request to reset your password. Open this link within " . RESET_TOKEN_MINUTES . " minutes to choose a new one:\n\n$link\n\nIf you didn't ask for this, you can ignore this email — your password won't change.\n\n— " . STORE_NAME
    );
}

function find_reset_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) return null;
    return db_one(
        'SELECT token_hash, customer_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP(3)',
        [hash('sha256', $token)]
    );
}

function complete_password_reset(string $token, string $password): ?string
{
    $row = find_reset_token($token);
    if (!$row) return 'This reset link has expired or was already used. Please request a new one.';
    if (strlen($password) < PASSWORD_MIN_LENGTH) return 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    db_query('UPDATE customers SET password_hash = ?, failed_logins = 0, locked_until = NULL, updated_at = UTC_TIMESTAMP(3) WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['customer_id']]);
    db_query('UPDATE password_resets SET used_at = UTC_TIMESTAMP(3) WHERE customer_id = ? AND used_at IS NULL', [$row['customer_id']]);
    login_customer((int) $row['customer_id']);
    return null;
}

// ---------------------------------------------------------------------------
// Saved addresses

function customer_addresses(int $customerId): array
{
    return db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$customerId]);
}

function customer_address(int $customerId, int $id): ?array
{
    return db_one('SELECT * FROM customer_addresses WHERE customer_id = ? AND id = ?', [$customerId, $id]);
}

/** Validates address input; returns [cleanValues, errors]. */
function validate_address(array $in): array
{
    $v = [
        'label' => in_array($in['label'] ?? '', ['Home', 'Office', 'Other'], true) ? $in['label'] : 'Home',
        'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 120),
        'phone' => normalize_indian_phone((string) ($in['phone'] ?? '')) ?? '',
        'address_line' => mb_substr(trim((string) ($in['address_line'] ?? '')), 0, 300),
        'city' => mb_substr(trim((string) ($in['city'] ?? '')), 0, 80),
        'state' => (string) ($in['state'] ?? ''),
        'pincode' => trim((string) ($in['pincode'] ?? '')),
    ];
    $e = [];
    if (mb_strlen($v['name']) < 2) $e['name'] = 'Please enter the full name';
    if ($v['phone'] === '') $e['phone'] = 'Please enter a valid 10-digit mobile number';
    if (mb_strlen($v['address_line']) < 5) $e['address_line'] = 'Please enter the street address';
    if (mb_strlen($v['city']) < 2) $e['city'] = 'Please enter the city';
    if (!in_array($v['state'], INDIAN_STATES, true)) $e['state'] = 'Please choose the state';
    if (!preg_match('/^[1-9][0-9]{5}$/', $v['pincode'])) $e['pincode'] = 'Please enter a valid 6-digit PIN code';
    return [$v, $e];
}

function save_address(int $customerId, array $v, ?int $id = null, bool $makeDefault = false): int
{
    $count = (int) db_one('SELECT COUNT(*) AS n FROM customer_addresses WHERE customer_id = ?', [$customerId])['n'];
    if ($id) {
        db_query(
            'UPDATE customer_addresses SET label = ?, name = ?, phone = ?, address_line = ?, city = ?, state = ?, pincode = ? WHERE id = ? AND customer_id = ?',
            [$v['label'], $v['name'], $v['phone'], $v['address_line'], $v['city'], $v['state'], $v['pincode'], $id, $customerId]
        );
    } else {
        if ($count >= 20) return 0;
        db_query(
            'INSERT INTO customer_addresses (customer_id, label, name, phone, address_line, city, state, pincode, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(3))',
            [$customerId, $v['label'], $v['name'], $v['phone'], $v['address_line'], $v['city'], $v['state'], $v['pincode']]
        );
        $id = (int) db()->lastInsertId();
    }
    if ($makeDefault || $count === 0) set_default_address($customerId, $id);
    return $id;
}

function set_default_address(int $customerId, int $id): void
{
    db_query('UPDATE customer_addresses SET is_default = (id = ?) WHERE customer_id = ?', [$id, $customerId]);
}

function delete_address(int $customerId, int $id): void
{
    $wasDefault = (bool) (customer_address($customerId, $id)['is_default'] ?? false);
    db_query('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [$id, $customerId]);
    if ($wasDefault) {
        $next = db_one('SELECT id FROM customer_addresses WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$customerId]);
        if ($next) set_default_address($customerId, (int) $next['id']);
    }
}

// ---------------------------------------------------------------------------
// Wishlist (signed-in customers)

function wishlist_skus(?array $customer = null): array
{
    static $cache = [];
    $customer ??= current_customer();
    if (!$customer) return [];
    $id = (int) $customer['id'];
    return $cache[$id] ??= array_column(db_all('SELECT sku FROM wishlist_items WHERE customer_id = ? ORDER BY created_at DESC', [$id]), 'sku');
}

function wishlist_toggle(int $customerId, string $sku): bool
{
    if (db_one('SELECT 1 AS x FROM wishlist_items WHERE customer_id = ? AND sku = ?', [$customerId, $sku])) {
        db_query('DELETE FROM wishlist_items WHERE customer_id = ? AND sku = ?', [$customerId, $sku]);
        return false;
    }
    db_query('INSERT IGNORE INTO wishlist_items (customer_id, sku, created_at) VALUES (?, ?, UTC_TIMESTAMP(3))', [$customerId, $sku]);
    return true;
}

function customer_orders(int $customerId, int $limit = 50): array
{
    return db_all(
        'SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count,
                (SELECT i.image_url FROM order_items i WHERE i.order_id = o.id AND i.image_url IS NOT NULL ORDER BY i.id LIMIT 1) AS first_image,
                (SELECT i.name FROM order_items i WHERE i.order_id = o.id ORDER BY i.id LIMIT 1) AS first_name
           FROM orders o WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT ' . (int) $limit,
        [$customerId]
    );
}

// ---------------------------------------------------------------------------
// Recently viewed (per session)

function remember_viewed(string $sku): void
{
    $list = array_values(array_diff($_SESSION['recently_viewed'] ?? [], [$sku]));
    array_unshift($list, $sku);
    $_SESSION['recently_viewed'] = array_slice($list, 0, 12);
}

function recently_viewed(string $exceptSku = '', int $limit = 8): array
{
    $skus = array_values(array_diff($_SESSION['recently_viewed'] ?? [], [$exceptSku]));
    $found = get_products_by_skus($skus);
    $out = [];
    foreach ($skus as $s) if (isset($found[$s])) $out[] = $found[$s];
    return array_slice($out, 0, $limit);
}
