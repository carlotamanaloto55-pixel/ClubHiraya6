<?php
require_once "db_connect.php";

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id']);

$sql = "DELETE FROM order_items WHERE id = $id";
echo json_encode(['ok' => $conn->query($sql) ? true : false]);
$conn->close();
?>
