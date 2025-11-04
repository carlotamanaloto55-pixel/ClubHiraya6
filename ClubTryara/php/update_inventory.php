<?php
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);
$ok = true;
$error = "";

foreach ($data as $item) {
    $id = intval($item['id']);
    $qty = intval($item['qty']);

    // Check stock first
    $check = $conn->query("SELECT stock FROM foods WHERE id=$id")->fetch_assoc();
    if ($check['stock'] < $qty) {
        $ok = false;
        $error = "Not enough stock for item ID $id";
        break;
    }

    // Deduct stock
    $conn->query("UPDATE foods SET stock = stock - $qty WHERE id = $id");
}

echo json_encode(['ok' => $ok, 'error' => $error]);
$conn->close();
?>
