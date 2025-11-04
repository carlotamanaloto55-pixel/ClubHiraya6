<?php
require_once "db_connect.php";

$data = json_decode(file_get_contents("php://input"), true);
$order_id = intval($data['order_id']);
$food_id = intval($data['food_id']);
$qty = intval($data['qty']);

$sql = "INSERT INTO order_items (order_id, food_id, qty) VALUES ($order_id, $food_id, $qty)";
if ($conn->query($sql) === TRUE) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => $conn->error]);
}
$conn->close();
?>
