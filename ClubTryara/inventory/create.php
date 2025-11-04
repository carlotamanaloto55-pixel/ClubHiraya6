<?php
// create.php - themed Add New Item page with server-side validation + CSRF
session_start();
include "db_connect.php"; // expects $conn (mysqli)

$feedback = '';
$errors = [];

// allowed categories (dropdown)
$categories = [
    'Main Course',
    'Appetizer',
    'Soup',
    'Salad',
    'Seafoods',
    'Pasta & Noodles',
    'Sides',
    'Pizza',
    'Drinks',
    'Alcohol',
    'Cocktails'
];

// CSRF token: generate if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate CSRF
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], (string)$postedToken)) {
        $errors[] = "Invalid form submission (CSRF token mismatch).";
    }

    $name = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $stock = $_POST['stock'] ?? '';
    $image = trim($_POST['image'] ?? '');

    // --- sanitize image filename: same rules as edit.php ---
    // remove NUL bytes, collapse multiple whitespace to a single space and trim
    $image = str_replace("\0", '', $image);
    $image = preg_replace('/\s+/', ' ', $image);
    $image = trim($image);
    // -------------------------------------------------------

    // server-side validation
    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 255) {
        $errors[] = "Name is required (2–255 characters).";
    }

    if (!is_numeric($price) || floatval($price) <= 0 || floatval($price) > 1000000) {
        $errors[] = "Price must be a number greater than 0 and less than 1,000,000.";
    } else {
        $price = number_format((float)$price, 2, '.', '');
    }

    // category must be one of the allowed options
    if (!in_array($category, $categories, true)) {
        $errors[] = "Category is required and must be one of the predefined categories.";
    }

    if (!ctype_digit((string)$stock) || intval($stock) < 0 || intval($stock) > 1000000) {
        $errors[] = "Stock must be a non-negative integer.";
    } else {
        $stock = intval($stock);
    }

    // Validate image filename (allow spaces, jpg/png/gif), but disallow path separators
    if ($image === '' || !preg_match('/^[^\/\\\\]+\.(jpe?g|png|gif)$/i', $image)) {
        $errors[] = "Image filename is required and must end with .jpg/.jpeg/.png/.gif (no path separators).";
    }

    if (empty($errors)) {
        // Prevent duplicate name using prepared statements
        $check_sql = "SELECT id FROM foods WHERE name = ?";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Item '" . htmlspecialchars($name) . "' already exists.";
        } else {
            $sql = "INSERT INTO foods (name, price, category, stock, image) VALUES (?, ?, ?, ?, ?)";
            $insert = mysqli_prepare($conn, $sql);
            // types: s = string, d = double, s = string, i = int, s = string
            mysqli_stmt_bind_param($insert, 'sdsis', $name, $price, $category, $stock, $image);
            if (mysqli_stmt_execute($insert)) {
                $_SESSION['success'] = "New item added successfully!";
                // regenerate token after successful action
                $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
                header("Location: inventory.php");
                exit();
            } else {
                $errors[] = "Database error: " . mysqli_error($conn);
            }
            if ($insert) mysqli_stmt_close($insert);
        }
        if ($stmt) mysqli_stmt_close($stmt);
    }

    if (!empty($errors)) {
        $feedback = "<div class='alert alert-error'><ul style='margin:0;padding-left:18px;'>";
        foreach ($errors as $e) $feedback .= "<li>" . htmlspecialchars($e) . "</li>";
        $feedback .= "</ul></div>";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Add Item - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <!-- Sidebar (same as inventory) -->
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
      <div class="sidebar-header">
          <img src="../assets/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
      </div>
      <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
          <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/home.png" alt="Home"></span><span>Home</span></a>
          <a href="tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/table.png" alt="Tables"></span><span>Tables</span></a>
          <a href="inventory.php" class="sidebar-btn active"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
          <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Sales"></span><span>Sales Report</span></a>
          <a href="settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/setting.png" alt="Settings"></span><span>Settings</span></a>
      </nav>
      <div style="flex:1" aria-hidden="true"></div>
      <button class="sidebar-logout" type="button" aria-label="Logout">Logout</button>
  </aside>

  <main class="main-content" role="main" aria-label="Main content">
    <div class="inventory-container">
      <div class="form-card" style="max-width:900px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <div>
            <div style="font-size:20px;font-weight:800;">Add New Menu Item</div>
            <div style="color:#666;margin-top:6px;">Create a new food or drink entry for the POS system</div>
          </div>
          <div>
            <a href="inventory.php" class="btn-cancel" style="padding:10px 14px;display:inline-block;">Back to Inventory</a>
          </div>
        </div>

        <?php if ($feedback) echo $feedback; ?>

        <form method="POST" action="" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="name">Name</label>
              <input id="name" name="name" type="text" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" placeholder="e.g. Lechon Baka">
            </div>

            <div class="form-group">
              <label for="price">Price (₱)</label>
              <input id="price" name="price" type="number" step="0.01" min="0" required value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" placeholder="e.g. 420.00">
            </div>

            <div class="form-group">
              <label for="category">Category</label>
              <select id="category" name="category" required>
                <option value="" disabled <?php echo empty($_POST['category']) ? 'selected' : ''; ?>>-- Select Category --</option>
                <?php
                  $selectedCategory = isset($_POST['category']) ? $_POST['category'] : '';
                  foreach ($categories as $c): ?>
                  <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($selectedCategory === $c) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="stock">Stock</label>
              <input id="stock" name="stock" type="number" min="0" required value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0'; ?>">
            </div>

            <div class="form-group" style="grid-column:1 / -1;">
              <label for="image">Image filename</label>
              <input id="image" name="image" type="text" required placeholder='file.jpg (placed in assets/ folder) ' value="<?php echo isset($_POST['image']) ? htmlspecialchars($_POST['image']) : ''; ?>">
              <div class="help-small">Place images into assets/ and reference the filename here. Spaces are allowed (e.g. "fern salad.jpg").</div>
            </div>
          </div>

          <div class="form-actions">
            <a href="inventory.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">Save Item</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</body>
</html>