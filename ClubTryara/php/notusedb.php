<?php
// DB connection - update these values if your phpMyAdmin / MySQL uses different credentials
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // default for local installations like XAMPP; change if needed
$DB_NAME = 'clubhiraya';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    // If accessed by browser, it's helpful to return JSON error for the ajax caller
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed: ' . $mysqli->connect_error]);
    exit;
}

// Ensure UTF-8 (useful if product names contain non-ASCII characters)
$mysqli->set_charset('utf8mb4');