<?php
// edit_category.php - edit category info (name/description) and show ingredients in the category (with edit links)
require 'db_connect.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: ingredient_categories.php");
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['category_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') $errors[] = "Category name is required.";
    if (empty($errors)) {
        $u = $conn->prepare("UPDATE ingredient_category SET category_name = ?, description = ? WHERE category_id = ?");
        $u->bind_param("ssi", $name, $desc, $id);
        if ($u->execute()) {
            $_SESSION['success'] = "Category updated.";
            header("Location: ingredient_categories.php");
            exit;
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $u->close();
    }
}

// fetch category
$stmt = $conn->prepare("SELECT * FROM ingredient_category WHERE category_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$category) {
    header("Location: ingredient_categories.php");
    exit;
}

// fetch ingredients in this category
$ings = $conn->prepare("SELECT ingredient_id, name, current_stock, unit FROM ingredient WHERE category_id = ? ORDER BY name ASC");
$ings->bind_param("i", $id);
$ings->execute();
$ings_res = $ings->get_result();
$ings->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Edit Category - <?=htmlspecialchars($category['category_name'])?></title>
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
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:20px;font-weight:800;">Edit Category</div>
            <div class="small-muted" style="margin-top:6px;"><?=htmlspecialchars($category['category_name'])?></div>
          </div>
          <div>
            <a class="btn-cancel" href="ingredient_categories.php">Back to Categories</a>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-error"><ul style="margin:0;padding-left:18px;"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
        <?php endif; ?>

        <form method="post" style="margin-top:12px;">
          <div class="form-grid">
            <div class="form-group">
              <label>Category name</label>
              <input name="category_name" required value="<?php echo htmlspecialchars($_POST['category_name'] ?? $category['category_name']); ?>">
            </div>
            <div class="form-group">
              <label>Description</label>
              <input name="description" value="<?php echo htmlspecialchars($_POST['description'] ?? $category['description']); ?>">
            </div>
          </div>

          <div class="form-actions">
            <a class="btn-cancel" href="ingredient_categories.php">Cancel</a>
            <button class="btn-save" type="submit">Save Category</button>
          </div>
        </form>

        <div style="margin-top:18px;">
          <h3>Ingredients in this category</h3>
          <?php if ($ings_res->num_rows === 0): ?>
            <div class="empty-state">No ingredients in this category.</div>
          <?php else: ?>
            <?php while($ing = $ings_res->fetch_assoc()): ?>
              <div class="table-row" style="grid-template-columns:1fr 220px;">
                <div>
                  <strong><?=htmlspecialchars($ing['name'])?></strong>
                  <div class="small-muted"><?=number_format($ing['current_stock'],4)?> <?=htmlspecialchars($ing['unit'])?></div>
                </div>
                <div class="action-buttons">
                  <a class="btn-edit" href="edit_ingredient.php?id=<?=$ing['ingredient_id']?>">Edit Ingredient</a>
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