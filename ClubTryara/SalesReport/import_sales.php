<?php
// import_sales.php
session_start();

// OPTIONAL: require authentication here
// if (!isset($_SESSION['user_id'])) { header('Location: ../settings/Settings.php'); exit; }

require_once __DIR__ . '/../php/db_connect.php'; // adjust path as needed

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['sales_csv'])) {
    header('Location: ../settings/Settings.php');
    exit;
}

$file = $_FILES['sales_csv'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    // handle upload error
    header('Location: ../settings/Settings.php');
    exit;
}

// Basic file type check (not foolproof)
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    header('Location: ../settings/Settings.php');
    exit;
}

$tmp = $file['tmp_name'];
$handle = fopen($tmp, 'r');
if ($handle === false) {
    header('Location: ../settings/Settings.php');
    exit;
}

// Read header row to know column order (optional)
$header = fgetcsv($handle);
if ($header === false) {
    fclose($handle);
    header('Location: ../settings/Settings.php');
    exit;
}

// Expected header example: order_id,date,total,discount,note
// Adjust parsing below to match your CSV layout
while (($row = fgetcsv($handle)) !== false) {
    // Skip empty rows
    if (count($row) < 1) continue;

    // Basic sanitation/validation - adapt as needed
    $order_id = isset($row[0]) ? intval($row[0]) : 0;
    $date = isset($row[1]) ? $conn->real_escape_string($row[1]) : date('Y-m-d H:i:s');
    $total = isset($row[2]) ? floatval($row[2]) : 0.0;
    $discount = isset($row[3]) ? floatval($row[3]) : 0.0;
    $note = isset($row[4]) ? $conn->real_escape_string($row[4]) : '';

    // Example insertion/update - adapt to your schema.
    // This inserts into sales_report table and updates if the primary key exists.
    $sql = "INSERT INTO sales_report (id, created_at, total_amount, discount, note)
            VALUES ($order_id, '$date', $total, $discount, '$note')
            ON DUPLICATE KEY UPDATE created_at = '$date', total_amount = $total, discount = $discount, note = '$note'";

    $conn->query($sql);
}

fclose($handle);

// Redirect back to settings UI
header('Location: ../settings/Settings.php');
exit;