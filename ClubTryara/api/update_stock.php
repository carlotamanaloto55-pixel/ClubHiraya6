<?php
// api/update_stock.php
// Expects JSON payload: { items: [ { id: <food_id>, qty: <quantity> }, ... ] }
// Updates the `foods` table stock = stock - qty safely within a transaction and using SELECT FOR UPDATE.
//
// IMPORTANT: Replace DB credentials with your own before using.

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!isset($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request, items missing.']);
    exit;
}

$items = $data['items'];
if (count($items) === 0) {
    echo json_encode(['success' => false, 'message' => 'No items to update.']);
    exit;
}

// DB config - set your real credentials
$dbHost = 'localhost';
$dbName = 'clubhiraya';      // update if different
$dbUser = 'root';    // <-- REPLACE
$dbPass = '';// <-- REPLACE
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $pdo->beginTransaction();

    $errors = [];

    $selectStmt = $pdo->prepare('SELECT stock, name FROM foods WHERE id = ? FOR UPDATE');
    $updateStmt = $pdo->prepare('UPDATE foods SET stock = stock - ? WHERE id = ?');

    foreach ($items as $it) {
        $id = isset($it['id']) ? (int)$it['id'] : 0;
        $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
        if ($id <= 0 || $qty <= 0) {
            $errors[] = "Invalid item data (id={$id}, qty={$qty}).";
            continue;
        }

        $selectStmt->execute([$id]);
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $errors[] = "Item id {$id} not found.";
            continue;
        }

        $stock = (int)$row['stock'];
        $name = $row['name'];
        if ($stock < $qty) {
            $errors[] = "Insufficient stock for {$name} (id={$id}). Available: {$stock}, requested: {$qty}.";
            continue;
        }

        $updateStmt->execute([$qty, $id]);
    }

    if (count($errors) > 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;

} catch (PDOException $ex) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $ex->getMessage()]);
    exit;
}