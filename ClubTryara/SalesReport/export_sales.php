<?php
// export_sales.php
session_start();

// OPTIONAL: require authentication here
// if (!isset($_SESSION['user_id'])) { http_response_code(401); echo 'Unauthorized'; exit; }

require_once __DIR__ . '/../php/db_connect.php'; // adjust path if your db_connect.php is elsewhere

// Filename with timestamp
$filename = 'sales_report_export_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$out = fopen('php://output', 'w');

// CSV header - adjust column names to match your sales report table
fputcsv($out, ['order_id','date','total','discount','note']);

// TODO: Adjust SQL to match your sales table and columns
$sql = "SELECT id AS order_id, created_at AS date, total_amount AS total, discount AS discount, note AS note
        FROM sales_report
        ORDER BY created_at DESC";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['order_id'],
            $row['date'],
            $row['total'],
            $row['discount'],
            $row['note']
        ]);
    }
    $result->free();
}

fclose($out);
exit;