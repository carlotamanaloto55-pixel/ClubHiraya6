<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'clubhiraya';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get item ID
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($item_id <= 0) {
    $_SESSION['error'] = "Invalid item ID";
    header("Location: inventory.php");
    exit();
}

// Delete the item
try {
    $stmt = $pdo->prepare("DELETE FROM foods WHERE id = :id");
    $stmt->execute([':id' => $item_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Item deleted successfully!";
    } else {
        $_SESSION['error'] = "Item not found";
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Error deleting item: " . $e->getMessage();
}

header("Location: inventory.php");
exit();
?>