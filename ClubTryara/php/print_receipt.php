<?php
// php/print_receipt.php
// Printable receipt. When the user clicks "Close" or "Back to POS" this page will:
// - If opened as a popup by the POS (window.opener exists and exposes handleProceed), call opener._club_tryara.handleProceed() then close the popup.
// - Otherwise (receipt opened in same tab or opener not available) it will POST the same cart -> api/update_stock.php directly from this page, then navigate to index.php.
// This ensures that closing the receipt performs the same "proceed" action (update stock) as the Proceed button in the POS.

$cartJson   = $_POST['cart']   ?? null;
$totalsJson = $_POST['totals'] ?? null;

// If no POST data, show info page with a back link
if ($cartJson === null) {
    header('X-Robots-Tag: noindex, nofollow', true);
    ?>
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8" />
      <title>Receipt - Club Hiraya</title>
      <meta name="viewport" content="width=device-width,initial-scale=1" />
      <style>
        body { font-family: Arial, sans-serif; padding: 28px; color: #222; background:#f7f7fb; }
        .card { max-width:720px; margin:40px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,0.06); }
        h1 { margin:0 0 8px 0; font-size:20px; }
        p { color:#444; }
        a.button { display:inline-block; margin-top:16px; padding:10px 16px; background:#d51ecb; color:#fff; border-radius:8px; text-decoration:none; font-weight:700; }
      </style>
    </head>
    <body>
      <div class="card">
        
        <h1>No receipt data</h1>
        <p>This page was opened without receipt data. Open the Bill Out/Print Receipt function from the POS (index.php) to print a receipt.</p>
        <p><a class="button" href="index.php">Back to POS</a></p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$cart = json_decode($cartJson, true);
$totals = json_decode($totalsJson, true);
if (!is_array($cart)) $cart = [];
if (!is_array($totals)) $totals = [];
$date = date('Y-m-d H:i:s');

function fmt($n) {
    return '₱' . number_format((float)$n, 2);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Receipt - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;padding:20px;color:#111;background:#fff;}
    header{text-align:center;margin-bottom:10px}
    .items{width:100%;border-collapse:collapse;margin-top:10px}
    .items th,.items td{border-bottom:1px solid #eee;padding:10px;text-align:left}
    .right{text-align:right}
    .controls{margin-top:18px;display:flex;gap:10px;justify-content:center}
    .btn{padding:10px 16px;border-radius:8px;border:none;font-weight:700;color:#fff;text-decoration:none;cursor:pointer}
    .btn-print{background:#2b6cb0}
    .btn-back{background:#d51ecb}
    .btn-close{background:#6b7280}
    @media print{ .no-print{display:none} }
  </style>
</head>
<body>
  <header>
    <h2>Club Hiraya</h2>
    <div style="color:#666;font-size:13px;margin-top:4px">Receipt</div>
    <div style="color:#666;font-size:13px"><?= htmlspecialchars($date, ENT_QUOTES) ?></div>
  </header>

  <table class="items" aria-label="Receipt items">
    <thead>
      <tr><th style="width:56%;">Item</th><th style="width:12%;">Qty</th><th style="width:16%;" class="right">Price</th><th style="width:16%;" class="right">Total</th></tr>
    </thead>
    <tbody>
      <?php if (empty($cart)): ?>
        <tr><td colspan="4" style="padding:18px;text-align:center;color:#666;">(No items)</td></tr>
      <?php else: foreach ($cart as $it):
        $name = htmlspecialchars($it['name'] ?? 'Item', ENT_QUOTES);
        $qty = (int)($it['qty'] ?? 0);
        $price = (float)($it['price'] ?? 0.0);
        $line = $price * $qty;
      ?>
        <tr>
          <td><?= $name ?></td>
          <td><?= $qty ?></td>
          <td class="right"><?= fmt($price) ?></td>
          <td class="right"><?= fmt($line) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <table class="totals" style="margin-top:12px;width:100%">
    <tr><td style="color:#444">Subtotal</td><td style="text-align:right;font-weight:700"><?= fmt($totals['subtotal'] ?? 0) ?></td></tr>
    <tr><td style="color:#444">Service Charge</td><td style="text-align:right;font-weight:700"><?= fmt($totals['serviceCharge'] ?? 0) ?></td></tr>
    <tr><td style="color:#444">Tax</td><td style="text-align:right;font-weight:700"><?= fmt($totals['tax'] ?? 0) ?></td></tr>
    <tr><td style="color:#444">Discount</td><td style="text-align:right;font-weight:700"><?= fmt($totals['discountAmount'] ?? 0) ?></td></tr>
    <tr style="border-top:2px solid #eee;"><td><strong>Payable</strong></td><td style="text-align:right;font-weight:900"><strong><?= fmt($totals['payable'] ?? 0) ?></strong></td></tr>
  </table>

  <div class="controls no-print">
    <button id="printBtn" class="btn btn-print" type="button" onclick="window.print();">Print</button>

    <!-- Back to POS: will behave like "proceed" (update stock) then return -->
    <a id="backToPos" class="btn btn-back" href="index.php"
       onclick="event.preventDefault(); tryProceedAndReturn();">Back to POS</a>

    <!-- Close: same behavior -->
    <a id="closeBtn" class="btn btn-close" href="index.php"
       onclick="event.preventDefault(); tryProceedAndReturn();">Close</a>
  </div>

  <script>
    // Cart data available to JS for fallback API calls
    const __receipt_cart = <?= json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;

    // Helper: attempt to trigger the parent's proceed function, otherwise call API from here.
    function tryProceedAndReturn() {
      // If the POS opened this receipt (popup) and exposes the proceed function, use it.
      try {
        if (window.opener && window.opener._club_tryara && typeof window.opener._club_tryara.handleProceed === 'function') {
          // Call proceed in the opener which will update the DB.
          // We call it asynchronously and then close this popup.
          try {
            window.opener._club_tryara.handleProceed();
          } catch (e) {
            // If opener's function throws, fall back to local API POST below.
            console.error('opener.handleProceed() threw:', e);
            return fallbackPostThenRedirect();
          }
          // Close this popup window (if allowed)
          try { window.close(); } catch (e) {}
          return;
        }
      } catch (err) {
        console.error('Error contacting opener:', err);
      }

      // Fallback: POST the cart directly to the update_stock API from this page, then navigate back to POS.
      return fallbackPostThenRedirect();
    }

    // Posts cart -> ../api/update_stock.php (adjust path if your API is elsewhere) and then navigates back to index.php.
    function fallbackPostThenRedirect() {
      // Build payload: items: [{id, qty}, ...]
      const payload = { items: (Array.isArray(__receipt_cart) ? __receipt_cart.map(i => ({ id: i.id, qty: i.qty })) : []) };

      // If there are no items, just navigate back.
      if (!payload.items.length) {
        window.location.href = 'index.php';
        return;
      }

      fetch('../api/update_stock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      }).then(response => {
        if (!response.ok) throw new Error('Network response was not OK: ' + response.status);
        return response.json();
      }).then(body => {
        // Optionally you can inspect body.success
        // Redirect back to POS
        window.location.href = 'index.php';
      }).catch(err => {
        console.error('Failed to update stock from receipt page:', err);
        // Still redirect so user can continue; or optionally show an error and let them retry.
        window.location.href = 'index.php';
      });
    }

    // Auto-print if opened by the POS (window.opener present)
    window.addEventListener('load', function() {
      if (window.opener) {
        setTimeout(function() { window.print(); }, 400);
      }
    });
  </script>
</body>
</html>