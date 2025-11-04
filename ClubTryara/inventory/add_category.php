<?php
// add_category.php - add a new ingredient category (layout like create.php)
session_start();
require 'db_connect.php';

$errors = [];
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], (string)$posted)) {
        $errors[] = "Invalid form submission.";
    }

    $name = trim($_POST['category_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if ($name === '') $errors[] = "Category name is required.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO ingredient_category (category_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $desc);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Category added.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
            header("Location: ingredient_categories.php");
            exit;
        } else {
            $errors[] = "DB error: " . $conn->error;
        }
        $stmt->close();
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Add Category - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
    <div class="sidebar-header"><img src="../images/logo1.png" class="sidebar-header-img"></div>
    <nav class="sidebar-menu">
      <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/home.png"></span><span>Home</span></a>
      <a href="../php/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/table.png"></span><span>Tables</span></a>
      <a href="inventory.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/inventory.png"></span><span>Inventory</span></a>
    </nav>
    <div style="flex:1"></div>
    <button class="sidebar-logout">Logout</button>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div class="search-section"></div>
      <div class="navlinks" style="display:flex;gap:12px;">
        <a href="ingredients.php" class="btn-cancel" style="padding:8px 12px;">Ingredients</a>
        <a href="ingredient_categories.php" class="btn-cancel" style="padding:8px 12px;">Categories</a>
        <a href="inventory_transaction.php" class="btn-cancel" style="padding:8px 12px;">Transactions</a>
      </div>
    </div>

    <div class="inventory-container">
      <div class="form-card" style="max-width:700px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <div>
            <div style="font-size:20px;font-weight:800;">Add Category</div>
            <div style="color:#666;margin-top:6px;">Create a new ingredient category</div>
          </div>
          <div><a class="btn-cancel" href="ingredient_categories.php">Back</a></div>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-error"><ul style="margin:0;padding-left:18px;"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="form-grid">
            <div class="form-group">
              <label>Category name</label>
              <input name="category_name" required value="<?php echo htmlspecialchars($_POST['category_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label>Description</label>
              <input name="description" value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
            </div>
          </div>

          <div class="form-actions">
            <a class="btn-cancel" href="ingredient_categories.php">Cancel</a>
            <button class="btn-save" type="submit">Save Category</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</body>
</html>