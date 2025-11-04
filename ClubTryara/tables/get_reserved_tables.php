<?php
// get_reserved_tables.php
// Return reserved/booked tables as JSON.
// Tries to be tolerant of capitalization/whitespace/different column names.
// Use ?debug=1 to show DB errors or the raw SQL result for troubleshooting.

header('Content-Type: application/json; charset=utf-8');
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

// Primary DB include we expect in this project (you said db_connect.php)
$primaryInclude = __DIR__ . '/../php/db_connect.php';

// Fallbacks if needed (keeps previous behavior, but primary should work)
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
        if (isset($conn) && ($conn instanceof PDO || $conn instanceof mysqli)) {
            $connected = true;
            break;
        }
        if (isset($pdo) && $pdo instanceof PDO) {
            $conn = $pdo;
            $connected = true;
            break;
        }
        if (isset($db) && ($db instanceof PDO || $db instanceof mysqli)) {
            $conn = $db;
            $connected = true;
            break;
        }
        if (isset($db_conn) && ($db_conn instanceof PDO || $db_conn instanceof mysqli)) {
            $conn = $db_conn;
            $connected = true;
            break;
        }
        // If include defined DB constants, try to connect with mysqli
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
            $h = DB_HOST; $u = DB_USER; $p = defined('DB_PASS') ? DB_PASS : ''; $n = DB_NAME;
            $try = @new mysqli($h, $u, $p, $n);
            if (!$try->connect_errno) {
                $conn = $try;
                $connected = true;
                break;
            } else {
                $errors[] = "mysqli connect failed using constants from {$inc}: " . $try->connect_error;
            }
        }
    } catch (Throwable $e) {
        $errors[] = "include {$inc} threw: " . $e->getMessage();
    }
}

// If still not connected, try environment variables as last resort
if (!$connected && getenv('DB_HOST') && getenv('DB_USER') && getenv('DB_NAME')) {
    $h = getenv('DB_HOST'); $u = getenv('DB_USER'); $p = getenv('DB_PASS') ?: ''; $n = getenv('DB_NAME');
    $try = @new mysqli($h, $u, $p, $n);
    if (!$try->connect_errno) {
        $conn = $try;
        $connected = true;
    } else {
        $errors[] = 'fallback mysqli connect failed: ' . $try->connect_error;
    }
}

try {
    if (!$connected || !$conn) {
        if ($debug) {
            echo json_encode(['success' => false, 'error' => 'No DB connection', 'details' => $errors], JSON_PRETTY_PRINT);
            exit;
        }
        // Production-safe: return empty array so UI shows "No reserved tables found."
        echo json_encode([]);
        exit;
    }

    // Make query tolerant: match status values loosely (case-insensitive, trim).
    // Map common column names into consistent output columns.
    // Note: SELECT the source columns that exist in your DB (we try several aliases).
    $sql = "
      SELECT
        COALESCE(`id`, `table_id`, NULL) AS id,
        COALESCE(`name`, `guest_name`, `guest`, '') AS name,
        COALESCE(`table_number`, `table_no`, `number`, CAST(`id` AS CHAR)) AS table_number,
        COALESCE(`party_size`, `pax`, `seats`, 0) AS party_size,
        COALESCE(`status`, `reservation_status`, '') AS status,
        COALESCE(`price`, `reservation_price`, 0) AS price
      FROM `tables`
      WHERE (
        LOWER(TRIM(COALESCE(`status`, ''))) LIKE '%reserv%' OR
        LOWER(TRIM(COALESCE(`status`, ''))) LIKE '%book%' OR
        LOWER(TRIM(COALESCE(`status`, ''))) = 'reserved' OR
        LOWER(TRIM(COALESCE(`status`, ''))) = 'booked'
      )
      ORDER BY CAST(COALESCE(`table_number`, `table_no`, `id`) AS UNSIGNED), COALESCE(`table_number`, `table_no`, `id`) ASC
    ";

    // If using mysqli connection
    if ($conn instanceof mysqli) {
        // ensure charset
        $conn->set_charset('utf8mb4');
        $res = $conn->query($sql);
        if ($res === false) {
            throw new Exception('mysqli query failed: ' . $conn->error);
        }
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        exit;
    }

    // If using PDO
    if ($conn instanceof PDO) {
        $conn->exec("SET NAMES 'utf8mb4'");
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute();
        if (!$ok) {
            $err = $stmt->errorInfo();
            throw new Exception('PDO execute failed: ' . json_encode($err));
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array_values($rows));
        exit;
    }

    // unknown connection type
    if ($debug) {
        echo json_encode(['success' => false, 'error' => 'Unknown DB connection type', 'type' => gettype($conn)], JSON_PRETTY_PRINT);
        exit;
    }
    echo json_encode([]);
    exit;

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