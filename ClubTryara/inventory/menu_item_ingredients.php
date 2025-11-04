<?php
// menu_item_ingredients.php - styled to match inventory.php; added "Edit Ingredient" button per row
require 'db_connect.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
  header("Location: menu_items.php");
  exit;
}

$stmt = $conn->prepare("SELECT * FROM menu_item WHERE menu_item_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$menu = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt2 = $conn->prepare("
  SELECT mii.*, i.name AS ingredient_name, i.unit AS base_unit, i.ingredient_id
  FROM menu_item_ingredient mii
  JOIN ingredient i ON mii.ingredient_id = i.ingredient_id
  WHERE mii.menu_item_id = ?
  ORDER BY mii.mii_id ASC
");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$rows = $stmt2->get_result();
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Recipe - <?=htmlspecialchars($menu['name'] ?? '')?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <!-- Sidebar (same classes/structure as inventory.php) -->
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
      <div class="sidebar-header">
          <img src="../images/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
      </div>
      <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
          <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/home.png" alt="Home"></span><span>Home</span></a>
          <a href="../php/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/table.png" alt="Tables"></span><span>Tables</span></a>
          <a href="inventory.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
          <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/sales.png" alt="Sales"></span><span>Sales Report</span></a>
          <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/setting.png" alt="Settings"></span><span>Settings</span></a>
      </nav>
      <div style="flex:1" aria-hidden="true"></div>
      <button class="sidebar-logout" type="button" aria-label="Logout">Logout</button>
  </aside>

  <main class="main-content" role="main" aria-label="Main content">
    <!-- Topbar matches inventory.php -->
    <div class="topbar">
      <div class="search-section">
        <input class="search-input" placeholder="Filter recipe items..." oninput="filterRows(this.value)">
      </div>
      <div class="navlinks" style="display:flex;gap:14px;align-items:center;">
        <a href="menu_items.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Back to Menu</a>
        <a href="ingredients.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Ingredients</a>
        <a href="inventory_transaction.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Transactions</a>
      </div>
    </div>

    <div class="inventory-container">
      <div class="form-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <div>
            <div style="font-size:20px;font-weight:800;"><?=htmlspecialchars($menu['name'] ?? 'Recipe')?></div>
            <div style="color:#666;margin-top:6px;">Ingredients used in this menu item</div>
          </div>
          <div>
            <a class="btn-cancel" href="menu_items.php" style="padding:10px 14px;display:inline-block;">Back to Menu</a>
          </div>
        </div>

        <?php if ($rows->num_rows === 0): ?>
          <div class="empty-state">No recipe defined for this menu item.</div>
        <?php else: ?>
          <div class="table-header" style="margin-top:6px;">
            <div>Ingredient</div>
            <div>Qty / Unit</div>
            <div class="header-actions"></div>
          </div>

          <?php while($r = $rows->fetch_assoc()): ?>
            <div class="table-row" style="grid-template-columns:1fr 220px 220px;">
              <div>
                <strong><?=htmlspecialchars($r['ingredient_name'])?></strong>
                <div class="small-muted"><?=htmlspecialchars($r['base_unit'])?> base unit</div>
              </div>
              <div style="text-align:right">
                <div style="font-weight:700;"><?=number_format($r['quantity'],4)?> <?=htmlspecialchars($r['unit'])?></div>
                <div class="small-muted">unit: <?=htmlspecialchars($r['unit'])?></div>
              </div>
              <div class="action-buttons">
                <!-- Edit menu_item_ingredient entry -->
                <a class="btn-edit" href="edit_menu_item_ingredients.php?mii_id=<?=$r['mii_id']?>">Edit Ingredient</a>
                <!-- Quick link to edit the base ingredient itself -->
                <a class="btn small" href="edit_ingredient.php?id=<?=$r['ingredient_id']?>" style="padding:8px 14px;background:#f3f4f6;color:#111;border-radius:8px;text-decoration:none;">Edit Base</a>
              </div>
            </div>
          <?php endwhile; ?>
        <?php endif; ?>

        <div style="margin-top:12px;">
          <a class="btn" href="menu_items.php">Back</a>
        </div>
      </div>
    </div>
  </main>

<script>
function filterRows(q){
  q=(q||'').toLowerCase();
  document.querySelectorAll('.table-row').forEach(function(r){
    const text = (r.textContent||'').toLowerCase();
    r.style.display = text.indexOf(q)!==-1 ? '' : 'none';
  });
}
</script>
</body>
</html>