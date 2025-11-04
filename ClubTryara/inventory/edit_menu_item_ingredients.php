<?php
// edit_menu_item_ingredients.php - edit a single menu_item_ingredient entry (quantity/unit)
// Form uses same classes/layout as edit.php. Back button goes to inventory.php per request.
require 'db_connect.php';
session_start();

$mii_id = isset($_GET['mii_id']) ? (int)$_GET['mii_id'] : 0;
if ($mii_id <= 0) {
    $_SESSION['error'] = "Invalid entry id.";
    header("Location: menu_items.php");
    exit;
}

// fetch menu_item_ingredient with related menu item and ingredient data
$stmt = $conn->prepare("
  SELECT mii.*, mi.name AS menu_name, i.name AS ingredient_name, i.unit AS base_unit
  FROM menu_item_ingredient mii
  JOIN menu_item mi ON mii.menu_item_id = mi.menu_item_id
  JOIN ingredient i ON mii.ingredient_id = i.ingredient_id
  WHERE mii.mii_id = ?
");
$stmt->bind_param("i", $mii_id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$entry) {
    $_SESSION['error'] = "Record not found.";
    header("Location: menu_items.php");
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = $_POST['quantity'] ?? '';
    $unit = trim($_POST['unit'] ?? '');

    if (!is_numeric($quantity)) $errors[] = "Quantity must be a number.";
    if ($unit === '') $errors[] = "Unit is required.";

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE menu_item_ingredient SET quantity = ?, unit = ? WHERE mii_id = ?");
        $qty = floatval($quantity);
        $stmt->bind_param("dsi", $qty, $unit, $mii_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Entry updated.";
            $stmt->close();
            // after update redirect back to the menu item's recipe page so user sees updated recipe
            header("Location: menu_item_ingredients.php?id=" . urlencode($entry['menu_item_id']));
            exit;
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Edit Recipe Ingredient - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
    <div class="sidebar-header"><img src="../images/logo1.png" class="sidebar-header-img" alt="logo"></div>
    <nav class="sidebar-menu">
      <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/home.png"></span><span>Home</span></a>
      <a href="../php/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/table.png"></span><span>Tables</span></a>
      <a href="inventory.php" class="sidebar-btn active"><span class="sidebar-icon"><img src="../images/inventory.png"></span><span>Inventory</span></a>
      <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/sales.png"></span><span>Sales Report</span></a>
      <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/setting.png"></span><span>Settings</span></a>
    </nav>
    <div style="flex:1"></div>
    <button class="sidebar-logout">Logout</button>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div class="search-section"></div>
      <div class="navlinks" style="display:flex;gap:12px;">
        <a href="ingredients.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Ingredients</a>
        <a href="ingredient_categories.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Categories</a>
        <a href="inventory_transaction.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Transactions</a>
      </div>
    </div>

    <div class="inventory-container">
      <div class="form-card" style="max-width:900px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <div>
            <div style="font-size:20px;font-weight:800;">Edit Recipe Ingredient</div>
            <div style="color:#666;margin-top:6px;"><?=htmlspecialchars($entry['menu_name'])?> — <?=htmlspecialchars($entry['ingredient_name'])?></div>
          </div>
          <div>
            <!-- Back goes to inventory.php per your request -->
            <a href="inventory.php" class="btn-cancel" style="padding:10px 14px;display:inline-block;">Back to Inventory</a>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-error">
            <ul style="margin:0;padding-left:18px;">
              <?php foreach ($errors as $e): ?>
                <li><?=htmlspecialchars($e)?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post">
          <div class="form-grid">
            <div class="form-group">
              <label>Ingredient</label>
              <input type="text" value="<?=htmlspecialchars($entry['ingredient_name'])?>" disabled>
            </div>
            <div class="form-group">
              <label>Quantity</label>
              <input name="quantity" type="number" step="0.0001" required value="<?=htmlspecialchars($_POST['quantity'] ?? $entry['quantity'])?>">
            </div>
            <div class="form-group">
              <label>Unit</label>
              <input name="unit" required value="<?=htmlspecialchars($_POST['unit'] ?? $entry['unit'])?>">
            </div>
            <div class="form-group">
              <label>Menu Item</label>
              <input type="text" value="<?=htmlspecialchars($entry['menu_name'])?>" disabled>
            </div>
          </div>

          <div class="form-actions">
            <a href="inventory.php" class="btn-cancel">Cancel</a>
            <button class="btn-save" type="submit">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</body>
</html>