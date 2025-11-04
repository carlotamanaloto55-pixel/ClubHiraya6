// Robust Bill Out and Proceed helpers.
// Include this <script src="js/receipt_and_proceed.js"></script> after your main app.js
(function () {
  // Use the global 'order' variable created by your app.js.
  // If your app stores the cart in a different variable, update getCart() accordingly.
  function getCart() {
    if (typeof order !== 'undefined' && Array.isArray(order)) return order;
    // fallback: attempt to read from a global window.cart
    if (typeof window.cart !== 'undefined' && Array.isArray(window.cart)) return window.cart;
    return [];
  }

  function getTotals() {
    // If your app exposes a computeNumbers() helper that returns totals, use it.
    if (typeof computeNumbers === 'function') {
      try { return computeNumbers(); } catch (e) { /* ignore */ }
    }
    // fallback empty totals
    return { subtotal: 0, serviceCharge: 0, tax: 0, discountAmount: 0, payable: 0 };
  }

  // Robust Bill Out: open new window first, then POST cart/totals to print_receipt.php with that target
  async function handleBillOut() {
    const cart = getCart();
    if (!cart || cart.length === 0) {
      alert('Cart is empty.');
      return;
    }

    // open a blank window first so popup blockers can be detected
    const w = window.open('', '_blank', 'width=820,height=920,menubar=no,toolbar=no,location=no,status=no');
    if (!w) {
      alert('Popup blocked. Please allow popups for this site to print receipts.');
      return;
    }

    // build form, attach to document, submit to new window
    const form = document.createElement('form');
    form.method = 'POST';
    // Adjust path if your print_receipt.php is in a different folder
    form.action = 'php/print_receipt.php';
    form.target = w.name;

    const inputCart = document.createElement('input');
    inputCart.type = 'hidden';
    inputCart.name = 'cart';
    inputCart.value = JSON.stringify(cart);
    form.appendChild(inputCart);

    const inputTotals = document.createElement('input');
    inputTotals.type = 'hidden';
    inputTotals.name = 'totals';
    inputTotals.value = JSON.stringify(getTotals());
    form.appendChild(inputTotals);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    try { w.focus(); } catch (e) { /* ignore focus errors */ }
  }

  // Proceed: separate operation — calls API to update stock in DB
  // Expects an endpoint at api/update_stock.php that accepts JSON { items: [{id, qty}, ...] }
  async function handleProceed() {
    const cart = getCart();
    if (!cart || cart.length === 0) {
      alert('No items to proceed.');
      return;
    }

    if (!confirm('Proceed with this order and update stock?')) return;

    // disable proceed button if present
    const proceedBtn = document.getElementById('proceedBtn') || document.querySelector('.proceed-btn');
    const billOutBtn = document.getElementById('billOutBtn') || document.querySelector('.hold-btn');

    if (proceedBtn) proceedBtn.disabled = true;
    if (billOutBtn) billOutBtn.disabled = true;

    try {
      // prepare payload
      const items = cart.map(i => ({ id: i.id, qty: i.qty }));
      const res = await fetch('api/update_stock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items })
      });

      if (!res.ok) {
        throw new Error('Network response was not OK: ' + res.status);
      }

      const body = await res.json();
      if (body.success) {
        alert('Stock updated successfully.');
        // Optionally refresh product list/UI. If your app has a reload function, call it:
        if (typeof loadProducts === 'function') {
          await loadProducts();
        } else {
          location.reload(); // fallback if no JS refresh helper
        }
      } else {
        const msg = body.message || (body.errors ? body.errors.join('\n') : 'Unknown error');
        alert('Failed to update stock: ' + msg);
      }
    } catch (err) {
      console.error(err);
      alert('Error while updating stock: ' + (err.message || err));
    } finally {
      if (proceedBtn) proceedBtn.disabled = false;
      if (billOutBtn) billOutBtn.disabled = false;
    }
  }

  // Wire up buttons if present on the page
  document.addEventListener('DOMContentLoaded', function () {
    const billOutBtn = document.getElementById('billOutBtn') || document.querySelector('.hold-btn');
    const proceedBtn = document.getElementById('proceedBtn') || document.querySelector('.proceed-btn');

    if (billOutBtn) {
      // Remove old handlers to avoid double-binding (defensive)
      billOutBtn.replaceWith(billOutBtn.cloneNode(true));
      const newBillBtn = document.getElementById('billOutBtn') || document.querySelector('.hold-btn');
      newBillBtn.addEventListener('click', function (e) {
        e.preventDefault();
        handleBillOut();
      });
    }

    if (proceedBtn) {
      proceedBtn.replaceWith(proceedBtn.cloneNode(true));
      const newProceedBtn = document.getElementById('proceedBtn') || document.querySelector('.proceed-btn');
      newProceedBtn.addEventListener('click', function (e) {
        e.preventDefault();
        handleProceed();
      });
    }
  });

  // Expose functions for debugging if needed
  window._club_tryara = window._club_tryara || {};
  window._club_tryara.handleBillOut = handleBillOut;
  window._club_tryara.handleProceed = handleProceed;
})();