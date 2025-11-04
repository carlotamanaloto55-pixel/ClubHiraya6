<?php
// edit_ingredient.php - copy of edit.php behavior but for ingredient (uses PDO like edit.php)
session_start();

// PDO connection (match edit.php approach)
$host = 'localhost';
$dbname = 'clubhiraya';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$ingredient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($ingredient_id <= 0) {
    $_SESSION['error'] = "Invalid ingredient ID";
    header("Location: ingredients.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// fetch ingredient
try {
    $stmt = $pdo->prepare("SELECT * FROM ingredient WHERE ingredient_id = :id");
    $stmt->execute([':id' => $ingredient_id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ingredient) {
        $_SESSION['error'] = "Ingredient not found";
        header("Location: ingredients.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error fetching ingredient: " . $e->getMessage());
}

$errors = [];
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

    // validation similar to edit.php rules (adjusted for ingredient)
    if ($name === '' || mb_strlen($name) < 1 || mb_strlen($name) > 255) $errors[] = "Name is required (1–255 chars).";
    if ($category <= 0) $errors[] = "Category is required.";
    if (!is_numeric($stock)) $errors[] = "Current stock must be a number.";
    if (!is_numeric($cost)) $errors[] = "Cost per unit must be a number.";

    if (empty($errors)) {
        try {
            $sql = "UPDATE ingredient SET category_id = :category_id, name = :name, unit = :unit, par_level = :par_level, current_stock = :current_stock, cost_per_unit = :cost_per_unit, supplier = :supplier WHERE ingredient_id = :id";
            $upd = $pdo->prepare($sql);
            $upd->execute([
                ':category_id' => $category,
                ':name' => $name,
                ':unit' => $unit,
                ':par_level' => floatval($par),
                ':current_stock' => floatval($stock),
                ':cost_per_unit' => floatval($cost),
                ':supplier' => $supplier,
                ':id' => $ingredient_id
            ]);
            $_SESSION['success'] = "Ingredient updated successfully!";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
            header("Location: ingredients.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Error updating ingredient: " . $e->getMessage();
        }
    }
}

// categories list
$cats = $pdo->query("SELECT * FROM ingredient_category ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Edit Ingredient - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <!-- Sidebar identical to inventory.php -->
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
      <div class="sidebar-header"><img src="../assets/logos/logo1.png" class="sidebar-header-img" alt="logo"></div>
      <nav class="sidebar-menu">
        <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/home.png" alt="Home"></span><span>Home</span></a>
        <a href="../php/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/table.png" alt="Tables"></span><span>Tables</span></a>
        <a href="inventory.php" class="sidebar-btn active"><span class="sidebar-icon"><img src="../assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
        <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/sales.png" alt="Sales"></span><span>Sales Report</span></a>
        <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/setting.png" alt="Settings"></span><span>Settings</span></a>
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
            <div style="font-size:20px;font-weight:800;">Edit Ingredient</div>
            <div style="color:#666;margin-top:6px;">Update ingredient details used across the system</div>
          </div>
          <div>
            <a href="ingredients.php" class="btn-cancel" style="padding:10px 14px;display:inline-block;">Back to Ingredients</a>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-error"><ul style="margin:0;padding-left:18px;"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="form-grid">
            <div class="form-group"><label>Name</label><input name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $ingredient['name']); ?>"></div>
            <div class="form-group"><label>Category</label>
              <select name="category_id" required>
                <option value="" disabled>-- Select Category --</option>
                <?php foreach ($cats as $c): $sel = (isset($_POST['category_id']) ? $_POST['category_id'] : $ingredient['category_id']) == $c['category_id']; ?>
                  <option value="<?php echo $c['category_id']; ?>" <?php echo $sel ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['category_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group"><label>Unit</label><input name="unit" required value="<?php echo htmlspecialchars($_POST['unit'] ?? $ingredient['unit']); ?>"></div>
            <div class="form-group"><label>Current Stock</label><input name="current_stock" type="number" step="0.0001" required value="<?php echo htmlspecialchars($_POST['current_stock'] ?? $ingredient['current_stock']); ?>"></div>
            <div class="form-group"><label>Cost per Unit (₱)</label><input name="cost_per_unit" type="number" step="0.01" required value="<?php echo htmlspecialchars($_POST['cost_per_unit'] ?? $ingredient['cost_per_unit']); ?>"></div>
            <div class="form-group"><label>Supplier</label><input name="supplier" value="<?php echo htmlspecialchars($_POST['supplier'] ?? $ingredient['supplier']); ?>"></div>
            <div class="form-group"><label>Par Level</label><input name="par_level" type="number" step="0.0001" value="<?php echo htmlspecialchars($_POST['par_level'] ?? $ingredient['par_level']); ?>"></div>
          </div>

          <div class="form-actions">
            <a class="btn-cancel" href="ingredients.php">Cancel</a>
            <button class="btn-save" type="submit">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</body>
</html>