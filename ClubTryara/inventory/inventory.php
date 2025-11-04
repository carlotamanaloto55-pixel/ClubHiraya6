<?php
session_start();

// Use existing db_connect.php in the same folder which should create $conn (mysqli)
$dbConnectPath = __DIR__ . '/db_connect.php';
if (!file_exists($dbConnectPath)) {
    die("Missing db_connect.php in php/ folder. Please add it or restore it.");
}
require_once $dbConnectPath;

// Ensure $conn is available (mysqli)
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("db_connect.php must define a valid \$conn (mysqli) connection.");
}

// Handle search safely
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch items with search (using prepared statements)
$items = [];
if ($search !== '') {
    $like = "%{$search}%";
    $stmt = $conn->prepare("SELECT * FROM foods WHERE name LIKE ? OR category LIKE ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $items = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        $stmt->close();
    } else {
        // fallback: query without prepared statement (shouldn't happen)
        $escaped = $conn->real_escape_string($search);
        $res = $conn->query("SELECT * FROM foods WHERE name LIKE '%$escaped%' OR category LIKE '%$escaped%' ORDER BY id ASC");
        if ($res) $items = $res->fetch_all(MYSQLI_ASSOC);
    }
} else {
    $res = $conn->query("SELECT * FROM foods ORDER BY id ASC");
    if ($res) $items = $res->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Inventory - Club Hiraya</title>
  <link rel="stylesheet" href="../css/inventory.css">
  <style>
    /* Highlight style for matched text */
    mark.highlight {
      background: #ffea8a;
      padding: 0 2px;
      border-radius: 3px;
      color: inherit;
    }
  </style>
</head>
<body<?php if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo ' class="dark-mode"'; ?>> <!-- Need this also -->

  <!-- Sidebar -->
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
      <div class="sidebar-header">
          <img src="../assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
      </div>

      <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
          <a href="../index.php" class="sidebar-btn" aria-current="page">
              <span class="sidebar-icon"><img src="../assets/logos/home.png" alt="Home icon"></span>
              <span>Home</span>
          </a>
          <a href="../tables/tables.php" class="sidebar-btn">
              <span class="sidebar-icon"><img src="../assets/logos/table.png" alt="Tables icon"></span>
              <span>Tables</span>
          </a>
          <a href="inventory.php" class="sidebar-btn active">
              <span class="sidebar-icon"><img src="../assets/logos/inventory.png" alt="Inventory icon"></span>
              <span>Inventory</span>
          </a>
          <a href="sales_report.php" class="sidebar-btn">
              <span class="sidebar-icon"><img src="../assets/logos/sales.png" alt="Sales report icon"></span>
              <span>Sales Report</span>
          </a>
          <a href="../settings/settings.php" class="sidebar-btn">
              <span class="sidebar-icon"><img src="../assets/logos/setting.png" alt="Settings icon"></span>
              <span>Settings</span>
          </a>
      </nav>

      <div style="flex:1" aria-hidden="true"></div>

      <button class="sidebar-logout" type="button" aria-label="Logout">
          <span>Logout</span>
      </button>
  </aside>

  <!-- Main Content -->
  <main class="main-content" role="main" aria-label="Main content">
      <!-- Top Bar -->
      <div class="topbar">
          <div class="search-section">
              <form id="searchForm" class="search-container" method="GET" action="">
                  <input id="searchInput" type="text" name="search" class="search-input" placeholder="Search products" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
              </form>
          </div>
          <div class="navlinks" style="display:flex;gap:14px;align-items:center;">
            <a href="ingredients.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Ingredients</a>
            <a href="ingredient_categories.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Categories</a>
            <a href="inventory_transaction.php" class="btn-cancel" style="padding:8px 12px;text-decoration:none;">Transactions</a>
          </div>
      </div>

      <!-- Inventory Container -->
      <div class="inventory-container" id="inventoryContainer">
          <?php if (isset($_SESSION['success'])): ?>
              <div class="alert alert-success">
                  <?php
                  echo htmlspecialchars($_SESSION['success']);
                  unset($_SESSION['success']);
                  ?>
              </div>
          <?php endif; ?>

          <?php if (isset($_SESSION['error'])): ?>
              <div class="alert alert-error">
                  <?php
                  echo htmlspecialchars($_SESSION['error']);
                  unset($_SESSION['error']);
                  ?>
              </div>
          <?php endif; ?>

          <!-- Table Header -->
          <div class="table-header">
              <div>ID</div>
              <div>Name</div>
              <div>Price</div>
              <div>Category</div>
              <div></div>
              <div class="col-image" aria-hidden="true">File Name</div>
              <div class="header-actions">
                <!-- Toggle button to show/hide Image column -->
                <button id="toggleImageBtn" class="btn-toggle" type="button" aria-pressed="false">Show File Name</button>
              </div>

              <a href="create.php" class="add-btn" title="Add New Item">
                  Add New
              </a>
          </div>

          <!-- Table Rows -->
          <?php if (empty($items)): ?>
              <div class="empty-state" id="noResultsServer">
                  <?php if ($search !== ''): ?>
                      No items found matching "<?php echo htmlspecialchars($search, ENT_QUOTES); ?>"
                  <?php else: ?>
                      No items in inventory. Click the + button to add items.
                  <?php endif; ?>
              </div>
          <?php else: ?>
              <?php foreach ($items as $item): ?>
                  <div class="table-row"
                       data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>"
                       data-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>">
                      <div><?php echo htmlspecialchars($item['id']); ?></div>
                      <div><?php echo htmlspecialchars($item['name']); ?></div>
                      <div>₱<?php echo number_format($item['price'], 2); ?></div>
                      <div><?php echo htmlspecialchars($item['category']); ?></div>
                      <div></div>

                      <!-- Image filename cell (hidden by default) -->
                      <div class="col-image"><?php echo htmlspecialchars($item['image']); ?></div>

                      <div class="action-buttons">
                          <!-- Updated: View Ingredients button linking to menu_item_ingredients.php with the item id -->
                          <a class="btn-edit" href="menu_item_ingredients.php?id=<?php echo urlencode($item['id']); ?>" title="View Ingredients">View Ingredients</a>

                          <a href="edit.php?id=<?php echo urlencode($item['id']); ?>" class="btn-edit">Edit</a>
                          <button onclick="confirmDelete(<?php echo htmlspecialchars($item['id']); ?>)" class="btn-delete">Delete</button>
                      </div>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>

          <!-- Dynamic no-results message for client-side filtering (hidden by default) -->
          <div id="noResults" class="empty-state" style="display:none;"></div>
      </div>
  </main>

  <script>
    // Live search, highlighting and Image column toggle
    (function() {
      const toggleBtn = document.getElementById('toggleImageBtn');
      const container = document.getElementById('inventoryContainer');
      const searchForm = document.getElementById('searchForm');
      const searchInput = document.getElementById('searchInput');
      const noResults = document.getElementById('noResults');
      const serverEmpty = document.getElementById('noResultsServer');

      function updateButton(isShown) {
        toggleBtn.textContent = isShown ? 'Hide File Name' : 'Show File Name';
        toggleBtn.setAttribute('aria-pressed', isShown ? 'true' : 'false');
      }

      // initialize image column visibility from localStorage
      const saved = localStorage.getItem('inventory_show_images');
      if (saved === '1') {
        container.classList.add('show-images');
        updateButton(true);
      } else {
        updateButton(false);
      }

      toggleBtn.addEventListener('click', function() {
        const isShown = container.classList.toggle('show-images');
        updateButton(isShown);
        localStorage.setItem('inventory_show_images', isShown ? '1' : '0');
      });

      window.confirmDelete = function(id) {
        if (confirm('Are you sure you want to delete this item?')) {
          window.location.href = 'delete.php?id=' + encodeURIComponent(id);
        }
      };

      // Prevent form submit to avoid page reload — we do client-side filtering instead.
      if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
          e.preventDefault();
        });
      }

      // Utility: escape HTML for safe insertion
      function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"'`=\/]/g, function(s) {
          return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '/': '&#x2F;',
            '`': '&#x60;',
            '=': '&#x3D;'
          })[s];
        });
      }

      // Build a highlighted HTML string by wrapping matches in <mark class="highlight">
      function highlightText(text, query) {
        if (!query) return escapeHtml(text);
        const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // escape regex chars
        const re = new RegExp(escapedQuery, 'gi');
        // Replace using a function so we can escape the matched text as well
        return escapeHtml(text).replace(re, match => '<mark class="highlight">' + escapeHtml(match) + '</mark>');
      }

      // Client-side filtering of the rendered rows with highlighting
      function filterRows(q) {
        q = (q || '').trim();
        const qLower = q.toLowerCase();
        const rows = Array.from(document.querySelectorAll('.table-row'));
        let visibleCount = 0;

        rows.forEach(row => {
          // raw values stored in data attributes
          const rawName = row.dataset.name || '';
          const rawCategory = row.dataset.category || '';
          const nameLower = rawName.toLowerCase();
          const categoryLower = rawCategory.toLowerCase();

          const matches = q === '' || nameLower.includes(qLower) || categoryLower.includes(qLower);
          row.style.display = matches ? '' : 'none';

          // update cells with highlighted HTML when visible, otherwise leave original text
          const nameCell = row.children[1];
          const categoryCell = row.children[3];
          if (nameCell) nameCell.innerHTML = highlightText(rawName, q);
          if (categoryCell) categoryCell.innerHTML = highlightText(rawCategory, q);

          if (matches) visibleCount++;
        });

        // If server-side returned no rows at all, keep the server message
        const serverHadNoRows = !!serverEmpty && serverEmpty.style.display !== 'none';

        if (visibleCount === 0) {
          // show the client-side no-results message (only if there are rows on the page or server had rows)
          const msg = q ? `No items found matching "${escapeHtml(q)}"` : (serverHadNoRows ? serverEmpty.textContent : 'No items in inventory.');
          noResults.textContent = msg;
          noResults.style.display = 'block';
        } else {
          noResults.style.display = 'none';
          // hide server message if present (we have results)
          if (serverEmpty) serverEmpty.style.display = 'none';
        }
      }

      // Debounce typing
      let to;
      if (searchInput) {
        // initialize filter from any existing value (e.g., when page was loaded with ?search=)
        filterRows(searchInput.value || '');

        searchInput.addEventListener('input', function() {
          clearTimeout(to);
          to = setTimeout(function() {
            filterRows(searchInput.value);
          }, 180);
        });

        // Optional: pressing ESC clears the search
        searchInput.addEventListener('keydown', function(e) {
          if (e.key === 'Escape') {
            searchInput.value = '';
            filterRows('');
          }
        });
      }

    })();
  </script>
</body>
</html>