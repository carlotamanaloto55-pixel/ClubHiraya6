<?php
require_once "db_connect.php";

$data = json_decode(file_get_contents("php://input"), true);
$order_id = intval($data['order_id']);
$discount = floatval($data['discount']);

$sql = "UPDATE orders SET discount = $discount WHERE id = $order_id";
echo json_encode(['ok' => $conn->query($sql) ? true : false]);
$conn->close();
?>
