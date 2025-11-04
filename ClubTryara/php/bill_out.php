<?php
session_start();
require_once "db_connect.php";

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id === 0) {
    echo "<h3>No order selected.</h3>";
    exit;
}

// Fetch order info
$order_sql = "SELECT * FROM orders WHERE id = $order_id";
$order_result = $conn->query($order_sql);
if ($order_result->num_rows === 0) {
    echo "<h3>Order not found.</h3>";
    exit;
}
$order = $order_result->fetch_assoc();

// Fetch items for the order
$sql = "SELECT f.name, f.price, i.qty, (f.price * i.qty) AS total
        FROM order_items i
        JOIN foods f ON i.food_id = f.id
        WHERE i.order_id = $order_id";
$result = $conn->query($sql);

$subtotal = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Summary - POS Club Hiraya</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f7f7f7;
        padding: 30px;
    }
    .summary-box {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        width: 500px;
        margin: 0 auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    th, td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        text-align: left;
    }
    .total {
        font-weight: bold;
    }
    button {
        background: #4CAF50;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    }
</style>
</head>
<body>

<div class="summary-box">
    <h2>Order Summary</h2>
    <p><b>Order ID:</b> <?= $order['id'] ?></p>

    <table>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Total</th>
        </tr>

        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= $row['qty'] ?></td>
                <td>₱<?= number_format($row['price'], 2) ?></td>
                <td>₱<?= number_format($row['total'], 2) ?></td>
            </tr>
            <?php $subtotal += $row['total']; ?>
        <?php endwhile; ?>
    </table>

    <?php
    // read service & tax from session (percent values like 10,12)
    $service_percent = isset($_SESSION['service_charge']) ? floatval($_SESSION['service_charge']) : 10.0;
    $tax_percent = isset($_SESSION['tax']) ? floatval($_SESSION['tax']) : 12.0;

    $service = $subtotal * ($service_percent / 100.0);
    $tax = $subtotal * ($tax_percent / 100.0);
    $discount = $order['discount'] * ($subtotal + $service + $tax);
    $total = $subtotal + $service + $tax - $discount;
    ?>

    <p>Subtotal: ₱<?= number_format($subtotal, 2) ?></p>
    <p>Service Charge (<?= number_format($service_percent, 2) ?>%): ₱<?= number_format($service, 2) ?></p>
    <p>Tax (<?= number_format($tax_percent, 2) ?>%): ₱<?= number_format($tax, 2) ?></p>
    <p>Discount: ₱<?= number_format($discount, 2) ?></p>
    <h3>Total: ₱<?= number_format($total, 2) ?></h3>

    <form action="update_inventory.php" method="POST">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <button type="submit">Bill Out</button>
    </form>
</div>

</body>
</html>

<?php $conn->close(); ?>