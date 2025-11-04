<?php
// Lightweight integration test for inventory CRUD operations (CLI script).
// Usage: php tests/inventory_integration_test.php
// Make sure this script has access to the same DB used by your app.

$host = 'localhost';
$dbname = 'clubhiraya';
$user = 'root';
$pass = '';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "ERROR: Could not connect to DB: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

function fail($msg) {
    echo "FAIL: $msg" . PHP_EOL;
    exit(1);
}
function ok($msg) {
    echo "OK: $msg" . PHP_EOL;
}

// 1) Create unique item
$unique = 'test_item_' . bin2hex(random_bytes(4));
$price = 123.45;
$category = 'TestCategory';
$stock = 7;
$image = 'test.jpg';

echo "Testing create..." . PHP_EOL;
$insert = $pdo->prepare("INSERT INTO foods (name, price, category, stock, image) VALUES (?, ?, ?, ?, ?)");
$insert->execute([$unique, $price, $category, $stock, $image]);
$id = $pdo->lastInsertId();
if (!$id) fail("Insert returned no id");
ok("Inserted id $id");

// 2) Read back
$stmt = $pdo->prepare("SELECT * FROM foods WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) fail("Could not read back inserted item");
if ($row['name'] !== $unique) fail("Name mismatch after insert");
ok("Read back and validated");

// 3) Update
$newPrice = 222.22;
$update = $pdo->prepare("UPDATE foods SET price = ? WHERE id = ?");
$update->execute([$newPrice, $id]);
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (abs(floatval($row['price']) - $newPrice) > 0.001) fail("Price did not update");
ok("Updated price successfully");

// 4) Delete
$del = $pdo->prepare("DELETE FROM foods WHERE id = ?");
$del->execute([$id]);
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) fail("Item still exists after delete");
ok("Deleted item OK");

echo "All integration steps passed." . PHP_EOL;
exit(0);