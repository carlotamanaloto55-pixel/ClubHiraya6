<?php
// api/db.php
// Database connection helper using PDO. Update the credentials if needed.

// DEV: show errors during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

$DB_HOST = '127.0.0.1';
$DB_NAME = 'clubhiraya'; // make sure this DB exists
$DB_USER = 'root';
$DB_PASS = ''; // default for XAMPP; change if you set a password
$DB_CHAR = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHAR}";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}