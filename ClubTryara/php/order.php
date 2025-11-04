<?php
session_start();

function compute_order($order_items, $discountPercent = 0, $service_rate = null, $tax_rate = null) {
    // Use provided rates (as decimals, e.g. 0.10) or fall back to session or defaults
    if ($service_rate === null) {
        $service_rate = isset($_SESSION['service_charge']) ? (floatval($_SESSION['service_charge']) / 100.0) : 0.10;
    }
    if ($tax_rate === null) {
        $tax_rate = isset($_SESSION['tax']) ? (floatval($_SESSION['tax']) / 100.0) : 0.12;
    }

    $subtotal = 0;
    foreach ($order_items as $item) {
        $subtotal += $item['price'] * $item['qty'];
    }
    $service_charge = round($subtotal * $service_rate, 2);
    $tax = round($subtotal * $tax_rate, 2);

    $discount = 0;
    if ($discountPercent > 0) {
        $discount = round(($subtotal + $service_charge + $tax) * $discountPercent, 2);
    }

    $total = $subtotal + $service_charge + $tax - $discount;
    return [
        'subtotal' => $subtotal,
        'service_charge' => $service_charge,
        'tax' => $tax,
        'discount' => $discount,
        'total' => $total
    ];
}
?>