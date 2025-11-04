<?php
// api/get_availability.php
// Simple availability lookup: returns tables that have enough seats and are NOT reserved/occupied
// for the requested datetime range.
//
// Query params (GET):
//   date=YYYY-MM-DD    (required)
//   time=HH:MM          (required, 24h or 12h HH:MM acceptable)
//   duration=minutes    (optional, default 90)
//   seats=integer       (optional, default 1)
//
// Response:
//   { success: true, data: [ { id, name, seats, status }, ... ] }
//   or { success: false, error: "message" }

header('Content-Type: application/json; charset=utf-8');

// DEV: errors on screen for quick debugging; disable in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$time = isset($_GET['time']) ? trim($_GET['time']) : '';
$duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 90;
$seats = isset($_GET['seats']) ? (int)$_GET['seats'] : 1;

// basic validation
if ($date === '' || $time === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing date or time parameter']);
    exit;
}

// parse date/time into DateTime
$dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
if ($dt === false) {
    // Try a fallback: if time does not include leading zero (e.g. H:i)
    $dtAlt = DateTime::createFromFormat('Y-m-d g:i A', $date . ' ' . $time);
    if ($dtAlt === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid date/time format. Use YYYY-MM-DD and HH:MM']);
        exit;
    } else {
        $dt = $dtAlt;
    }
}

$start_dt = $dt->format('Y-m-d H:i:00');
$end_dt_obj = clone $dt;
$end_dt_obj->modify('+' . max(1, $duration) . ' minutes');
$end_dt = $end_dt_obj->format('Y-m-d H:i:00');

try {
    // We want tables that have seats >= requested seats and are NOT blocked by an overlapping reservation
    // Consider reservations overlapping if NOT (r.end <= requested_start OR r.start >= requested_end)
    // Also filter out tables that are currently occupied (optional — you may want to include them depending on your logic)

    // Prepared statement: select tables meeting seats, then exclude table_ids that have overlapping reservations
    $sql = "
        SELECT t.id, t.name, t.seats, t.status
        FROM `tables` t
        WHERE t.seats >= :seats
          AND t.id NOT IN (
            SELECT r.table_id
            FROM reservations r
            WHERE NOT (r.end <= :start_dt OR r.start >= :end_dt)
          )
        ORDER BY t.seats ASC, t.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':seats' => max(1, $seats),
        ':start_dt' => $start_dt,
        ':end_dt' => $end_dt
    ]);
    $rows = $stmt->fetchAll();

    // If you want to also respect table.status (e.g., exclude 'occupied' permanently), you can filter here.
    // For now we return whatever status is in the table row; frontend can decide how to present it.

    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}