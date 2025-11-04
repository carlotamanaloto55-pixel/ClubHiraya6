<?php
// menu_items.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $menu_id = isset($_POST['menu_item_id']) && $_POST['menu_item_id'] !== '' ? (int)$_POST['menu_item_id'] : 0;
    $name = $_POST['name'];
    $category = $_POST['category'] ?? '';
    $price = $_POST['price'] ?? 0;
    if ($menu_id) {
        $stmt = $conn->prepare("UPDATE menu_item SET name=?, category=?, price=? WHERE menu_item_id=?");
        $stmt->bind_param("ssdi", $name, $category, $price, $menu_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO menu_item (menu_item_id, name, category, price) VALUES (NULL, ?, ?, ?)");
        $stmt->bind_param("ssd", $name, $category, $price);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: menu_items.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM menu_item WHERE menu_item_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $stmt->close();
    header("Location: menu_items.php");
    exit;
}

$result = $conn->query("SELECT * FROM menu_item ORDER BY menu_item_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Menu Items</title>
  <link rel="stylesheet" href="inventory.css">
</head>
<body>
  <div class="topbar">
    <div class="brand"><h1>Menu Items</h1></div>
    <div class="navlinks">
      <a href="ingredients.php">Ingredients</a>
      <a href="ingredient_categories.php">Categories</a>
      <a href="inventory_transaction.php">Transactions</a>
    </div>
  </div>

    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h2>Menu Items</h2>
        <button class="btn" onclick="document.getElementById('formbox').style.display='block'">+ Add Menu Item</button>
      </div>

      <div class="table-header" style="margin-top:12px;">
        <span>ID</span><span>Name</span><span>Category</span><span>Price</span><span></span>
      </div>

      <?php while($row = $result->fetch_assoc()): ?>
        <div class="table-row" style="grid-template-columns:60px 1fr 200px 120px 160px;">
          <span><?=$row['menu_item_id']?></span>
          <span><?=htmlspecialchars($row['name'])?></span>
          <span><?=htmlspecialchars($row['category'])?></span>
          <span>₱<?=number_format($row['price'],2)?></span>
          <div class="action-buttons">
            <a class="btn small" href="menu_item_ingredients.php?id=<?=$row['menu_item_id']?>">View Ingredients</a>
            <a class="btn small" href="#" onclick="editMenu(<?=htmlspecialchars(json_encode($row))?>);return false">Edit</a>
            <a class="btn small secondary" href="menu_items.php?action=delete&id=<?=$row['menu_item_id']?>" onclick="return confirm('Delete menu item?')">Delete</a>
          </div>
        </div>
      <?php endwhile; ?>

      <div id="formbox" class="form-card" style="display:none;margin-top:12px;">
        <form method="post">
          <input type="hidden" name="menu_item_id" id="menu_item_id">
          <div class="form-grid">
            <div class="form-group">
              <label>Name</label>
              <input name="name" id="m_name" required>
            </div>
            <div class="form-group">
              <label>Category</label>
              <input name="category" id="m_category">
            </div>
            <div class="form-group">
              <label>Price (₱)</label>
              <input name="price" id="m_price" type="number" step="0.01" required>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn" type="submit">Save</button>
            <button class="btn secondary" type="button" onclick="document.getElementById('formbox').style.display='none'">Cancel</button>
          </div>
        </form>
      </div>

    </div>
  </div>

<script>
function editMenu(obj){
  document.getElementById('formbox').style.display='block';
  document.getElementById('menu_item_id').value = obj.menu_item_id;
  document.getElementById('m_name').value = obj.name;
  document.getElementById('m_category').value = obj.category;
  document.getElementById('m_price').value = obj.price;
}
</script>
</body>
</html>
