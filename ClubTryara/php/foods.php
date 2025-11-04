<?php
require_once "db_connect.php"; // ✅ connection file

header('Content-Type: application/json');

// Fetch all foods
$sql = "SELECT id, name, price, category, image, stock FROM foods";
$result = $conn->query($sql);

$foods = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $foods[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'category' => $row['category'],
            'image' => $row['image'],
            'stock' => $row['stock']
        ];
    }
}

echo json_encode($foods);
$conn->close();
?>
