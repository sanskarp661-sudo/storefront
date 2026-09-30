<?php
declare(strict_types=1);

/**
 * Payment methods. Enable/disable with PAYMENT_METHODS in config.php.
 *
 * To add online payment later: add an entry with 'kind' => 'online', build its
 * gateway flow (create payment → redirect → verify callback) where
 * place_order() checks 'kind', then add its id to PAYMENT_METHODS.
 * Until that flow exists, place_order() refuses any non-offline method.
 */
const PAYMENT_METHOD_CATALOG = [
    'cod' => [
        'id' => 'cod',
        'kind' => 'offline',
        'label' => 'Cash on Delivery',
        'description' => 'Pay in cash when your order is delivered.',
        // The ERP order API has no payment field, so this goes at the top of the order notes.
        'erp_note' => 'Payment: Cash on Delivery (COD)',
        'order_page_note' => 'Pay in cash when your order is delivered. The final amount due is shown on your invoice.',
    ],
];

function payment_method(?string $id): ?array
{
    return $id !== null && isset(PAYMENT_METHOD_CATALOG[$id]) ? PAYMENT_METHOD_CATALOG[$id] : null;
}

/** Methods offered at checkout, in PAYMENT_METHODS order; falls back to COD if none are valid. */
function enabled_payment_methods(): array
{
    $methods = [];
    foreach (explode(',', (string) PAYMENT_METHODS) as $id) {
        $id = strtolower(trim($id));
        if (isset(PAYMENT_METHOD_CATALOG[$id])) $methods[$id] = PAYMENT_METHOD_CATALOG[$id];
    }
    return $methods ? array_values($methods) : [PAYMENT_METHOD_CATALOG['cod']];
}
