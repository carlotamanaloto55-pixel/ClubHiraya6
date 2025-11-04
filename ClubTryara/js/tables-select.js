/**
 * tables-select.js (event-delegation version)
 * - Same as previous but with improved error/debug handling when fetch fails.
 */

(function () {
  let selectedTable = null;
  let observer = null;

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
    chooseBtn.disabled = true;
    chooseBtn.style.marginTop = '8px';
    reservedBlock.appendChild(chooseBtn);

    const summary = document.createElement('div');
    summary.id = 'selected-table-summary';
    summary.style.display = 'none';
    summary.style.marginTop = '8px';
    summary.style.fontSize = '13px';

    summary.innerHTML =
      'Selected table: <strong id="selected-table-name">—</strong> (Table <span id="selected-table-number">—</span>, Party size: <span id="selected-table-party">—</span>, Price: ₱<span id="selected-table-price">0.00</span>)';

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

    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        chooseBtn.disabled = false;
        const tablesLoading = document.getElementById('tables-loading');
        if (tablesLoading) tablesLoading.style.display = '';
        // prefetch available/reserved tables so modal is ready
        // we request available tables by default (server supports ?type=available)
        fetchTables(true)
          .then((data) => {
            renderTablesToModal(data);
          })
          .catch((err) => {
            console.error('Failed to prefetch tables', err);
            const tl = document.getElementById('tables-loading');
            if (tl) tl.textContent = 'Failed to load tables (prefetch).';
          });
      } else {
        chooseBtn.disabled = true;
        clearSelectedTable();
      }
    });
  }

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

  // Improved fetch with timeout and better fallbacks
  function fetchTables(useReserved) {
    const base = 'tables/get_reserved_tables.php';
    // When checkbox is checked we want to show available tables by default.
    // Map useReserved param: if true we will request 'available' (so it shows seats).
    // If you want reserved-only behavior, change mapping.
    const type = useReserved ? 'available' : 'reserved';
    const url = base + '?type=' + encodeURIComponent(type);

    function fetchWithTimeout(resource, options = {}, ms = 8000) {
      const controller = new AbortController();
      const id = setTimeout(() => controller.abort(), ms);
      return fetch(resource, { ...options, signal: controller.signal })
        .finally(() => clearTimeout(id));
    }

    // Try primary request (with timeout), then fallback to ?type=all
    return fetchWithTimeout(url, { method: 'GET', credentials: 'same-origin' }, 8000)
      .then(async (r) => {
        if (!r.ok) {
          // fallback: try all
          const fall = await fetchWithTimeout(base + '?type=all', { method: 'GET', credentials: 'same-origin' }, 8000).catch(() => null);
          if (fall && fall.ok) return fall.json();
          const txt = await r.text().catch(() => '');
          const e = new Error('HTTP ' + r.status + ' ' + r.statusText);
          e.responseText = txt;
          throw e;
        }
        const text = await r.text();
        try {
          return JSON.parse(text || '[]');
        } catch (err) {
          // fallback: try all
          const fall = await fetchWithTimeout(base + '?type=all', { method: 'GET', credentials: 'same-origin' }, 8000).catch(() => null);
          if (fall && fall.ok) return fall.json();
          const e = new Error('Invalid JSON from server');
          e.responseText = text;
          throw e;
        }
      })
      .catch((err) => {
        const tablesLoading = document.getElementById('tables-loading');
        if (tablesLoading) {
          if (err.name === 'AbortError') {
            tablesLoading.textContent = 'Request timed out — try again or check the server.';
          } else if (err && err.responseText) {
            let t = String(err.responseText);
            try { t = JSON.stringify(JSON.parse(t), null, 2); } catch (e) {}
            tablesLoading.textContent = 'Failed to load tables: ' + (t.length > 1000 ? t.slice(0,1000) + '…' : t);
          } else {
            tablesLoading.textContent = 'Failed to load tables: ' + (err.message || 'Unknown error');
          }
        }
        throw err;
      });
  }

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

    const ev = new CustomEvent('table-selected', { detail: table });
    window.dispatchEvent(ev);

    document.body.dataset.reservedTablePrice = (parseFloat(table.price) || 0);
    applyTablePriceToComputation(parseFloat(table.price) || 0);
  }

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
    if (openBtn) openBtn.disabled = true;
  }

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

      if (typeof window.renderOrder === 'function') {
        if (opts.clear) document.body.dataset.reservedTablePrice = 0;
        else document.body.dataset.reservedTablePrice = price;
        window.renderOrder();
      }
    } catch (err) {
      console.error('applyTablePriceToComputation error', err);
    }
  }

  function delegatedClickHandler(e) {
    const chooseBtn = e.target.closest && e.target.closest('#open-tables-btn');
    if (chooseBtn) {
      const checkbox = document.getElementById('use-reserved-table');
      const useReserved = !!(checkbox && checkbox.checked);
      if (!useReserved) {
        console.warn('Please check "Customer has a reserved table" first.');
        chooseBtn.animate && chooseBtn.animate([{ background: '#f8d7da' }, { background: '' }], { duration: 300 });
        return;
      }

      const tablesLoading = document.getElementById('tables-loading');
      if (tablesLoading) {
        tablesLoading.style.display = '';
        tablesLoading.textContent = 'Loading...';
      }
      showModal();
      fetchTables(useReserved)
        .then((data) => renderTablesToModal(data))
        .catch((err) => {
          console.error('Failed to fetch tables', err);
          // fetchTables already wrote useful info into tables-loading; keep modal open so user can see it
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

    if (e.target && e.target.id === 'tablesModal') {
      hideModal();
      return;
    }
  }

  function setupObserver() {
    if (observer) return;
    const orderSection = document.querySelector('.order-section');
    if (!orderSection) return;

    observer = new MutationObserver(() => {
      ensureReservedUI();
    });
    observer.observe(orderSection, { childList: true, subtree: true });
  }

  function init() {
    ensureReservedUI();
    document.removeEventListener('click', delegatedClickHandler);
    document.addEventListener('click', delegatedClickHandler);
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

  window.tablesSelect = window.tablesSelect || {};
  window.tablesSelect.getSelectedTable = () => selectedTable;
  window.tablesSelect.clearSelectedTable = clearSelectedTable;
  window.tablesSelect.ensureReservedUI = ensureReservedUI;
})();