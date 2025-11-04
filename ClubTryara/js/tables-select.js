/**
 * tables-select.js (event-delegation version)
 * - Inserts the reserved-table UI above .compute-actions
 * - Uses document-level delegated click handlers so event wiring survives DOM replacements
 * - Fetches reserved tables from tables/get_reserved_tables.php when checkbox is checked
 * - Renders table rows and supports Select -> applies table price into order computation
 *
 * Drop this file at: ClubTryara/js/tables-select.js (overwrite existing)
 */

(function () {
  let selectedTable = null;
  let observer = null;

  // Ensure the reserved UI exists (idempotent)
  function ensureReservedUI() {
    if (document.getElementById('use-reserved-table')) return;

    const orderCompute = document.getElementById('orderCompute') || document.querySelector('.order-compute');
    const orderSection = document.querySelector('.order-section');
    if (!orderCompute && !orderSection) return;

    const reservedBlock = document.createElement('div');
    reservedBlock.className = 'reserved-table-block';
    reservedBlock.style.padding = '8px';
    reservedBlock.style.borderBottom = '1px solid rgba(0,0,0,0.05)';
    reservedBlock.style.boxSizing = 'border-box';

    const label = document.createElement('label');
    label.className = 'reserved-checkbox-label';
    label.style.display = 'flex';
    label.style.alignItems = 'center';
    label.style.gap = '8px';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = 'use-reserved-table';
    checkbox.setAttribute('aria-controls', 'tablesModal');

    const span = document.createElement('span');
    span.textContent = 'Customer has a reserved table';

    label.appendChild(checkbox);
    label.appendChild(span);
    reservedBlock.appendChild(label);

    const chooseBtn = document.createElement('button');
    chooseBtn.type = 'button';
    chooseBtn.id = 'open-tables-btn';
    chooseBtn.className = 'btn-small';
    chooseBtn.textContent = 'Choose table';
    chooseBtn.disabled = false; // clickable; the script will fetch reserved when checkbox is checked
    chooseBtn.style.marginTop = '8px';
    reservedBlock.appendChild(chooseBtn);

    const summary = document.createElement('div');
    summary.id = 'selected-table-summary';
    summary.style.display = 'none';
    summary.style.marginTop = '8px';
    summary.style.fontSize = '13px';

    summary.innerHTML =
      'Selected table: <strong id="selected-table-name">—</strong> (Table <span id="selected-table-number">—</span>, Party size: <span id="selected-table-party">—</span>, Price: ₱<span id="selected-table-price">0.00</span>) ';

    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.id = 'clear-selected-table';
    clearBtn.className = 'btn-link';
    clearBtn.style.marginLeft = '8px';
    clearBtn.textContent = 'Clear';
    summary.appendChild(clearBtn);

    reservedBlock.appendChild(summary);

    if (orderCompute) {
      const computeActions = orderCompute.querySelector('.compute-actions');
      if (computeActions) {
        orderCompute.insertBefore(reservedBlock, computeActions);
      } else {
        orderCompute.insertBefore(reservedBlock, orderCompute.firstChild);
      }
    } else {
      const orderButtons = orderSection.querySelector('.order-buttons');
      if (orderButtons) {
        orderSection.insertBefore(reservedBlock, orderButtons);
      } else {
        orderSection.appendChild(reservedBlock);
      }
    }
  }

  // Render rows into modal table
  function renderTablesToModal(rows) {
    const tablesLoading = document.getElementById('tables-loading');
    const tablesEmpty = document.getElementById('tables-empty');
    const tablesList = document.getElementById('tables-list');
    if (tablesLoading) tablesLoading.style.display = 'none';

    if (!Array.isArray(rows) || rows.length === 0) {
      if (tablesList) tablesList.style.display = 'none';
      if (tablesEmpty) tablesEmpty.style.display = '';
      return;
    }
    if (tablesList) tablesList.style.display = '';
    if (tablesEmpty) tablesEmpty.style.display = 'none';

    const tbody = tablesList.querySelector('tbody');
    tbody.innerHTML = '';

    rows.forEach((t) => {
      const tr = document.createElement('tr');

      const nameTd = document.createElement('td');
      nameTd.style.padding = '6px';
      nameTd.textContent = t.name || t.guest_name || '';
      tr.appendChild(nameTd);

      const numberTd = document.createElement('td');
      numberTd.style.padding = '6px';
      numberTd.textContent = t.table_number || t.table_no || t.id || '';
      tr.appendChild(numberTd);

      const partyTd = document.createElement('td');
      partyTd.style.padding = '6px';
      partyTd.textContent = t.party_size || t.pax || '';
      tr.appendChild(partyTd);

      const statusTd = document.createElement('td');
      statusTd.style.padding = '6px';
      statusTd.textContent = t.status || t.reservation_status || '';
      tr.appendChild(statusTd);

      const priceTd = document.createElement('td');
      priceTd.style.padding = '6px';
      priceTd.style.textAlign = 'right';
      priceTd.textContent = (parseFloat(t.price) || 0).toFixed(2);
      tr.appendChild(priceTd);

      const actionTd = document.createElement('td');
      actionTd.style.padding = '6px';
      const selectBtn = document.createElement('button');
      selectBtn.className = 'btn-small table-select-btn';
      selectBtn.type = 'button';
      selectBtn.textContent = 'Select';
      // store table data on dataset so delegated handler can pick it up
      selectBtn.dataset.table = JSON.stringify({
        id: t.id || t.table_id || null,
        name: t.name || t.guest_name || '',
        table_number: t.table_number || t.table_no || '',
        party_size: t.party_size || t.pax || '',
        status: t.status || t.reservation_status || '',
        price: parseFloat(t.price) || 0
      });
      actionTd.appendChild(selectBtn);
      tr.appendChild(actionTd);

      tbody.appendChild(tr);
    });
  }

  // Show / hide modal
  function showModal() {
    const tablesModal = document.getElementById('tablesModal');
    if (!tablesModal) return;
    tablesModal.classList.remove('hidden');
    tablesModal.setAttribute('tabindex', '-1');
    tablesModal.focus && tablesModal.focus();
  }
  function hideModal() {
    const tablesModal = document.getElementById('tablesModal');
    if (!tablesModal) return;
    tablesModal.classList.add('hidden');
  }

  // Fetch tables: reserved if useReserved true, otherwise attempts all (fallback)
  function fetchTables(useReserved) {
    const reservedUrl = 'tables/get_reserved_tables.php';
    const allUrl = 'tables/get_all_tables.php'; // optional; if missing will fallback
    const url = useReserved ? reservedUrl : allUrl;

    return fetch(url, { method: 'GET', credentials: 'same-origin' })
      .then((r) => {
        if (!r.ok) {
          // if we tried allUrl and it failed, fallback to reservedUrl
          if (!useReserved) return fetch(reservedUrl, { method: 'GET', credentials: 'same-origin' });
          throw new Error('Network response not ok');
        }
        return r.json();
      });
  }

  // Apply selected table object to UI and computation
  function applySelectedTable(table) {
    selectedTable = table;

    const selectedName = document.getElementById('selected-table-name');
    const selectedNumber = document.getElementById('selected-table-number');
    const selectedParty = document.getElementById('selected-table-party');
    const selectedPriceEl = document.getElementById('selected-table-price');
    const selectedSummary = document.getElementById('selected-table-summary');
    const checkbox = document.getElementById('use-reserved-table');
    const openBtn = document.getElementById('open-tables-btn');

    if (selectedName) selectedName.textContent = table.name || '—';
    if (selectedNumber) selectedNumber.textContent = table.table_number || table.id || '—';
    if (selectedParty) selectedParty.textContent = table.party_size || '—';
    if (selectedPriceEl) selectedPriceEl.textContent = (parseFloat(table.price) || 0).toFixed(2);
    if (selectedSummary) selectedSummary.style.display = '';

    if (checkbox && !checkbox.checked) checkbox.checked = true;
    if (openBtn) openBtn.disabled = false;

    // Dispatch event for integration
    const ev = new CustomEvent('table-selected', { detail: table });
    window.dispatchEvent(ev);

    // Persist price to body dataset for computeNumbers fallback
    document.body.dataset.reservedTablePrice = (parseFloat(table.price) || 0);

    // Try to apply table price to totals
    applyTablePriceToComputation(parseFloat(table.price) || 0);
  }

  // Clear selection
  function clearSelectedTable() {
    selectedTable = null;
    const selectedName = document.getElementById('selected-table-name');
    const selectedNumber = document.getElementById('selected-table-number');
    const selectedParty = document.getElementById('selected-table-party');
    const selectedPriceEl = document.getElementById('selected-table-price');
    const selectedSummary = document.getElementById('selected-table-summary');
    const checkbox = document.getElementById('use-reserved-table');
    const openBtn = document.getElementById('open-tables-btn');

    if (selectedName) selectedName.textContent = '—';
    if (selectedNumber) selectedNumber.textContent = '—';
    if (selectedParty) selectedParty.textContent = '—';
    if (selectedPriceEl) selectedPriceEl.textContent = '0.00';
    if (selectedSummary) selectedSummary.style.display = 'none';

    const ev = new CustomEvent('table-cleared');
    window.dispatchEvent(ev);

    document.body.dataset.reservedTablePrice = 0;
    applyTablePriceToComputation(0, { clear: true });

    if (checkbox) checkbox.checked = false;
    if (openBtn) openBtn.disabled = false;
  }

  // Try to apply price to existing totals integration points
  function applyTablePriceToComputation(price, opts = {}) {
    try {
      if (typeof window.applyReservedTablePrice === 'function') {
        if (opts.clear) window.applyReservedTablePrice(null);
        else window.applyReservedTablePrice(price);
        return;
      }

      if (typeof window.recomputeTotals === 'function') {
        if (opts.clear) window.recomputeTotals({ reservedTablePrice: 0, clearReserved: true });
        else window.recomputeTotals({ reservedTablePrice: price });
        return;
      }

      // Fallback: computeNumbers/renderOrder in app.js reads document.body.dataset.reservedTablePrice
      // and will include it on next render. Trigger a re-render if the app exposes renderOrder()
      if (typeof window.renderOrder === 'function') {
        if (opts.clear) document.body.dataset.reservedTablePrice = 0;
        else document.body.dataset.reservedTablePrice = price;
        window.renderOrder();
      }
    } catch (err) {
      console.error('applyTablePriceToComputation error', err);
    }
  }

  // Delegated click handler (for buttons that may be replaced)
  function delegatedClickHandler(e) {
    const chooseBtn = e.target.closest && e.target.closest('#open-tables-btn');
    if (chooseBtn) {
      // Open modal and fetch appropriate table list based on checkbox state
      const useReserved = !!(document.getElementById('use-reserved-table') && document.getElementById('use-reserved-table').checked);
      const tablesLoading = document.getElementById('tables-loading');
      if (tablesLoading) tablesLoading.style.display = '';
      showModal();
      fetchTables(useReserved)
        .then((data) => renderTablesToModal(data))
        .catch((err) => {
          console.error('Failed to fetch tables', err);
          const tl = document.getElementById('tables-loading');
          if (tl) tl.textContent = 'Failed to load tables';
          const tlst = document.getElementById('tables-list');
          if (tlst) tlst.style.display = 'none';
          const tempty = document.getElementById('tables-empty');
          if (tempty) tempty.style.display = 'none';
        });
      return;
    }

    const clearBtn = e.target.closest && e.target.closest('#clear-selected-table');
    if (clearBtn) {
      clearSelectedTable();
      return;
    }

    const closeModalBtn = e.target.closest && e.target.closest('#closeTablesModal');
    if (closeModalBtn) {
      hideModal();
      return;
    }

    // Click on a Select button in modal (delegated). We stored table data in dataset.table JSON.
    const selBtn = e.target.closest && e.target.closest('.table-select-btn');
    if (selBtn && selBtn.dataset && selBtn.dataset.table) {
      try {
        const tableObj = JSON.parse(selBtn.dataset.table);
        applySelectedTable(tableObj);
      } catch (err) {
        console.error('Failed to parse table data', err);
      }
      hideModal();
      return;
    }

    // Click on modal backdrop to close
    if (e.target && e.target.id === 'tablesModal') {
      hideModal();
      return;
    }
  }

  // MutationObserver to re-insert UI when compute area is replaced
  function setupObserver() {
    if (observer) return;
    const orderSection = document.querySelector('.order-section');
    if (!orderSection) return;

    observer = new MutationObserver(() => {
      ensureReservedUI();
    });
    observer.observe(orderSection, { childList: true, subtree: true });
  }

  // Init
  function init() {
    ensureReservedUI();
    // attach delegated listener once
    document.removeEventListener('click', delegatedClickHandler); // safe to call
    document.addEventListener('click', delegatedClickHandler);
    // ESC key closes modal
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape') hideModal();
    });
    setupObserver();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // small API
  window.tablesSelect = window.tablesSelect || {};
  window.tablesSelect.getSelectedTable = () => selectedTable;
  window.tablesSelect.clearSelectedTable = clearSelectedTable;
  window.tablesSelect.ensureReservedUI = ensureReservedUI;
})();