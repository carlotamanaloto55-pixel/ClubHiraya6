<?php
// api/get_tables.php
header('Content-Type: application/json; charset=utf-8');

// DEV: show errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// require the DB helper - make sure this filename matches the file in your api/ folder
require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("SELECT id, name, status, seats, IFNULL(guest,'') AS guest, updated_at FROM `tables` ORDER BY id ASC");
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}