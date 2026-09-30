<?php
declare(strict_types=1);

/**
 * Plain-text email through PHP's mail(), which Hostinger supports. The From
 * address should be a mailbox on your own domain (MAIL_FROM, else SUPPORT_EMAIL)
 * or many providers will reject or spam-folder the message.
 */
function send_store_mail(string $to, string $subject, string $body): bool
{
    $from = defined('MAIL_FROM') && MAIL_FROM ? MAIL_FROM : SUPPORT_EMAIL;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) return false;
    $name = str_replace(['"', "\r", "\n"], '', STORE_NAME);
    $headers = [
        'From: "' . $name . '" <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) error_log('[storefront] mail() failed sending "' . $subject . '"');
    return $ok;
}
