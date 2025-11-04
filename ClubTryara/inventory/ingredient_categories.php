<?php
// ingredient_categories.php - layout aligned with inventory.php, Add Category links to add_category.php
require 'db_connect.php';
session_start();

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : 0;
    $name = trim($_POST['category_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($cid) {
        $u = $conn->prepare("UPDATE ingredient_category SET category_name=?, description=? WHERE category_id=?");
        $u->bind_param("ssi", $name, $desc, $cid);
        $u->execute();
        $u->close();
    } else {
        $i = $conn->prepare("INSERT INTO ingredient_category (category_name, description) VALUES (?,?)");
        $i->bind_param("ss", $name, $desc);
        $i->execute();
        $i->close();
    }
    $_SESSION['success'] = "Category saved.";
    header("Location: ingredient_categories.php");
    exit;
}

if ($action === 'delete' && $id) {
    $d = $conn->prepare("DELETE FROM ingredient_category WHERE category_id = ?");
    $d->bind_param("i", $id);
    $d->execute();
    $d->close();
    $_SESSION['success'] = "Category deleted.";
    header("Location: ingredient_categories.php");
    exit;
}

$categories = $conn->query("SELECT * FROM ingredient_category ORDER BY category_name");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Ingredient Categories - Club Hiraya</title>
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
      <div class="search-section"><input class="search-input" placeholder="Search categories..." oninput="filterCat(this.value)"></div>
      <div class="navlinks" style="display:flex;gap:12px;">
        <a href="ingredients.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Ingredients</a>
        <a href="inventory_transaction.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Transactions</a>
        <a href="inventory.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Inventory</a>
      </div>
    </div>

    <div class="inventory-container">
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      <div class="form-card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:20px;font-weight:800;">Ingredient Categories</div>
            <div class="small-muted">Organize ingredients into categories</div>
          </div>
          <div>
            <!-- fixed: link to dedicated add_category.php page -->
            <a class="add-btn" href="add_category.php">+ Add Category</a>
          </div>
        </div>

        <div style="margin-top:12px;">
          <div class="table-header">
            <div>Category</div>
            <div class="header-actions"></div>
          </div>

          <?php while($c = $categories->fetch_assoc()): ?>
            <div class="table-row" style="grid-template-columns:1fr 220px;">
              <div>
                <strong><?=htmlspecialchars($c['category_name'])?></strong>
                <div class="small-muted"><?=htmlspecialchars($c['description'])?></div>
              </div>
              <div class="action-buttons">
                <!-- Edit goes to edit_category.php which edits category and shows ingredients in it -->
                <a class="btn-edit" href="edit_category.php?id=<?=$c['category_id']?>">Edit</a>
                <a class="btn-delete" href="ingredient_categories.php?action=delete&id=<?=$c['category_id']?>" onclick="return confirm('Delete category?')">Delete</a>
              </div>
            </div>
          <?php endwhile; ?>
        </div>

      </div>
    </div>
  </main>

<script>
function filterCat(q){
  q=(q||'').toLowerCase();
  document.querySelectorAll('.table-row').forEach(function(r){
    r.style.display = r.textContent.toLowerCase().indexOf(q)!==-1 ? '' : 'none';
  });
}
</script>
</body>
</html>