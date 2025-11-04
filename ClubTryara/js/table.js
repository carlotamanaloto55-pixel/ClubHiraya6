// ../js/table.js
// Render table cards and switch between All / Party / Date / Time views.
// Wired to a simple API: loads tables from API_GET and updates via API_UPDATE.
// Falls back to local sample data if the API is unavailable.
//
// This version includes:
// - per-card Delete button (wired to API_DELETE with local fallback)
// - Create flow posts to API_CREATE (with local fallback) so new tables persist to DB

document.addEventListener('DOMContentLoaded', () => {
  // API endpoints
  const API_GET = '../api/get_tables.php';
  const API_UPDATE = '../api/update_table.php';
  const API_DELETE = '../api/delete_table.php'; // delete endpoint (optional)
  const API_CREATE = '../api/create_table.php'; // new create endpoint

  // Data
  let tablesData = [];

  // DOM refs
  const viewHeader = document.getElementById('viewHeader');
  const viewContent = document.getElementById('viewContent');
  let cardsGrid = document.getElementById('cardsGrid'); // recreated per view
  const searchInput = document.getElementById('searchInput');
  const searchClear = document.getElementById('searchClear');
  const filterButtons = document.querySelectorAll('.filter-btn');
  const partyControl = document.getElementById('partyControl');
  const partySelect = document.getElementById('partySelect');
  const dateControl = document.getElementById('dateControl');
  const dateInput = document.getElementById('filterDateInput');
  const timeControl = document.getElementById('timeControl');
  const timeInput = document.getElementById('filterTimeInput');

  if (!viewHeader || !viewContent) {
    console.error('Missing view containers (#viewHeader or #viewContent)');
    return;
  }

  // State
  const state = {
    filter: 'all',
    search: '',
    partySeats: 'any',
    date: '',
    time: '',
    selectedId: null
  };

  // Helpers
  function capitalize(s) { return s && s.length ? s[0].toUpperCase() + s.slice(1) : ''; }
  function escapeHtml(text = '') {
    return String(text).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
  }

  // Fetch tables from server, fallback to sample data if API fails
  async function loadTables() {
    try {
      const res = await fetch(API_GET, { cache: 'no-store' });
      if (!res.ok) throw new Error('Network response was not ok: ' + res.status);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Failed to load');
      tablesData = json.data.map(t => ({
        id: Number(t.id),
        name: t.name,
        status: t.status,
        seats: Number(t.seats),
        guest: t.guest || ''
      }));
      renderView();
    } catch (err) {
      console.warn('loadTables(): API failed, falling back to local sample data. Error:', err);
      tablesData = [
        { id: 1, name: 'Table 1', status: 'occupied', seats: 6, guest: 'Taenamo Jiro' },
        { id: 2, name: 'Table 2', status: 'available', seats: 4, guest: 'Jo| co1' },
        { id: 3, name: 'Table 3', status: 'reserved', seats: 2, guest: '' },
        { id: 4, name: 'Table 4', status: 'occupied', seats: 4, guest: '' },
        { id: 5, name: 'Table 5', status: 'available', seats: 2, guest: '' },
        { id: 6, name: 'Table 6', status: 'available', seats: 8, guest: '' },
        { id: 7, name: 'Table 7', status: 'available', seats: 2, guest: '' },
        { id: 8, name: 'Table 8', status: 'reserved', seats: 4, guest: '' },
        { id: 9, name: 'Table 9', status: 'occupied', seats: 6, guest: '' }
      ];
      const grid = document.getElementById('cardsGrid');
      if (grid) grid.innerHTML = `<div style="padding:18px;color:#900">Using local fallback data (API load failed).</div>`;
      renderView();
    }
  }

  // Update local data helper
  function updateTableData(id, updates) {
    const idx = tablesData.findIndex(t => t.id === Number(id));
    if (idx === -1) return false;
    tablesData[idx] = Object.assign({}, tablesData[idx], updates);
    return true;
  }

  // Remove table from local data helper
  function removeTableData(id) {
    const idx = tablesData.findIndex(t => t.id === Number(id));
    if (idx === -1) return false;
    tablesData.splice(idx, 1);
    return true;
  }

  // Render cards into a container
  function renderCardsInto(container, data, opts = {}) {
    container.innerHTML = '';
    if (!data.length) {
      container.innerHTML = '<div style="padding:18px; font-weight:700">No tables found</div>';
      return;
    }

    data.forEach(tbl => {
      const card = document.createElement('div');
      card.className = 'table-card' + (opts.light ? ' light' : '');
      card.setAttribute('role', 'button');
      card.setAttribute('tabindex', '0');
      card.dataset.id = tbl.id;

      if (state.selectedId === tbl.id) card.classList.add('active');

      const statusDotColor = tbl.status === 'available' ? '#00b256' : tbl.status === 'reserved' ? '#ffd400' : '#d20000';

      card.innerHTML = `
        <div class="title">${escapeHtml(tbl.name)}</div>
        <div class="status-row">
          <span class="status-dot" style="background:${statusDotColor}"></span>
          <span class="status-label">${escapeHtml(capitalize(tbl.status))}</span>
        </div>
        <div class="seats-row"><span style="font-size:18px">👥</span><div>${escapeHtml(String(tbl.seats))} Seats</div></div>
        ${tbl.guest ? `<div class="guest">${escapeHtml(tbl.guest)}</div>` : ''}
        <div class="card-actions" aria-hidden="false">
          <button class="icon-btn edit-btn" aria-label="Edit table" title="Edit">✎</button>
          <button class="icon-btn toggle-btn" aria-label="Toggle status" title="Toggle">⟳</button>
          <button class="icon-btn clear-btn" aria-label="Clear table" title="Clear">🗑</button>
          <button class="icon-btn delete-btn" aria-label="Delete table" title="Delete">✖</button>
        </div>
      `;

      // card selection (click or keyboard)
      card.addEventListener('click', () => setSelected(tbl.id));
      card.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setSelected(tbl.id); }
      });

      // per-card action handlers
      const editBtn = card.querySelector('.edit-btn');
      const toggleBtn = card.querySelector('.toggle-btn');
      const clearBtn = card.querySelector('.clear-btn');
      const deleteBtn = card.querySelector('.delete-btn');

      if (editBtn) {
        editBtn.addEventListener('click', e => { e.stopPropagation(); openEditModal(tbl); });
      }
      if (toggleBtn) {
        toggleBtn.addEventListener('click', e => { e.stopPropagation(); quickToggleStatus(tbl); });
      }
      if (clearBtn) {
        clearBtn.addEventListener('click', e => { e.stopPropagation(); confirmClear(tbl); });
      }
      if (deleteBtn) {
        deleteBtn.addEventListener('click', e => { e.stopPropagation(); confirmDelete(tbl); });
      }

      container.appendChild(card);
    });
  }

  function setSelected(id) {
    state.selectedId = id;
    document.querySelectorAll('.table-card').forEach(c => {
      c.classList.toggle('active', c.dataset.id == id);
    });
    const selected = tablesData.find(t => t.id === Number(id));
    if (selected) console.info('Selected table:', selected.name);
  }

  // Filtering helper
  function filterBySearchAndParty(data) {
    const s = state.search.trim().toLowerCase();
    const seatsFilter = state.partySeats;
    return data.filter(t => {
      if (s) {
        const hay = (t.name + ' ' + (t.guest || '')).toLowerCase();
        if (!hay.includes(s)) return false;
      }
      if (state.filter === 'party' && seatsFilter !== 'any') {
        const v = Number(seatsFilter);
        const [min, max] = v === 2 ? [1, 2] : v === 4 ? [3, 4] : v === 6 ? [5, 6] : [7, 8];
        if (t.seats < min || t.seats > max) return false;
      }
      return true;
    });
  }

  // Views
  function renderAllView() {
    viewHeader.innerHTML = '<h1>All Tables</h1>';
    viewContent.innerHTML = `<div class="cards-grid" id="cardsGrid" role="list"></div>`;
    cardsGrid = document.getElementById('cardsGrid');
    state.filter = 'all';
    partyControl && partyControl.setAttribute('aria-hidden', 'true');
    dateControl && dateControl.setAttribute('aria-hidden', 'true');
    timeControl && timeControl.setAttribute('aria-hidden', 'true');
    const data = filterBySearchAndParty(tablesData);
    renderCardsInto(cardsGrid, data, { light: false });
  }

  function renderPartyView() {
    viewHeader.innerHTML = '<h1>Party Size</h1>';
    const bucketText = partySelect && partySelect.value !== 'any' ? partySelect.options[partySelect.selectedIndex].text + ' Persons' : 'Any';
    viewHeader.innerHTML += `<div class="view-subtitle">Party Size: <strong>${bucketText}</strong></div>`;
    viewContent.innerHTML = `<div class="cards-grid" id="cardsGrid" role="list"></div>`;
    cardsGrid = document.getElementById('cardsGrid');
    partyControl && partyControl.setAttribute('aria-hidden', 'false');
    dateControl && dateControl.setAttribute('aria-hidden', 'true');
    timeControl && timeControl.setAttribute('aria-hidden', 'true');
    const data = filterBySearchAndParty(tablesData);
    renderCardsInto(cardsGrid, data, { light: true });
  }

  // Date view (renders inline calendar + times/availability/reservations)
  function renderDateView() {
    viewHeader.innerHTML = '<h1>Date</h1>';
    viewContent.innerHTML = `
      <div class="date-layout date-time-panel" aria-live="polite">
        <div class="calendar" aria-hidden="false">
          <div class="calendar-box">
            <div id="inlineCalendar"></div>
            <div id="selectedDateHeader" style="margin-top:12px;font-weight:700">Date:</div>
          </div>
        </div>

        <div class="side-cards" id="sideCards">
          <div style="margin-bottom:12px; font-weight:700">Times</div>
          <div id="timesGrid" class="time-grid" aria-label="Time slots"></div>

          <div style="margin-top:14px; font-weight:700">Availability</div>
          <div id="availabilityList" class="availability-list" style="margin-bottom:10px">Pick a time to see availability</div>

          <div style="margin-top:14px; font-weight:700">Reservations</div>
          <div id="reservationsList" class="reservations-list">No date selected</div>
        </div>
      </div>
    `;
    partyControl && partyControl.setAttribute('aria-hidden', 'true');
    dateControl && dateControl.setAttribute('aria-hidden', 'false');
    timeControl && timeControl.setAttribute('aria-hidden', 'true');

    // Show a short list of currently non-available tables under Reservations (re-using card renderer)
    const sideCards = document.getElementById('sideCards');
    const reservations = tablesData.filter(t => t.status !== 'available').slice(0, 3);
    const smallList = document.createElement('div');
    smallList.style.display = 'grid';
    smallList.style.gap = '10px';
    renderCardsInto(smallList, reservations, { light: false });

    const reservationsListEl = sideCards.querySelector('.reservations-list');
    if (reservationsListEl) reservationsListEl.insertAdjacentElement('afterend', smallList);
    else sideCards.appendChild(smallList);
  }

  function renderTimeView() {
    viewHeader.innerHTML = '<h1>Time</h1>';
    viewContent.innerHTML = `
      <div class="date-layout">
        <div class="calendar" aria-hidden="true">
          <div class="calendar-box">Calendar<br><small>(placeholder)</small></div>
        </div>
        <div class="time-container">
          <div class="time-grid" id="timeGrid"></div>
        </div>
      </div>
    `;
    partyControl && partyControl.setAttribute('aria-hidden', 'true');
    dateControl && dateControl.setAttribute('aria-hidden', 'true');
    timeControl && timeControl.setAttribute('aria-hidden', 'false');

    const timeGrid = document.getElementById('timeGrid');
    const times = ['4:00 PM', '6:00 PM', '7:00 PM', '9:00 PM', '12:00 PM', '1:00 PM'];
    timeGrid.innerHTML = '';
    times.forEach(t => {
      const btn = document.createElement('button');
      btn.className = 'time-slot';
      btn.textContent = t;
      btn.addEventListener('click', () => {
        document.querySelectorAll('.time-slot').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
      });
      timeGrid.appendChild(btn);
    });
  }

  // Router
  function renderView() {
    switch (state.filter) {
      case 'party': renderPartyView(); break;
      case 'date': renderDateView(); break;
      case 'time': renderTimeView(); break;
      default: renderAllView();
    }
  }

  // Edit/create modal
  function openEditModal(table) {
    const isNew = !table || !table.id;
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true" aria-label="${isNew ? 'Create Table' : 'Edit ' + escapeHtml(table.name)}">
        <h3>${isNew ? 'New Reservation / Table' : 'Edit ' + escapeHtml(table.name)}</h3>
        <div class="form-row">
          <label for="modalName">Table Name</label>
          <input id="modalName" type="text" />
        </div>
        <div class="form-row">
          <label for="modalStatus">Status</label>
          <select id="modalStatus">
            <option value="available">Available</option>
            <option value="occupied">Occupied</option>
            <option value="reserved">Reserved</option>
          </select>
        </div>
        <div class="form-row">
          <label for="modalSeats">Seats</label>
          <input id="modalSeats" type="number" min="1" max="50" />
        </div>
        <div class="form-row">
          <label for="modalGuest">Guest (leave blank to clear)</label>
          <input id="modalGuest" type="text" />
        </div>
        <div class="modal-actions">
          <button id="modalCancel" class="btn">Cancel</button>
          <button id="modalSave" class="btn primary">${isNew ? 'Create' : 'Save'}</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const modalName = overlay.querySelector('#modalName');
    const modalStatus = overlay.querySelector('#modalStatus');
    const modalSeats = overlay.querySelector('#modalSeats');
    const modalGuest = overlay.querySelector('#modalGuest');

    modalName.value = table && table.name ? table.name : (table && table.id ? 'Table ' + table.id : 'New Table');
    modalStatus.value = table && table.status ? table.status : 'reserved';
    modalSeats.value = table && table.seats ? table.seats : 2;
    modalGuest.value = table && table.guest ? table.guest : '';

    overlay.querySelector('#modalCancel').addEventListener('click', () => overlay.remove());

    // ---- START: updated save handler (handles create via API_CREATE + fallback) ----
    overlay.querySelector('#modalSave').addEventListener('click', async () => {
      const name = modalName.value.trim() || (table && table.name) || 'Table';
      const status = modalStatus.value;
      const seats = parseInt(modalSeats.value, 10) || 2;
      const guest = modalGuest.value.trim();

      if (isNew) {
        // Try to create on server first
        if (typeof API_CREATE !== 'undefined') {
          try {
            const res = await fetch(API_CREATE, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ name, status, seats, guest })
            });
            const j = await res.json();
            if (!j.success) throw new Error(j.error || 'Create failed');
            // reload authoritative data from server
            await loadTables();
            overlay.remove();
            return;
          } catch (err) {
            console.warn('API create failed, falling back to local creation:', err);
            // fallthrough to local creation
          }
        }

        // Fallback: local-only creation (useful for dev when API not present)
        const newId = (tablesData.reduce((m, t) => Math.max(m, t.id), 0) || 0) + 1;
        tablesData.push({ id: newId, name, status, seats, guest });
        renderView();
        overlay.remove();
        return;
      }

      // Existing update flow for edits (unchanged)
      if (typeof API_UPDATE !== 'undefined') {
        try {
          const payload = { id: table.id, status, seats, guest, name };
          const res = await fetch(API_UPDATE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });
          const j = await res.json();
          if (!j.success) throw new Error(j.error || 'Update failed');
          await loadTables();
          overlay.remove();
        } catch (err) {
          alert('Failed to update table: ' + err.message);
          console.error(err);
        }
      } else {
        updateTableData(table.id, { name, status, seats, guest });
        renderView();
        overlay.remove();
      }
    });
    // ---- END: updated save handler ----

    overlay.addEventListener('click', ev => { if (ev.target === overlay) overlay.remove(); });
    setTimeout(() => modalName.focus(), 50);
  }

  // Toggle status quickly
  async function quickToggleStatus(table) {
    const next = table.status === 'available' ? 'reserved' : table.status === 'reserved' ? 'occupied' : 'available';

    if (typeof API_UPDATE !== 'undefined') {
      try {
        const res = await fetch(API_UPDATE, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: table.id, status: next })
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.error || 'Update failed');
        await loadTables();
        return;
      } catch (err) {
        console.warn('API toggle failed, falling back to local update:', err);
      }
    }

    updateTableData(table.id, { status: next });
    renderView();
  }

  // Confirm clear (checkout)
  async function confirmClear(table) {
    if (!confirm(`Clear ${table.name}? This will set status to "available" and remove guest.`)) return;

    if (typeof API_UPDATE !== 'undefined') {
      try {
        const res = await fetch(API_UPDATE, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: table.id, status: 'available', guest: '' })
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.error || 'Update failed');
        await loadTables();
        return;
      } catch (err) {
        console.warn('API clear failed, falling back to local update:', err);
      }
    }

    updateTableData(table.id, { status: 'available', guest: '' });
    renderView();
  }

  // Confirm delete (permanent removal)
  async function confirmDelete(table) {
    if (!confirm(`Delete ${table.name}? This action cannot be undone.`)) return;

    // Try server-side delete first
    if (typeof API_DELETE !== 'undefined') {
      try {
        const res = await fetch(API_DELETE, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: table.id })
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.error || 'Delete failed');
        // reload data from server to reflect deletion
        await loadTables();
        return;
      } catch (err) {
        console.warn('API delete failed, falling back to local removal:', err);
        // fall-through to local removal
      }
    }

    // Fallback: remove from local dataset and re-render
    const removed = removeTableData(table.id);
    if (removed) {
      renderView();
    } else {
      alert('Failed to delete table locally.');
    }
  }

  // New reservation
  function openNewReservationModal() {
    openEditModal({});
  }

  // Events wiring
  if (searchInput) {
    searchInput.addEventListener('input', e => {
      state.search = e.target.value;
      renderView();
    });
  }
  if (searchClear) {
    searchClear.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      state.search = '';
      renderView();
    });
  }

  if (filterButtons && filterButtons.length) {
    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        filterButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        state.filter = btn.dataset.filter;
        partyControl && partyControl.classList.toggle('visible', state.filter === 'party');
        renderView();
      });
    });
  }

  if (partySelect) {
    partySelect.addEventListener('change', e => { state.partySeats = e.target.value; renderView(); });
    state.partySeats = partySelect.value || 'any';
  }
  if (dateInput) {
    dateInput.addEventListener('change', e => { state.date = e.target.value; renderView(); });
  }
  if (timeInput) {
    timeInput.addEventListener('change', e => { state.time = e.target.value; renderView(); });
  }

  // Global Add / FAB buttons
  document.getElementById('btnAddReservation')?.addEventListener('click', e => {
    e.preventDefault();
    openNewReservationModal();
  });
  document.getElementById('fabNew')?.addEventListener('click', e => {
    e.preventDefault();
    openNewReservationModal();
  });

  // Inject modal CSS if missing
  (function injectModalCss() {
    if (document.getElementById('modal-styles')) return;
    const css = `
      .modal-overlay {
        position: fixed; left:0; top:0; right:0; bottom:0; background: rgba(0,0,0,0.45);
        display:flex; align-items:center; justify-content:center; z-index:9999;
      }
      .modal {
        background: #fff; padding:18px; border-radius:10px; width:420px; max-width:95%; box-shadow:0 8px 28px rgba(0,0,0,0.4);
      }
      .modal h3 { margin:0 0 12px 0; font-size:18px; }
      .form-row { margin-bottom:10px; display:flex; flex-direction:column; gap:6px; }
      .form-row label { font-weight:700; font-size:13px; }
      .form-row input, .form-row select { padding:8px 10px; font-size:14px; border-radius:6px; border:1px solid #ddd; }
      .modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:8px; }
      .btn { padding:8px 12px; border-radius:8px; border:1px solid #ccc; background:#f5f5f5; cursor:pointer; }
      .btn.primary { background:#001b89; color:#fff; border-color:#001b89; }
    `;
    const s = document.createElement('style');
    s.id = 'modal-styles';
    s.textContent = css;
    document.head.appendChild(s);
  })();

  // Initial load
  loadTables();

  // Expose for debugging
  window._tablesApp = { data: tablesData, state, renderView, renderCardsInto, openEditModal, quickToggleStatus, confirmClear, loadTables };
});