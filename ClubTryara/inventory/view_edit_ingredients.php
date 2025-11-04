<?php
// view_edit_ingredients.php - view ingredient details using same design/layout as edit/inventory
require 'db_connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: ingredients.php");
    exit;
}

$stmt = $conn->prepare("SELECT i.*, ic.category_name FROM ingredient i LEFT JOIN ingredient_category ic ON i.category_id = ic.category_id WHERE i.ingredient_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$ing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ing) {
    header("Location: ingredients.php");
    exit;
}

// menu items referencing this ingredient
$q = $conn->prepare("
  SELECT DISTINCT mi.menu_item_id, mi.name
  FROM menu_item_ingredient mii
  JOIN menu_item mi ON mii.menu_item_id = mi.menu_item_id
  WHERE mii.ingredient_id = ?
  ORDER BY mi.name ASC
");
$q->bind_param("i", $id);
$q->execute();
$menuRows = $q->get_result();
$q->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>View Ingredient - <?=htmlspecialchars($ing['name'])?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
    <div class="sidebar-header"><img src="../images/logo1.png" class="sidebar-header-img" alt="logo"></div>
    <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
      <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/home.png" alt="Home"></span><span>Home</span></a>
      <a href="../php/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/table.png" alt="Tables"></span><span>Tables</span></a>
      <a href="inventory.php" class="sidebar-btn active"><span class="sidebar-icon"><img src="../images/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
      <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/sales.png" alt="Sales"></span><span>Sales Report</span></a>
      <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/setting.png" alt="Settings"></span><span>Settings</span></a>
    </nav>
    <div style="flex:1" aria-hidden="true"></div>
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
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:20px;font-weight:800;"><?=htmlspecialchars($ing['name'])?></div>
            <div class="small-muted"><?=htmlspecialchars($ing['category_name'])?></div>
          </div>
          <div>
            <a class="btn" href="edit_ingredient.php?id=<?=$ing['ingredient_id']?>">Edit Ingredient</a>
            <a class="btn secondary" href="ingredients.php" style="margin-left:8px;">Back</a>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
          <div class="form-card" style="padding:12px;">
            <div style="font-weight:700">Current Stock</div>
            <div style="font-size:20px;margin-top:8px;"><?=number_format($ing['current_stock'],4)?> <?=htmlspecialchars($ing['unit'])?></div>
            <div class="small-muted" style="margin-top:6px;">Par Level: <?=number_format($ing['par_level'],4)?></div>
          </div>

          <div class="form-card" style="padding:12px;">
            <div style="font-weight:700">Cost & Supplier</div>
            <div style="margin-top:8px;">₱<?=number_format($ing['cost_per_unit'],2)?></div>
            <div class="small-muted" style="margin-top:6px;">Supplier: <?=htmlspecialchars($ing['supplier'])?></div>
          </div>
        </div>

        <div style="margin-top:12px;">
          <h3 style="margin-bottom:8px;">Menu Items using this ingredient</h3>
          <?php if ($menuRows->num_rows === 0): ?>
            <div class="empty-state">No menu items reference this ingredient.</div>
          <?php else: ?>
            <?php while($m = $menuRows->fetch_assoc()): ?>
              <div class="table-row" style="grid-template-columns:1fr 220px;">
                <span><a href="menu_item_ingredients.php?id=<?=$m['menu_item_id']?>" style="text-decoration:none;color:inherit;"><?=htmlspecialchars($m['name'])?></a></span>
                <div class="action-buttons">
                  <a class="btn small" href="menu_item_ingredients.php?id=<?=$m['menu_item_id']?>">View Recipe</a>
                </div>
              </div>
            <?php endwhile; ?>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </main>
</body>
</html>