<?php
require_once "db_connect.php";

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id']);
$delta = intval($data['delta']);

$sql = "UPDATE order_items SET qty = GREATEST(qty + $delta, 0) WHERE id = $id";
$conn->query($sql);

// Delete if qty <= 0
$conn->query("DELETE FROM order_items WHERE qty <= 0");
echo json_encode(['ok' => true]);
$conn->close();
?>
