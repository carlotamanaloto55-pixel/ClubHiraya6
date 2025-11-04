<?php
// get_products.php
// Located at ClubTryara/php/get_products.php

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // default for XAMPP
$DB_NAME = 'clubhiraya';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed: ' . $mysqli->connect_error]);
    exit;
}

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT id, name, price, category, image, description FROM foods WHERE 1=1";
$params = [];
$types = "";

// Filter by category if provided
if ($category !== '' && strtolower($category) !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Search filter
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql .= " ORDER BY category, name";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'DB prepare failed: ' . $mysqli->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$foods = [];
$categories_set = [];

// Path to the assets/foods folder on disk (one level up from php/)
$assets_dir = realpath(__DIR__ . '/../assets/foods');
$placeholder_filename = 'placeholder.png';

while ($row = $result->fetch_assoc()) {
    // ---- IMAGE PATH HANDLING ----
    $imgRaw = trim((string)$row['image']);

    // If it's an absolute URL (http/https), keep as-is
    if ($imgRaw !== '' && preg_match('#^https?://#i', $imgRaw)) {
        $imgUrl = $imgRaw;
    } else {
        // Determine filename (basename) to avoid directory traversal
        $basename = basename($imgRaw ?: '');

        // If there was no image provided, use placeholder
        if ($basename === '') {
            $basename = $placeholder_filename;
        }

        // Build filesystem path to check existence
        $fsPath = $assets_dir . DIRECTORY_SEPARATOR . $basename;

        if (!empty($assets_dir) && file_exists($fsPath)) {
            // Return a URL path that is correct when used from ClubTryara/index.php:
            // index.php references images as "assets/foods/...", so return that.
            // URL-encode the filename to handle spaces/symbols.
            $imgUrl = 'assets/foods/' . rawurlencode($basename);
        } else {
            // fallback to placeholder (ensure placeholder exists or still return placeholder path)
            // Placeholder assumed to be directly inside assets/
            $imgUrl = 'assets/' . rawurlencode($placeholder_filename);
        }
    }

    $foods[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'price' => (float)$row['price'],
        'category' => $row['category'],
        'image' => $imgUrl,
        'description' => $row['description']
    ];
    $categories_set[$row['category']] = true;
}

$stmt->close();

$categories = array_keys($categories_set);

// final JSON output
echo json_encode([
    'categories' => $categories,
    'foods' => $foods
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$mysqli->close();
?>
