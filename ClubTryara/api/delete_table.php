<?php
// api/delete_table.php
// Delete a table row by id
// Accepts POST body JSON { id: <int> } or form-encoded id=...

header('Content-Type: application/json; charset=utf-8');

// DEV: show errors for debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

// read input as JSON or form
$raw = file_get_contents('php://input');
$id = null;
if ($raw) {
    $data = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($data['id'])) {
        $id = (int)$data['id'];
    }
}
if ($id === null && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
}

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid id']);
    exit;
}

try {
    // You may want to check for constraints (e.g., prevent deletion if reservations exist)
    // Basic delete:
    $stmt = $pdo->prepare("DELETE FROM `tables` WHERE id = :id");
    $stmt->execute([':id' => $id]);
    // Affected rows:
    $count = $stmt->rowCount();

    if ($count === 0) {
        // No row removed -> not found
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Table not found']);
        exit;
    }

    echo json_encode(['success' => true, 'deleted_id' => $id]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>