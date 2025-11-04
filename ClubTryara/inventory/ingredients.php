<?php
session_start();

// Use existing db_connect.php which should create $conn (mysqli)
$dbConnectPath = __DIR__ . '/db_connect.php';
if (!file_exists($dbConnectPath)) {
    die("Missing db_connect.php. Please add it or restore it.");
}
require_once $dbConnectPath;

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("db_connect.php must define a valid \$conn (mysqli) connection.");
}

// Handle delete via GET safely
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($action === 'delete' && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM ingredient WHERE ingredient_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Ingredient deleted.";
    } else {
        $_SESSION['error'] = "Unable to delete ingredient.";
    }
    header("Location: ingredients.php");
    exit;
}

// Search input
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch items with search (using prepared statements)
$items = [];
if ($search !== '') {
    $like = "%{$search}%";
    $stmt = $conn->prepare("SELECT i.*, ic.category_name FROM ingredient i LEFT JOIN ingredient_category ic ON i.category_id = ic.category_id WHERE i.name LIKE ? OR ic.category_name LIKE ? ORDER BY i.ingredient_id ASC");
    if ($stmt) {
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $items = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $escaped = $conn->real_escape_string($search);
        $res = $conn->query("SELECT i.*, ic.category_name FROM ingredient i LEFT JOIN ingredient_category ic ON i.category_id = ic.category_id WHERE i.name LIKE '%$escaped%' OR ic.category_name LIKE '%$escaped%' ORDER BY i.ingredient_id ASC");
        if ($res) $items = $res->fetch_all(MYSQLI_ASSOC);
    }
} else {
    $res = $conn->query("SELECT i.*, ic.category_name FROM ingredient i LEFT JOIN ingredient_category ic ON i.category_id = ic.category_id ORDER BY i.ingredient_id ASC");
    if ($res) $items = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Ingredients - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
  <style>
    mark.highlight { background:#ffea8a; padding:0 2px; border-radius:3px; color:inherit; }
  </style>
</head>
<body<?php if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo ' class="dark-mode"'; ?>>

  <!-- Sidebar (same markup/classes as inventory.php) -->
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
      <div class="sidebar-header">
          <img src="../assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
      </div>
      <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
          <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/home.png" alt="Home"></span><span>Home</span></a>
          <a href="../php/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/table.png" alt="Tables"></span><span>Tables</span></a>
          <a href="inventory.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
          <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/sales.png" alt="Sales"></span><span>Sales Report</span></a>
          <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/setting.png" alt="Settings"></span><span>Settings</span></a>
      </nav>
      <div style="flex:1" aria-hidden="true"></div>
      <button class="sidebar-logout" type="button" aria-label="Logout">Logout</button>
  </aside>

  <!-- Main -->
  <main class="main-content" role="main" aria-label="Main content">
    <!-- Topbar matches inventory.php -->
    <div class="topbar">
      <div class="search-section">
        <form id="searchForm" method="GET" action="">
          <input id="searchInput" name="search" class="search-input" placeholder="Search ingredients" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
        </form>
      </div>
      <div class="navlinks" style="display:flex;gap:14px;align-items:center;">
        <a href="ingredient_categories.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Categories</a>
        <a href="inventory_transaction.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Transactions</a>
        <a href="inventory.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Inventory</a>
      </div>
    </div>

    <div class="inventory-container" id="ingredientsContainer">
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
      <?php endif; ?>

      <div class="table-header">
        <div>ID</div>
        <div>Name</div>
        <div>Category</div>
        <div>Stock</div>
        <div>Unit</div>
        <div class="col-image">Cost</div>
        <div class="header-actions"></div>
        <a href="add_ingredient.php" class="add-btn" title="Add Ingredient">Add New</a>
      </div>

      <?php if (empty($items)): ?>
        <div class="empty-state" id="noResultsServer"><?php echo $search !== '' ? 'No ingredients found.' : 'No ingredients yet.'; ?></div>
      <?php else: ?>
        <?php foreach ($items as $row): ?>
          <div class="table-row"
               data-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>"
               data-category="<?php echo htmlspecialchars($row['category_name'] ?? '', ENT_QUOTES); ?>">
            <div><?php echo htmlspecialchars($row['ingredient_id']); ?></div>
            <div><?php echo htmlspecialchars($row['name']); ?></div>
            <div><?php echo htmlspecialchars($row['category_name']); ?></div>
            <div><?php echo number_format($row['current_stock'], 4); ?></div>
            <div><?php echo htmlspecialchars($row['unit']); ?></div>
            <div class="col-image">₱<?php echo number_format($row['cost_per_unit'] ?? 0, 2); ?></div>
            <div class="action-buttons">
              <a class="btn-edit" href="view_edit_ingredients.php?id=<?php echo urlencode($row['ingredient_id']); ?>">View</a>
              <a class="btn-edit" href="edit_ingredient.php?id=<?php echo urlencode($row['ingredient_id']); ?>">Edit</a>
              <a class="btn-delete" href="ingredients.php?action=delete&id=<?php echo urlencode($row['ingredient_id']); ?>" onclick="return confirm('Delete this ingredient?')">Delete</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div id="noResults" class="empty-state" style="display:none;"></div>
    </div>
  </main>

  <script>
  (function(){
    const container = document.getElementById('ingredientsContainer');
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    const noResults = document.getElementById('noResults');
    const serverEmpty = document.getElementById('noResultsServer');

    // Client-side filter & highlight - same logic as inventory.php
    function escapeHtml(str){ if(!str) return ''; return str.replace(/[&<>"'`=\/]/g, s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;",'/':'&#x2F;','`':'&#x60;','=':'&#x3D;'})[s]); }
    function highlightText(text, query){
      if(!query) return escapeHtml(text);
      const esc = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      const re = new RegExp(esc,'gi');
      return escapeHtml(text).replace(re, m => '<mark class="highlight">'+escapeHtml(m)+'</mark>');
    }

    function filterRows(q){
      q = (q||'').trim();
      const qLower = q.toLowerCase();
      const rows = Array.from(document.querySelectorAll('.table-row'));
      let visibleCount = 0;
      rows.forEach(row => {
        const rawName = row.dataset.name || '';
        const rawCategory = row.dataset.category || '';
        const matches = q === '' || rawName.toLowerCase().includes(qLower) || rawCategory.toLowerCase().includes(qLower);
        row.style.display = matches ? '' : 'none';
        const nameCell = row.children[1];
        const categoryCell = row.children[2];
        if (nameCell) nameCell.innerHTML = highlightText(rawName, q);
        if (categoryCell) categoryCell.innerHTML = highlightText(rawCategory, q);
        if (matches) visibleCount++;
      });

      const serverHadNone = !!serverEmpty && serverEmpty.style.display !== 'none';
      if (visibleCount === 0) {
        const msg = q ? `No ingredients found matching "${escapeHtml(q)}"` : (serverHadNone ? serverEmpty.textContent : 'No ingredients yet.');
        noResults.textContent = msg; noResults.style.display = 'block';
      } else {
        noResults.style.display = 'none';
        if (serverEmpty) serverEmpty.style.display = 'none';
      }
    }

    if (searchForm) searchForm.addEventListener('submit', e => e.preventDefault());
    if (searchInput) {
      filterRows(searchInput.value || '');
      let to;
      searchInput.addEventListener('input', () => {
        clearTimeout(to); to = setTimeout(()=> filterRows(searchInput.value), 180);
      });
      searchInput.addEventListener('keydown', e => { if (e.key === 'Escape') { searchInput.value=''; filterRows(''); } });
    }
  })();
  </script>
</body>
</html>