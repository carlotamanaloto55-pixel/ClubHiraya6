<?php
// edit.php - themed Edit Item page with server-side validation + CSRF (PDO)
session_start();

$host = 'localhost';
$dbname = 'clubhiraya';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($item_id <= 0) {
    $_SESSION['error'] = "Invalid item ID";
    header("Location: inventory.php");
    exit();
}

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

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// fetch item
try {
    $stmt = $pdo->prepare("SELECT * FROM foods WHERE id = :id");
    $stmt->execute([':id' => $item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        $_SESSION['error'] = "Item not found";
        header("Location: inventory.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error fetching item: " . $e->getMessage());
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate csrf
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], (string)$postedToken)) {
        $errors[] = "Invalid form submission (CSRF token mismatch).";
    }

    $name = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $stock = $_POST['stock'] ?? '';

    // sanitize image filename: remove NULs and normalize whitespace to single spaces
    $image = str_replace("\0", '', $image);
    $image = preg_replace('/\s+/', ' ', $image); // collapse multiple whitespace to single space
    $image = trim($image);

    // validation
    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 255) $errors[] = "Name is required (2–255 chars).";
    if (!is_numeric($price) || floatval($price) <= 0 || floatval($price) > 1000000) $errors[] = "Price must be a number greater than 0 and less than 1,000,000.";

    // category must be one of the allowed options
    if (!in_array($category, $categories, true)) $errors[] = "Category is required and must be one of the predefined categories.";

    if ($image === '' || !preg_match('/^[^\/\\\\]+\\.(jpe?g|png|gif)$/i', $image)) $errors[] = "Image filename is required and must end with .jpg/.png/.gif.";
    if (!ctype_digit((string)$stock) || intval($stock) < 0 || intval($stock) > 1000000) $errors[] = "Stock must be a non-negative integer.";

    if (empty($errors)) {
        try {
            $sql = "UPDATE foods SET name = :name, price = :price, category = :category, stock = :stock, image = :image WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':price' => number_format((float)$price,2,'.',''),
                ':category' => $category,
                ':stock' => intval($stock),
                ':image' => $image,
                ':id' => $item_id
            ]);
            // refresh CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
            $_SESSION['success'] = "Item updated successfully!";
            header("Location: inventory.php");
            exit();
        } catch(PDOException $e) {
            $errors[] = "Error updating item: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Edit Item - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar" role="complementary" aria-label="Sidebar">
      <div class="sidebar-header">
          <img src="../../clubtryara/assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
      </div>
      <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
          <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/home.png" alt="Home"></span><span>Home</span></a>
          <a href="tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/table.png" alt="Tables"></span><span>Tables</span></a>
          <a href="inventory.php" class="sidebar-btn active"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
          <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Sales"></span><span>Sales Report</span></a>
          <a href="settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/setting.png" alt="Settings"></span><span>Settings</span></a>
      </nav>
      <div style="flex:1" aria-hidden="true"></div>
      <button class="sidebar-logout" type="button" aria-label="Logout">Logout</button>
  </aside>

  <main class="main-content" role="main" aria-label="Main content">
    <div class="inventory-container">
      <div class="form-card" style="max-width:900px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <div>
            <div style="font-size:20px;font-weight:800;">Edit Menu Item</div>
            <div style="color:#666;margin-top:6px;">Update product details used in the POS</div>
          </div>
          <div>
            <a href="inventory.php" class="btn-cancel" style="padding:10px 14px;display:inline-block;">Back to Inventory</a>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-error">
            <ul style="margin:0;padding-left:18px;">
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="name">Name</label>
              <input id="name" name="name" type="text" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($item['name']); ?>">
            </div>

            <div class="form-group">
              <label for="price">Price (₱)</label>
              <input id="price" name="price" type="number" step="0.01" min="0" required value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : htmlspecialchars($item['price']); ?>">
            </div>

            <div class="form-group">
              <label for="category">Category</label>
              <select id="category" name="category" required>
                <option value="" disabled <?php echo empty($_POST['category']) && empty($item['category']) ? 'selected' : ''; ?>>-- Select Category --</option>
                <?php
                  $selectedCategory = isset($_POST['category']) ? $_POST['category'] : $item['category'];
                  foreach ($categories as $c): ?>
                  <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($selectedCategory === $c) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="stock">Stock</label>
              <input id="stock" name="stock" type="number" min="0" required value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : htmlspecialchars($item['stock']); ?>">
            </div>

            <div class="form-group" style="grid-column:1 / -1;">
              <label for="image">Image filename</label>
              <input id="image" name="image" type="text" required value="<?php echo isset($_POST['image']) ? htmlspecialchars($_POST['image']) : htmlspecialchars($item['image']); ?>">
              <div class="help-small">Place images into assets/ and reference the filename here. Spaces are allowed (e.g. "fern salad.jpg").</div>
            </div>
          </div>

          <div class="form-actions">
            <a href="inventory.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</body>
</html>