<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    logout_customer();
    flash('success', 'You have been signed out.');
}
redirect(url(''));
