<?php
// get_reserved_tables.php
// Returns tables (available/reserved/all) as JSON tailored to your schema.
// Expects tables table with columns similar to: id, name, status, seats, price, table_number, party_size
// Supports: ?type=reserved|available|all  and ?debug=1 for verbose server-side errors.

header('Content-Type: application/json; charset=utf-8');
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';
$allowed = ['reserved', 'available', 'all'];
if (!in_array($type, $allowed, true)) $type = 'all';

// Primary include expected in this project (adjust path if your DB include is elsewhere)
$primaryInclude = __DIR__ . '/../php/db_connect.php';

// Fallbacks for common include names/locations
$includeCandidates = [
    $primaryInclude,
    __DIR__ . '/../php/db.php',
    __DIR__ . '/../php/conn.php',
    __DIR__ . '/../php/connection.php',
    __DIR__ . '/../php/config.php',
    __DIR__ . '/../../php/db_connect.php',
    __DIR__ . '/../../php/db.php'
];

$connected = false;
$conn = null;
$errors = [];

foreach ($includeCandidates as $inc) {
    if (!file_exists($inc)) {
        $errors[] = "include not found: {$inc}";
        continue;
    }
    try {
        require_once $inc;
        // common variable names exported by connection files:
        if (isset($conn) && ($conn instanceof PDO || $conn instanceof mysqli)) { $connected = true; break; }
        if (isset($pdo) && $pdo instanceof PDO) { $conn = $pdo; $connected = true; break; }
        if (isset($db) && ($db instanceof PDO || $db instanceof mysqli)) { $conn = $db; $connected = true; break; }
        if (isset($db_conn) && ($db_conn instanceof PDO || $db_conn instanceof mysqli)) { $conn = $db_conn; $connected = true; break; }

        // If include defined DB constants, try to connect with mysqli
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
            $h = DB_HOST; $u = DB_USER; $p = defined('DB_PASS') ? DB_PASS : ''; $n = DB_NAME;
            $try = @new mysqli($h, $u, $p, $n);
            if (!$try->connect_errno) { $conn = $try; $connected = true; break; }
            else { $errors[] = "mysqli connect failed using constants from {$inc}: " . $try->connect_error; }
        }
    } catch (Throwable $e) {
        $errors[] = "include {$inc} threw: " . $e->getMessage();
    }
}

// Last-resort environment variables
if (!$connected && getenv('DB_HOST') && getenv('DB_USER') && getenv('DB_NAME')) {
    $h = getenv('DB_HOST'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: ''; $n = getenv('DB_NAME');
    $try = @new mysqli($h, $u, $p, $n);
    if (!$try->connect_errno) { $conn = $try; $connected = true; }
    else { $errors[] = 'fallback mysqli connect failed: ' . $try->connect_error; }
}

try {
    if (!$connected || !$conn) {
        if ($debug) {
            echo json_encode(['success' => false, 'error' => 'No DB connection', 'details' => $errors], JSON_PRETTY_PRINT);
            exit;
        }
        echo json_encode([]); exit;
    }

    // Build WHERE clause according to requested type using the status column that exists in your schema.
    $statusExpr = "LOWER(TRIM(COALESCE(`status`, '')))";
    if ($type === 'reserved') {
        $where = "(" . $statusExpr . " LIKE '%reserv%' OR " . $statusExpr . " LIKE '%book%' OR " . $statusExpr . " = 'reserved' OR " . $statusExpr . " = 'booked')";
    } elseif ($type === 'available') {
        $where = "(" . $statusExpr . " LIKE '%avail%' OR " . $statusExpr . " LIKE '%free%' OR " . $statusExpr . " = 'available' OR " . $statusExpr . " = 'free' OR " . $statusExpr . " = '')";
    } else {
        $where = "1=1";
    }

    // Your DB schema (from tables.sql) has: id, name, status, seats, price, table_number, party_size
    // Select and alias into the shape the frontend expects: id, name, table_number, party_size, status, price
    // Use seats as fallback for party_size and id as fallback for table_number.
    $sql = "
      SELECT
        COALESCE(`id`, 0) AS id,
        COALESCE(`name`, '') AS name,
        COALESCE(`table_number`, CAST(`id` AS CHAR)) AS table_number,
        COALESCE(`party_size`, `seats`, 0) AS party_size,
        COALESCE(`status`, '') AS status,
        COALESCE(`price`, 0) AS price
      FROM `tables`
      WHERE {$where}
      ORDER BY CAST(COALESCE(`table_number`, CAST(`id` AS CHAR)) AS UNSIGNED) ASC
    ";

    if ($conn instanceof mysqli) {
        $conn->set_charset('utf8mb4');
        $res = $conn->query($sql);
        if ($res === false) {
            throw new Exception('mysqli query failed: ' . $conn->error . ' SQL: ' . $sql);
        }
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        exit;
    }

    if ($conn instanceof PDO) {
        $conn->exec("SET NAMES 'utf8mb4'");
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute();
        if (!$ok) { $err = $stmt->errorInfo(); throw new Exception('PDO execute failed: ' . json_encode($err) . ' SQL: ' . $sql); }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array_values($rows));
        exit;
    }

    // unknown connection type
    if ($debug) {
        echo json_encode(['success' => false, 'error' => 'Unknown DB connection type', 'type' => gettype($conn)], JSON_PRETTY_PRINT);
        exit;
    }
    echo json_encode([]); exit;

} catch (Exception $e) {
    http_response_code(500);
    if ($debug) {
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([]);
    }
    exit;
}
?>