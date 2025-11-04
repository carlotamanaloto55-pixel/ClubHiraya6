// Updated calendar.js — ensures date is encoded and logs attempted URLs
(function () {
  const inlineCalendarEl = () => document.getElementById('inlineCalendar');
  const selectedDateHeader = () => document.getElementById('selectedDateHeader');
  const reservationsList = () => document.getElementById('reservationsList');
  const timesGrid = () => document.getElementById('timesGrid');
  const availabilityList = () => document.getElementById('availabilityList');
  const dateTimePanel = () => document.querySelector('.date-time-panel');
  const filterDateInput = () => document.getElementById('filterDateInput');

  // Filter buttons
  const filterDateBtn = document.getElementById('filterDate');
  const filterTimeBtn = document.getElementById('filterTime');
  const filterAllBtn = document.getElementById('filterAll'); // used to hide panel again

  const TIMES = [
    '10:00','11:00','12:00','13:00','14:00',
    '15:00','16:00','17:00','18:00','19:00',
    '20:00','21:00','22:00'
  ];

  let selectedDate = null;
  let selectedTimeBtn = null;
  let fpInstance = null; // flatpickr instance

  if (dateTimePanel()) dateTimePanel().style.display = 'none';

  function renderTimes() {
    const tg = timesGrid();
    if (!tg) return;
    tg.innerHTML = '';
    TIMES.forEach(t => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'time-slot-btn';
      btn.textContent = t;
      btn.dataset.time = t;
      btn.addEventListener('click', () => {
        if (!selectedDate) return;
        if (selectedTimeBtn) selectedTimeBtn.classList.remove('selected');
        btn.classList.add('selected');
        selectedTimeBtn = btn;
        fetchAvailability(selectedDate, t);
      });
      tg.appendChild(btn);
    });
  }

  function showDatePanel() { if (!dateTimePanel()) return; dateTimePanel().style.display = 'flex'; }
  function hideDatePanel() { if (!dateTimePanel()) return; dateTimePanel().style.display = 'none'; }

  function initFilterButtons() {
    if (filterDateBtn) filterDateBtn.addEventListener('click', showDatePanel);
    if (filterTimeBtn) filterTimeBtn.addEventListener('click', showDatePanel);
    if (filterAllBtn) filterAllBtn.addEventListener('click', hideDatePanel);
  }

  function initCalendar() {
    const calEl = inlineCalendarEl();
    if (!calEl || !window.flatpickr) return;
    if (fpInstance && typeof fpInstance.destroy === 'function') {
      try { fpInstance.destroy(); } catch (e) { /* ignore */ }
      fpInstance = null;
    }

    fpInstance = flatpickr(calEl, {
      inline: true,
      dateFormat: 'Y-m-d',
      defaultDate: new Date(),
      minDate: 'today',
      onChange: function (selectedDates, dateStr) {
        selectedDate = dateStr;
        const hdr = selectedDateHeader();
        if (hdr) hdr.textContent = `Date: ${dateStr}`;
        const fdi = filterDateInput();
        if (fdi) fdi.value = dateStr;
        if (selectedTimeBtn) selectedTimeBtn.classList.remove('selected');
        selectedTimeBtn = null;
        const avail = availabilityList();
        if (avail) avail.innerHTML = 'Pick a time to see availability';
        fetchReservations(dateStr);
      }
    });

    const todayStr = (new Date()).toISOString().slice(0,10);
    selectedDate = todayStr;
    const hdr = selectedDateHeader();
    if (hdr) hdr.textContent = `Date: ${todayStr}`;
    const fdi = filterDateInput();
    if (fdi) fdi.value = todayStr;
    renderTimes();
    fetchReservations(todayStr);

    if (fdi) {
      fdi.removeEventListener('change', onFilterDateInputChange);
      fdi.addEventListener('change', onFilterDateInputChange);
    }
  }

  function onFilterDateInputChange() {
    const fdi = filterDateInput();
    if (!fdi) return;
    const val = fdi.value;
    if (!val) return;
    if (fpInstance && typeof fpInstance.setDate === 'function') {
      try { fpInstance.setDate(val, true); } catch (err) { console.warn('invalid date', val, err); }
    } else {
      selectedDate = val;
      const hdr = selectedDateHeader();
      if (hdr) hdr.textContent = `Date: ${val}`;
      fetchReservations(val);
    }
  }

  async function fetchJsonOrText(url) {
    const res = await fetch(url);
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      return { ok: res.ok, json, text };
    } catch (e) {
      return { ok: res.ok, json: null, text };
    }
  }

  // Try several candidate URLs — always encode the date parameter
async function tryFetchReservations(date) {
  const encoded = encodeURIComponent(date);
  // Update these to match your project path on localhost
  const base = '/ClubHiraya/ClubTryara/api';
  const candidates = [
    `${base}/list_reservations.php?date=${encoded}`,
    `${base}/list_reservation.php?date=${encoded}`,
    `../api/list_reservations.php?date=${encoded}`,
    `../api/list_reservation.php?date=${encoded}`
  ];
  console.debug('fetchReservations: trying candidate URLs', candidates);

  for (const u of candidates) {
    try {
      const result = await fetchJsonOrText(u);
      if (result.ok && result.json && result.json.success) return { ok: true, json: result.json, url: u };
      if (result.json && result.json.success === false) return { ok: false, json: result.json, url: u, text: result.text };
    } catch (err) {
      // try next
    }
  }
  return { ok: false, json: null, url: candidates[0] };
}

  async function fetchReservations(date) {
    const listEl = reservationsList() || document.getElementById('reservationsList');
    if (!listEl) return;
    listEl.innerHTML = 'Loading...';

    try {
      const attempt = await tryFetchReservations(date);

      if (attempt.ok && attempt.json) {
        const rows = attempt.json.data || [];
        if (!rows.length) {
          listEl.innerHTML = '<div class="res-item">No reservations for this date.</div>';
          return;
        }
        listEl.innerHTML = '';
        rows.forEach(r => {
          const item = document.createElement('div');
          item.className = 'res-item';
          const start = r.start ? r.start.replace(' ', ' at ') : '';
          item.innerHTML = `<strong>${escapeHtml(r.table_name || ('Table ' + (r.table_id||'')))}</strong>
                            <div>${escapeHtml(String(r.party_size || ''))} seat(s) — ${escapeHtml(r.guest || 'Guest')}</div>
                            <div style="font-size:13px;color:#666">${escapeHtml(start)} — status: ${escapeHtml(r.status)}</div>`;
          listEl.appendChild(item);
        });
        return;
      }

      // Get raw text of primary candidate to show helpful error (truncated)
      const primary = `../api/list_reservations.php?date=${encodeURIComponent(date)}`;
      const raw = await fetch(primary).then(r => r.text()).catch(e => e.message || String(e));
      const short = String(raw).slice(0,300);
      listEl.innerHTML = `<div class="res-item">Error loading reservations: ${escapeHtml(short)}${String(raw).length>300?'...':''}</div>`;
    } catch (err) {
      listEl.innerHTML = `<div class="res-item">Network error: ${escapeHtml(err.message || err)}</div>`;
    }
  }

  async function fetchAvailability(date, time) {
    const availEl = availabilityList() || document.getElementById('availabilityList');
    if (!availEl) return;
    availEl.innerHTML = 'Loading availability...';
    const partyInput = document.getElementById('resParty') || document.getElementById('partySelect');
    const seats = partyInput ? Number(partyInput.value) || 1 : 1;
    const durationInput = document.getElementById('resDuration');
    const duration = durationInput ? Number(durationInput.value) || 90 : 90;

    try {
      const url = `../api/get_availability.php?date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}&duration=${encodeURIComponent(duration)}&seats=${encodeURIComponent(seats)}`;
      const result = await fetchJsonOrText(url);

      if (!result.ok && result.text && result.text.indexOf('Not Found') !== -1) {
        availEl.innerHTML = '<div class="avail-item">Availability API not found (get_availability.php). Please implement this endpoint.</div>';
        return;
      }

      if (!result.ok) {
        availEl.innerHTML = `<div class="avail-item">Error: ${escapeHtml(result.text)}</div>`;
        return;
      }
      if (!result.json || !result.json.success) {
        const err = result.json && result.json.error ? result.json.error : result.text;
        availEl.innerHTML = `<div class="avail-item">Network error: ${escapeHtml(err)}</div>`;
        return;
      }
      const rows = result.json.data;
      if (!rows || rows.length === 0) {
        availEl.innerHTML = '<div class="avail-item">No available tables for this time.</div>';
        return;
      }

      availEl.innerHTML = '';
      rows.forEach(t => {
        const el = document.createElement('div');
        el.className = 'avail-item';
        el.innerHTML = `<div>
                          <strong>${escapeHtml(t.name || ('Table ' + t.id))}</strong>
                          <div style="font-size:13px;color:#666">${escapeHtml(String(t.seats))} seat(s) — ${escapeHtml(t.status||'')}</div>
                        </div>
                        <div>
                          <button type="button" class="btn ghost" data-table-id="${t.id}">Reserve</button>
                        </div>`;
        const btn = el.querySelector('button');
        btn.addEventListener('click', () => {
          openReservationModalWith(t.id, date, time, seats, duration);
        });
        availEl.appendChild(el);
      });
    } catch (err) {
      availEl.innerHTML = `<div class="avail-item">Network error: ${escapeHtml(err.message || err)}</div>`;
    }
  }

  function openReservationModalWith(tableId, date, time, party_size, duration) {
    const resTableId = document.getElementById('resTableId');
    const resDate = document.getElementById('resDate');
    const resTime = document.getElementById('resTime');
    const resParty = document.getElementById('resParty');
    const resDuration = document.getElementById('resDuration');

    if (resTableId) resTableId.value = tableId || '';
    if (resDate) resDate.value = date || '';
    if (resTime) resTime.value = time || '';
    if (resParty) resParty.value = party_size || 1;
    if (resDuration) resDuration.value = duration || 90;

    const openBtn = document.getElementById('btnAddReservation') || document.getElementById('fabNew');
    if (openBtn) {
      openBtn.click();
      setTimeout(() => {
        const submit = document.getElementById('resSubmit');
        if (submit) submit.focus();
      }, 300);
    } else {
      alert('Reservation modal not found — please open the New reservation modal manually.');
    }
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function start() {
    initFilterButtons();
    initCalendar();
    if (!inlineCalendarEl() || !window.flatpickr) {
      const mo = new MutationObserver((mutations, observer) => {
        if (inlineCalendarEl() && window.flatpickr) {
          observer.disconnect();
          initCalendar();
        }
      });
      mo.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();