<?php
// api/update_table.php
header('Content-Type: application/json; charset=utf-8');

// DEV: show errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
$status = isset($input['status']) ? trim($input['status']) : null;
$seats = isset($input['seats']) ? (int)$input['seats'] : null;
$guest = array_key_exists('guest', $input) ? trim($input['guest']) : null;
$name  = isset($input['name']) ? trim($input['name']) : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$allowedStatuses = ['available','occupied','reserved'];
$fields = [];
$params = [':id' => $id];

if ($status !== null) {
    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }
    $fields[] = "`status` = :status";
    $params[':status'] = $status;
}

if ($seats !== null) {
    if ($seats < 1 || $seats > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid seats']);
        exit;
    }
    $fields[] = "`seats` = :seats";
    $params[':seats'] = $seats;
}

if ($guest !== null) {
    if (strlen($guest) > 255) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Guest name too long']);
        exit;
    }
    $fields[] = "`guest` = :guest";
    $params[':guest'] = $guest;
}

if ($name !== null) {
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name too long']);
        exit;
    }
    $fields[] = "`name` = :name";
    $params[':name'] = $name;
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

$sql = "UPDATE `tables` SET " . implode(', ', $fields) . " WHERE id = :id LIMIT 1";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}