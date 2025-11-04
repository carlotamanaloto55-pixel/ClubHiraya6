<?php
// api/list_reservations.php
// Returns reservations for a specific date (YYYY-MM-DD).
// Query params:
//   date=YYYY-MM-DD  (optional — if omitted, returns next 50 reservations)
//
// Response:
//  { success: true, data: [ { id, table_id, table_name, guest, party_size, start, end, status }, ... ] }

header('Content-Type: application/json; charset=utf-8');

// DEV: errors on screen for quick debugging; disable in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

$date = isset($_GET['date']) ? trim($_GET['date']) : '';

try {
    if ($date) {
        // Validate date format YYYY-MM-DD
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if ($dt === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD']);
            exit;
        }
        $start = $dt->format('Y-m-d') . ' 00:00:00';
        $end   = $dt->format('Y-m-d') . ' 23:59:59';

        $sql = "
            SELECT r.id, r.table_id, t.name AS table_name, r.guest, r.party_size, r.start, r.end, r.status
            FROM reservations r
            LEFT JOIN `tables` t ON t.id = r.table_id
            WHERE r.start BETWEEN :start_dt AND :end_dt
            ORDER BY r.start ASC, r.table_id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start_dt' => $start, ':end_dt' => $end]);
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    } else {
        // No date: return recent upcoming reservations
        $sql = "
            SELECT r.id, r.table_id, t.name AS table_name, r.guest, r.party_size, r.start, r.end, r.status
            FROM reservations r
            LEFT JOIN `tables` t ON t.id = r.table_id
            ORDER BY r.start ASC
            LIMIT 100
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}   