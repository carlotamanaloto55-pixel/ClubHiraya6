<?php
// inventory_transaction.php - UI aligned to inventory.php classes/layout and updates ingredient stock
require 'db_connect.php';
session_start();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ingredient_id'])) {
    $ingredient_id = (int)$_POST['ingredient_id'];
    $change_amount = floatval($_POST['change_amount']);
    $reason = trim($_POST['reason'] ?? '');
    $transaction_date = $_POST['transaction_date'] ?? null;

    if ($ingredient_id <= 0) $errors[] = "Select an ingredient.";
    if ($change_amount == 0) $errors[] = "Change amount cannot be zero.";
    if ($reason === '') $errors[] = "Reason is required.";

    if (empty($errors)) {
        $s = $conn->prepare("SELECT current_stock, unit FROM ingredient WHERE ingredient_id = ?");
        $s->bind_param("i", $ingredient_id);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$r) {
            $errors[] = "Ingredient not found.";
        } else {
            $new_stock = floatval($r['current_stock']) + $change_amount;
            $u = $conn->prepare("UPDATE ingredient SET current_stock = ? WHERE ingredient_id = ?");
            $u->bind_param("di", $new_stock, $ingredient_id);
            $ok1 = $u->execute();
            $u->close();

            $t = $conn->prepare("INSERT INTO inventory_transaction (ingredient_id, change_amount, unit, reason, transaction_date) VALUES (?,?,?,?,?)");
            $unit = $r['unit'];
            $ts = $transaction_date ? $transaction_date : date('Y-m-d H:i:s');
            $t->bind_param("idsss", $ingredient_id, $change_amount, $unit, $reason, $ts);
            $ok2 = $t->execute();
            $t->close();

            if ($ok1 && $ok2) {
                $_SESSION['success'] = "Transaction saved; stock updated.";
                header("Location: inventory_transaction.php");
                exit;
            } else {
                $errors[] = "Database error.";
            }
        }
    }
}

// fetch transactions & ingredients
$txns = $conn->query("SELECT t.transaction_id, i.name AS ingredient, t.change_amount, t.unit, t.reason, t.transaction_date FROM inventory_transaction t JOIN ingredient i ON t.ingredient_id = i.ingredient_id ORDER BY t.transaction_date DESC");
$ings = $conn->query("SELECT ingredient_id, name FROM ingredient ORDER BY name ASC");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Inventory Transactions - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
  <aside class="sidebar">
    <div class="sidebar-header"><img src="../images/logo1.png" class="sidebar-header-img"></div>
    <nav class="sidebar-menu">
      <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/home.png"></span><span>Home</span></a>
      <a href="../php/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/table.png"></span><span>Tables</span></a>
      <a href="inventory.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/inventory.png"></span><span>Inventory</span></a>
      <a href="sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/sales.png"></span><span>Sales</span></a>
      <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../images/setting.png"></span><span>Settings</span></a>
    </nav>
    <div style="flex:1"></div>
    <button class="sidebar-logout">Logout</button>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div class="search-section"><input class="search-input" placeholder="Filter transactions..." oninput="filterTxn(this.value)"></div>
      <div class="navlinks" style="display:flex;gap:14px;align-items:center;">
        <a href="ingredients.php" class="btn-cancel">Ingredients</a>
        <a href="ingredient_categories.php" class="btn-cancel">Categories</a>
        <a href="menu_items.php" class="btn-cancel">Menu</a>
      </div>
    </div>

    <div class="inventory-container">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><ul style="margin:0;padding-left:18px;"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
      <?php endif; ?>
      <?php if (isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>

      <div class="table-header">
        <div style="grid-column:1/-1; font-size:18px; font-weight:800;">Record Transaction</div>
      </div>

      <div class="form-card" style="margin-top:12px;">
        <form method="post" class="form-grid">
          <div class="form-group">
            <label>Ingredient</label>
            <select name="ingredient_id" required>
              <option value="">-- choose ingredient --</option>
              <?php while($i = $ings->fetch_assoc()): ?>
                <option value="<?=$i['ingredient_id']?>"><?=htmlspecialchars($i['name'])?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Change Amount</label>
            <input name="change_amount" type="number" step="0.0001" required>
          </div>
          <div class="form-group">
            <label>Reason</label>
            <input name="reason" required>
          </div>
          <div class="form-group">
            <label>Date (optional)</label>
            <input name="transaction_date" type="datetime-local">
          </div>
          <div class="form-actions" style="grid-column:1/-1;">
            <a class="btn-cancel" href="inventory.php">Back to Inventory</a>
            <button class="btn-save" type="submit">Record Transaction</button>
          </div>
        </form>
      </div>

      <div class="table-header" style="margin-top:18px;">
        <div>ID</div><div>Ingredient</div><div>Change</div><div>Unit</div><div>Reason</div><div>Date</div><div class="header-actions"></div>
      </div>

      <?php while($t = $txns->fetch_assoc()): ?>
        <div class="table-row" style="grid-template-columns:60px 1fr 120px 120px 1fr 200px;">
          <div><?php echo $t['transaction_id']; ?></div>
          <div><?php echo htmlspecialchars($t['ingredient']); ?></div>
          <div style="color:<?php echo $t['change_amount'] < 0 ? '#c53030' : '#0b842e'; ?>"><?php echo number_format($t['change_amount'],4); ?></div>
          <div><?php echo htmlspecialchars($t['unit']); ?></div>
          <div><?php echo htmlspecialchars($t['reason']); ?></div>
          <div><?php echo htmlspecialchars($t['transaction_date']); ?></div>
          <div class="action-buttons"></div>
        </div>
      <?php endwhile; ?>

    </div>
  </main>

<script>
function filterTxn(q) {
  q=(q||'').toLowerCase();
  document.querySelectorAll('.table-row').forEach(function(r){
    r.style.display = r.textContent.toLowerCase().indexOf(q)!==-1 ? '' : 'none';
  });
}
</script>
</body>
</html>