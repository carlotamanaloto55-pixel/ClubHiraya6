<?php
require_once "db_connect.php";

$sql = "INSERT INTO orders (discount, note) VALUES (0, '')";
if ($conn->query($sql) === TRUE) {
    $order_id = $conn->insert_id;
    echo json_encode(['ok' => 1, 'order_id' => $order_id]);
} else {
    echo json_encode(['ok' => 0, 'error' => $conn->error]);
}
$conn->close();
?>
