<?php
// add_ingredient.php - copy of create.php logic adapted to ingredient table (uses mysqli from db_connect.php)
session_start();
require 'db_connect.php';

$feedback = '';
$errors = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], (string)$posted)) {
        $errors[] = "Invalid form submission (CSRF token mismatch).";
    }

    $name = trim($_POST['name'] ?? '');
    $category = (int)($_POST['category_id'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $stock = $_POST['current_stock'] ?? '';
    $cost = $_POST['cost_per_unit'] ?? '';
    $supplier = trim($_POST['supplier'] ?? '');
    $par = $_POST['par_level'] ?? 0;

    if ($name === '' || mb_strlen($name) < 1 || mb_strlen($name) > 255) $errors[] = "Name is required (1–255 chars).";
    if ($category <= 0) $errors[] = "Category is required.";
    if (!is_numeric($stock)) $errors[] = "Current stock must be numeric.";
    if (!is_numeric($cost)) $errors[] = "Cost must be numeric.";

    if (empty($errors)) {
        // Prevent duplicate name (optional)
        $check = $conn->prepare("SELECT ingredient_id FROM ingredient WHERE name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $errors[] = "Ingredient already exists.";
            $check->close();
        } else {
            $check->close();
            $stmt = $conn->prepare("INSERT INTO ingredient (category_id, name, unit, par_level, current_stock, cost_per_unit, supplier) VALUES (?,?,?,?,?,?,?)");
            $par = floatval($par);
            $stock = floatval($stock);
            $cost = floatval($cost);
            $stmt->bind_param("issddds", $category, $name, $unit, $par, $stock, $cost, $supplier);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Ingredient added successfully.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
                header("Location: ingredients.php");
                exit;
            } else {
                $errors[] = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }

    if (!empty($errors)) {
        $feedback = "<div class='alert alert-error'><ul style='margin:0;padding-left:18px;'>";
        foreach ($errors as $e) $feedback .= "<li>" . htmlspecialchars($e) . "</li>";
        $feedback .= "</ul></div>";
    }
}

// categories for select
$categories = $conn->query("SELECT * FROM ingredient_category ORDER BY category_name");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Add Ingredient - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
      <div class="sidebar-header"><img src="../assets/logos/logo1.png" class="sidebar-header-img" alt="logo"></div>
      <nav class="sidebar-menu">
        <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/home.png"></span><span>Home</span></a>
        <a href="../php/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/table.png"></span><span>Tables</span></a>
        <a href="inventory.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/inventory.png"></span><span>Inventory</span></a>
        <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/sales.png"></span><span>Sales Report</span></a>
        <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/setting.png"></span><span>Settings</span></a>
      </nav>
      <div style="flex:1"></div>
      <button class="sidebar-logout" type="button">Logout</button>
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
            <div style="font-size:20px;font-weight:800;">Add New Ingredient</div>
            <div style="color:#666;margin-top:6px;">Create a new ingredient used for recipes and stock tracking</div>
          </div>
          <div><a href="ingredients.php" class="btn-cancel" style="padding:10px 14px;display:inline-block;">Back to Ingredients</a></div>
        </div>

        <?php if ($feedback) echo $feedback; ?>

        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="form-grid">
            <div class="form-group"><label>Name</label><input name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"></div>

            <div class="form-group"><label>Category</label>
              <select name="category_id" required>
                <option value="">-- Select --</option>
                <?php while($c = $categories->fetch_assoc()): ?>
                  <option value="<?=$c['category_id']?>"><?=htmlspecialchars($c['category_name'])?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="form-group"><label>Unit</label><input name="unit" required value="<?php echo htmlspecialchars($_POST['unit'] ?? ''); ?>"></div>
            <div class="form-group"><label>Current Stock</label><input name="current_stock" type="number" step="0.0001" required value="<?php echo htmlspecialchars($_POST['current_stock'] ?? '0'); ?>"></div>
            <div class="form-group"><label>Cost per Unit (₱)</label><input name="cost_per_unit" type="number" step="0.01" required value="<?php echo htmlspecialchars($_POST['cost_per_unit'] ?? '0'); ?>"></div>
            <div class="form-group"><label>Supplier</label><input name="supplier" value="<?php echo htmlspecialchars($_POST['supplier'] ?? ''); ?>"></div>
            <div class="form-group"><label>Par Level</label><input name="par_level" type="number" step="0.0001" value="<?php echo htmlspecialchars($_POST['par_level'] ?? '0'); ?>"></div>
          </div>

          <div class="form-actions">
            <a href="ingredients.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">Save Ingredient</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</body>
</html>