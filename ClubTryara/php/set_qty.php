<?php
require_once "db_connect.php";

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id']);
$qty = max(1, intval($data['qty']));

$sql = "UPDATE order_items SET qty = $qty WHERE id = $id";
echo json_encode(['ok' => $conn->query($sql) ? true : false]);
$conn->close();
?>
