/**
 * app.js — POS UI
 * - loads server settings (tax/service/currency/notifications)
 * - uses APP_SETTINGS.rates (from settings-sync.js) to convert amounts
 * - click any price to toggle between PHP and the selected currency
 *
 * Replace your app.js with this file and hard-refresh (Ctrl+F5).
 */

document.addEventListener('DOMContentLoaded', () => {
  // Make this async so we can fetch settings before first render
  (async function init() {
    // DOM refs
    const foodsGrid = document.getElementById('foodsGrid');
    const categoryTabs = document.getElementById('categoryTabs');
    const searchBox = document.getElementById('searchBox');
    const orderList = document.getElementById('orderList');
    const orderCompute = document.getElementById('orderCompute');

    const draftModal = document.getElementById('draftModal'); // modal wrapper
    const draftModalContent = draftModal ? draftModal.querySelector('.modal-content') : null;

    const draftBtn = document.getElementById('draftBtn');
    const closeDraftModalFallback = document.getElementById('closeDraftModal');

    const newOrderBtn = document.getElementById('newOrderBtn');
    const refreshBtn = document.getElementById('refreshBtn');

    // Page-level action buttons (these are the single canonical buttons we use)
    const billOutBtn = document.getElementById('billOutBtn'); // page-level Bill Out (must exist in index.php)
    const proceedBtnPage = document.getElementById('proceedBtn'); // page-level Proceed (must exist in index.php)

    // helpers: currency symbols
    const CURRENCY_SYMBOLS = { PHP: '₱', USD: '$', EUR: '€', JPY: '¥' };

    // Ensure page buttons visible
    function ensurePageButtonsVisible() {
      const asideBtns = document.querySelector('.order-section > .order-buttons');
      if (asideBtns) {
        asideBtns.style.position = asideBtns.style.position || 'relative';
        asideBtns.style.zIndex = '20';
        asideBtns.style.background = asideBtns.style.background || 'transparent';
      }
      if (billOutBtn) { billOutBtn.style.display = billOutBtn.style.display || 'inline-block'; billOutBtn.style.zIndex = '21'; }
      if (proceedBtnPage) { proceedBtnPage.style.display = proceedBtnPage.style.display || 'inline-block'; proceedBtnPage.style.zIndex = '21'; }
    }

    function removeInlineComputeButtons() {
      if (!orderCompute) return;
      const inlineBtnContainers = orderCompute.querySelectorAll('.order-buttons');
      inlineBtnContainers.forEach(node => {
        if (!node.closest('.order-section')) node.remove();
      });
      const inlineProceeds = orderCompute.querySelectorAll('.proceed-btn');
      inlineProceeds.forEach(btn => {
        if (proceedBtnPage && btn === proceedBtnPage) return;
        btn.remove();
      });
    }

    ensurePageButtonsVisible();
    removeInlineComputeButtons();

    // ---------- DYNAMIC SETTINGS ----------
    // defaults
    let SERVICE_RATE = 0.10;
    let TAX_RATE = 0.12;
    window.APP_SETTINGS = window.APP_SETTINGS || {};
    window.APP_SETTINGS.notifications = window.APP_SETTINGS.notifications || { sound: false, orderAlerts: false, lowStock: false };
    window.APP_SETTINGS.rates = window.APP_SETTINGS.rates || { USD: 0.01705, EUR: 0.016, JPY: 2.57 };

    async function loadServerSettings() {
      try {
        const res = await fetch('api/get_settings.php', { cache: 'no-store', credentials: 'same-origin' });
        if (!res.ok) throw new Error('Failed to load settings: ' + res.status);
        const s = await res.json();
        SERVICE_RATE = (Number(s.service_charge) || 10) / 100;
        TAX_RATE = (Number(s.tax) || 12) / 100;
        window.APP_SETTINGS.notifications = Object.assign({}, window.APP_SETTINGS.notifications, (s.notifications || {}));
        window.APP_SETTINGS.currency = s.currency || 'PHP';
        window.APP_SETTINGS.dark_mode = !!s.dark_mode;
        // settings-sync.js should have set window.APP_SETTINGS.rates; if not, use defaults above
      } catch (err) {
        console.warn('Failed to fetch server settings, using defaults', err);
      }
    }

    await loadServerSettings();

    // ---------- Currency helpers ----------
    function convertAmountPHP(amountPHP, currency) {
      if (!currency || currency === 'PHP') return amountPHP;
      const rates = (window.APP_SETTINGS && window.APP_SETTINGS.rates) || { USD: 0.01705, EUR: 0.016, JPY: 2.57 };
      const rate = Number(rates[currency]) || 1;
      return amountPHP * rate;
    }
    function formatCurrencyValue(amount, currency) {
      const symbol = CURRENCY_SYMBOLS[currency] || '';
      // For JPY, no decimals
      if (currency === 'JPY') return symbol + Math.round(amount);
      return symbol + Number(amount).toFixed(2);
    }

    // Toggle display helper for price elements (data attributes used)
    function setupPriceToggle(elem, pricePhp) {
      if (!elem) return;
      elem.dataset.pricePhp = Number(pricePhp);
      const targetCurrency = window.APP_SETTINGS.currency || 'PHP';
      const converted = convertAmountPHP(Number(pricePhp), targetCurrency);
      // initial display based on target currency
      if (targetCurrency && targetCurrency !== 'PHP') {
        elem.textContent = formatCurrencyValue(converted, targetCurrency);
        elem.dataset.showing = 'converted';
      } else {
        elem.textContent = formatCurrencyValue(pricePhp, 'PHP');
        elem.dataset.showing = 'orig';
      }
      elem.style.cursor = 'pointer';
      elem.title = 'Click to toggle between PHP and ' + (targetCurrency || 'PHP');
      elem.addEventListener('click', (e) => {
        e.stopPropagation(); // avoid triggering card click (which adds to order)
        const cur = elem.dataset.showing === 'orig' ? 'converted' : 'orig';
        if (cur === 'orig') {
          elem.textContent = formatCurrencyValue(Number(elem.dataset.pricePhp), 'PHP');
          elem.dataset.showing = 'orig';
        } else {
          const t = window.APP_SETTINGS.currency || 'PHP';
          const conv = convertAmountPHP(Number(elem.dataset.pricePhp), t);
          elem.textContent = formatCurrencyValue(conv, t);
          elem.dataset.showing = 'converted';
        }
      });
    }

    // ---------- Notifications helpers ----------
    const lowStockSeen = new Set();

    function playBeep(duration = 220, frequency = 880, volume = 0.08) {
      try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.type = 'sine';
        o.frequency.value = frequency;
        g.gain.value = volume;
        o.connect(g);
        g.connect(ctx.destination);
        o.start();
        setTimeout(() => { o.stop(); ctx.close().catch(()=>{}); }, duration);
      } catch (e) {
        // ignore audio errors
        console.warn('beep failed', e);
      }
    }

    function showToast(message, options = {}) {
      const d = document.createElement('div');
      d.className = 'app-toast';
      d.textContent = message;
      d.style.position = 'fixed';
      d.style.right = '18px';
      d.style.bottom = '18px';
      d.style.padding = '10px 14px';
      d.style.background = 'rgba(0,0,0,0.8)';
      d.style.color = '#fff';
      d.style.borderRadius = '8px';
      d.style.zIndex = 9999;
      d.style.fontWeight = 700;
      d.style.boxShadow = '0 6px 18px rgba(0,0,0,0.18)';
      document.body.appendChild(d);
      setTimeout(() => { d.style.transition = 'opacity 300ms'; d.style.opacity = '0'; }, options.duration || 2200);
      setTimeout(() => { d.remove(); }, (options.duration || 2200) + 350);
    }

    // ---------- Settings used elsewhere in app ----------
    const desiredOrder = [
      "Main Course", "Appetizer", "Soup", "Salad",
      "Seafoods", "Pasta & Noodles", "Sides","Pizza", "Drinks","Alcohol",
      "Cocktails"
    ];

    const DISCOUNT_TYPES = { 'Regular': 0.00, 'Senior Citizen': 0.20, 'PWD': 0.20 };

    let products = [];
    let categories = [];
    let currentCategory = null;
    let order = [];
    let discountRate = DISCOUNT_TYPES['Regular'];
    let discountType = 'Regular';
    let noteValue = '';

    // ---------- PRODUCT LOADING ----------
    async function loadProducts() {
      try {
        const res = await fetch('php/get_products.php', { cache: 'no-store' });
        if (!res.ok) throw new Error('Server returned ' + res.status);
        const data = await res.json();
        if (Array.isArray(data)) products = data;
        else if (Array.isArray(data.foods)) products = data.foods;
        else products = [];
      } catch (err) {
        console.warn('Failed to load php/get_products.php — using sample data', err);
        products = [
          { id: 1, name: 'Lechon Baka', price: 420, category: 'Main Course', image: 'assets/lechon_baka.jpg', description: '' },
          { id: 2, name: 'Hoisin BBQ Pork Ribs', price: 599, category: 'Main Course', image: 'assets/ribs.jpg', description: '' },
          { id: 3, name: 'Mango Habanero', price: 439, category: 'Main Course', image: 'assets/mango.jpg', description: '' },
          { id: 4, name: 'Smoked Carbonara', price: 349, category: 'Pasta & Noodles', image: 'assets/carbonara.jpg', description: '' },
          { id: 5, name: 'Mozzarella Poppers', price: 280, category: 'Appetizer', image: 'assets/poppers.jpg', description: '' },
          { id: 6, name: 'Salmon Tare-Tare', price: 379, category: 'Seafoods', image: 'assets/salmon.jpg', description: '' }
        ];
      }

      buildCategoryList();
      const found = desiredOrder.find(c => categories.includes(c));
      currentCategory = found || (categories.length ? categories[0] : null);
      renderCategoryTabs();
      renderProducts();
      renderOrder();

      // low-stock notifications if the server provided 'stock' field and low stock alerts enabled
      products.forEach(p => {
        if (typeof p.stock === 'number' && p.stock <= 5 && window.APP_SETTINGS.notifications.lowStock) {
          if (!lowStockSeen.has(p.id)) {
            lowStockSeen.add(p.id);
            // show toast & sound once
            showToast(`Low stock: ${p.name} (${p.stock})`);
            if (window.APP_SETTINGS.notifications.sound) playBeep(240, 660, 0.08);
          }
        }
      });
    }

    function buildCategoryList() {
      const set = new Set(products.map(p => String(p.category || '').trim()).filter(Boolean));
      categories = Array.from(set);
    }

    // ---------- CATEGORIES ----------
    function renderCategoryTabs() {
      if (!categoryTabs) return;
      categoryTabs.innerHTML = '';
      desiredOrder.forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'category-btn';
        btn.type = 'button';
        btn.textContent = cat;
        btn.dataset.category = cat;
        if (!categories.includes(cat)) {
          btn.classList.add('empty-category');
          btn.title = 'No items in this category';
        }
        btn.addEventListener('click', () => {
          currentCategory = cat;
          setActiveCategory(cat);
          renderProducts();
        });
        categoryTabs.appendChild(btn);
      });
      const extras = categories.filter(c => !desiredOrder.includes(c));
      extras.forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'category-btn';
        btn.type = 'button';
        btn.textContent = cat;
        btn.dataset.category = cat;
        btn.addEventListener('click', () => {
          currentCategory = cat;
          setActiveCategory(cat);
          renderProducts();
        });
        categoryTabs.appendChild(btn);
      });
      setActiveCategory(currentCategory);
    }
    function setActiveCategory(cat) {
      if (!categoryTabs) return;
      Array.from(categoryTabs.children).forEach(btn => {
        if (btn.dataset.category === cat) btn.classList.add('active');
        else btn.classList.remove('active');
      });
    }

    // ---------- PRODUCTS ----------
    function renderProducts() {
      if (!foodsGrid) return;
      const q = (searchBox && searchBox.value || '').trim().toLowerCase();
      const visible = products.filter(p => {
        if (currentCategory && p.category !== currentCategory) return false;
        if (!q) return true;
        return (p.name && p.name.toLowerCase().includes(q)) ||
               (p.description && p.description.toLowerCase().includes(q));
      });

      foodsGrid.innerHTML = '';
      if (visible.length === 0) {
        const msg = document.createElement('div');
        msg.style.padding = '12px';
        msg.style.color = '#666';
        msg.textContent = 'No products found in this category.';
        foodsGrid.appendChild(msg);
        return;
      }

      visible.forEach(prod => {
        const card = document.createElement('div');
        card.className = 'food-card';
        card.setAttribute('data-id', prod.id);

        // create image element and resolve path safely with fallbacks
        const img = document.createElement('img');

        // raw image path from product
        const raw = (prod.image || 'assets/placeholder.png').toString();

        // helper to set src safely
        function setSrc(path) {
          try {
            img.src = new URL(path, window.location.href).href;
          } catch (e) {
            img.src = path;
          }
        }

        // Try the provided path first
        setSrc(raw);

        // If the image fails to load, attempt fallback paths that place assets under the ClubTryara folder
        img.addEventListener('error', function handleImgError() {
          img.removeEventListener('error', handleImgError);

          const fileName = raw.split('/').pop();
          // Safely remove leading ./ or /
          const trimmedRaw = raw.replace(/^\.\//, '').replace(/^\//, '');

          const candidates = [
            `ClubTryara/${trimmedRaw}`,
            `ClubTryara/assets/${fileName}`,
            `/ClubHiraya/ClubTryara/${trimmedRaw}`,
            `/ClubHiraya/ClubTryara/assets/${fileName}`,
            `/ClubHiraya/${trimmedRaw}`
          ];

          let idx = 0;
          function tryNext() {
            if (idx >= candidates.length) {
              setSrc('assets/placeholder.png');
              return;
            }
            const candidate = candidates[idx++];
            img.addEventListener('error', tryNext, { once: true });
            setSrc(candidate);
          }

          tryNext();
        }, { once: true });

        img.alt = prod.name || 'Product image';
        card.appendChild(img);

        const label = document.createElement('div');
        label.className = 'food-label';
        label.textContent = prod.name;
        card.appendChild(label);

        const price = document.createElement('div');
        price.className = 'food-price';
        // Show converted or PHP depending on server setting. Also allow click to toggle.
        setupPriceToggle(price, Number(prod.price) || 0);
        card.appendChild(price);

        card.addEventListener('click', () => addToOrder(prod));
        foodsGrid.appendChild(card);
      });
    }

    // ---------- ORDER MANAGEMENT ----------
    function addToOrder(prod) {
      const idx = order.findIndex(i => i.id === prod.id);
      if (idx >= 0) order[idx].qty += 1;
      else order.push({ id: prod.id, name: prod.name, price: Number(prod.price) || 0, qty: 1 });
      renderOrder();

      // Trigger order notifications
      if (window.APP_SETTINGS.notifications.orderAlerts) {
        showToast(`Added to order: ${prod.name}`);
      }
      if (window.APP_SETTINGS.notifications.sound) {
        playBeep(160, 880, 0.06);
      }
    }
    function removeFromOrder(prodId) {
      order = order.filter(i => i.id !== prodId);
      renderOrder();
    }
    function changeQty(prodId, qty) {
      const idx = order.findIndex(i => i.id === prodId);
      if (idx >= 0) {
        order[idx].qty = Math.max(0, Math.floor(qty));
        if (order[idx].qty === 0) removeFromOrder(prodId);
      }
      renderOrder();
    }

    // ---------- COMPUTATIONS ----------
    function roundCurrency(n) {
      return Math.round((n + Number.EPSILON) * 100) / 100;
    }
   function computeNumbers() {
    const subtotal = order.reduce((s, i) => s + (i.price * i.qty), 0);
    const serviceCharge = subtotal * SERVICE_RATE;
    const tax = subtotal * TAX_RATE;
    const discountAmount = subtotal * (discountRate || 0);
    
    // Add reserved table price if selected
    const tablePrice = parseFloat(document.body.dataset.reservedTablePrice) || 0;
    const payable = subtotal + serviceCharge + tax - discountAmount + tablePrice;
    
    return {
      subtotal: roundCurrency(subtotal),
      serviceCharge: roundCurrency(serviceCharge),
      tax: roundCurrency(tax),
      discountAmount: roundCurrency(discountAmount),
      tablePrice: roundCurrency(tablePrice),
      payable: roundCurrency(payable)
    };
  }

    // ---------- RENDER ORDER + COMPUTE UI ----------
    function renderOrder() {
      removeInlineComputeButtons();
      ensurePageButtonsVisible();

      if (!orderList || !orderCompute) return;
      orderList.innerHTML = '';
      if (order.length === 0) {
        orderList.textContent = 'No items in order.';
      } else {
        order.forEach(item => {
          const row = document.createElement('div');
          row.className = 'order-item';

          const name = document.createElement('div');
          name.className = 'order-item-name';
          name.textContent = item.name;
          row.appendChild(name);

          // qty controls
          const qtyWrap = document.createElement('div');
          qtyWrap.style.display = 'flex';
          qtyWrap.style.alignItems = 'center';
          qtyWrap.style.gap = '6px';

          const btnMinus = document.createElement('button');
          btnMinus.type = 'button';
          btnMinus.className = 'order-qty-btn';
          btnMinus.textContent = '−';
          btnMinus.title = 'Decrease';
          btnMinus.addEventListener('click', () => changeQty(item.id, item.qty - 1));
          qtyWrap.appendChild(btnMinus);

          const qtyInput = document.createElement('input');
          qtyInput.className = 'order-qty-input';
          qtyInput.type = 'number';
          qtyInput.value = item.qty;
          qtyInput.min = 0;
          qtyInput.addEventListener('change', () => changeQty(item.id, Number(qtyInput.value)));
          qtyWrap.appendChild(qtyInput);

          const btnPlus = document.createElement('button');
          btnPlus.type = 'button';
          btnPlus.className = 'order-qty-btn';
          btnPlus.textContent = '+';
          btnPlus.title = 'Increase';
          btnPlus.addEventListener('click', () => changeQty(item.id, item.qty + 1));
          qtyWrap.appendChild(btnPlus);

          row.appendChild(qtyWrap);

          const price = document.createElement('div');
          // display per-line total; support toggle
          const linePhp = (item.price * item.qty) || 0;
          price.className = 'order-line-price';
          setupPriceToggle(price, linePhp);
          price.style.minWidth = '80px';
          price.style.textAlign = 'right';
          row.appendChild(price);

          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'remove-item-btn';
          removeBtn.innerHTML = '×';
          removeBtn.title = 'Remove';
          removeBtn.addEventListener('click', () => removeFromOrder(item.id));
          row.appendChild(removeBtn);

          orderList.appendChild(row);
        });
      }

      // compute and show in orderCompute area
      const nums = computeNumbers();
      orderCompute.innerHTML = '';

      // compute actions (Discount choices & Note)
      const actions = document.createElement('div');
      actions.className = 'compute-actions';

      const discountBtn = document.createElement('button');
      discountBtn.className = 'compute-btn discount';
      discountBtn.textContent = 'Discount';
      actions.appendChild(discountBtn);

      const noteBtn = document.createElement('button');
      noteBtn.className = 'compute-btn note';
      noteBtn.textContent = 'Note';
      actions.appendChild(noteBtn);

      orderCompute.appendChild(actions);

      // interactive area: discount choices and note input
      const interactiveWrap = document.createElement('div');
      interactiveWrap.style.marginBottom = '8px';

      const discountPanel = document.createElement('div');
      discountPanel.style.display = 'none';
      discountPanel.style.gap = '8px';
      discountPanel.style.marginBottom = '8px';

      Object.keys(DISCOUNT_TYPES).forEach(type => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'compute-btn';
        btn.textContent = `${type} ${DISCOUNT_TYPES[type] > 0 ? `(${(DISCOUNT_TYPES[type]*100).toFixed(0)}%)` : ''}`;
        btn.style.marginRight = '6px';
        if (type === discountType) {
          btn.classList.add('active');
        }
        btn.addEventListener('click', () => {
          discountType = type;
          discountRate = DISCOUNT_TYPES[type];
          Array.from(discountPanel.children).forEach(c => c.classList.remove('active'));
          btn.classList.add('active');
          renderOrder();
        });
        discountPanel.appendChild(btn);
      });

      const noteInput = document.createElement('textarea');
      noteInput.value = noteValue || '';
      noteInput.placeholder = 'Order note...';
      noteInput.style.width = '100%';
      noteInput.style.minHeight = '48px';
      noteInput.style.borderRadius = '6px';
      noteInput.style.border = '1px solid #ccc';
      noteInput.addEventListener('input', () => { noteValue = noteInput.value; });

      discountPanel.style.display = 'none';
      noteInput.style.display = 'none';

      interactiveWrap.appendChild(discountPanel);
      interactiveWrap.appendChild(noteInput);
      orderCompute.appendChild(interactiveWrap);

      // Toggle handlers
      discountBtn.addEventListener('click', () => {
        discountPanel.style.display = discountPanel.style.display === 'none' ? 'flex' : 'none';
        noteInput.style.display = 'none';
      });
      noteBtn.addEventListener('click', () => {
        noteInput.style.display = noteInput.style.display === 'none' ? 'block' : 'none';
        discountPanel.style.display = 'none';
      });

      // numeric rows
      function makeRow(label, value, isTotal=false) {
        const r = document.createElement('div');
        r.className = 'compute-row' + (isTotal ? ' total' : '');
        const l = document.createElement('div'); l.className='label'; l.textContent = label;
        const v = document.createElement('div'); v.className='value';
        // show converted amounts for numeric rows too
        if (window.APP_SETTINGS.currency && window.APP_SETTINGS.currency !== 'PHP') {
          v.textContent = formatCurrencyValue(convertAmountPHP(Number(value), window.APP_SETTINGS.currency), window.APP_SETTINGS.currency);
        } else {
          v.textContent = (CURRENCY_SYMBOLS.PHP) + Number(value).toFixed(2);
        }
        r.appendChild(l); r.appendChild(v);
        return r;
      }

      orderCompute.appendChild(makeRow('Subtotal', nums.subtotal));
      orderCompute.appendChild(makeRow('Service Charge', nums.serviceCharge));
      orderCompute.appendChild(makeRow('Tax', nums.tax));
      orderCompute.appendChild(makeRow(`Discount (${discountType})`, nums.discountAmount));
      orderCompute.appendChild(makeRow('Payable Amount', nums.payable, true));

      if (nums.tablePrice > 0) {
        orderCompute.appendChild(makeRow('Reserved Table', nums.tablePrice));
      }
      // IMPORTANT: do not create inline Proceed button here anymore.
      if (!billOutBtn || !proceedBtnPage) {
        const fallback = document.createElement('div');
        fallback.className = 'order-buttons fallback';
        if (!billOutBtn) {
          const b = document.createElement('button');
          b.id = 'billOutBtn_fallback';
          b.className = 'hold-btn';
          b.textContent = 'Bill Out';
          b.addEventListener('click', handleBillOut);
          fallback.appendChild(b);
        }
        if (!proceedBtnPage) {
          const p = document.createElement('button');
          p.id = 'proceedBtn_fallback';
          p.className = 'proceed-btn';
          p.textContent = 'Proceed';
          p.addEventListener('click', handleProceed);
          fallback.appendChild(p);
        }
        orderCompute.appendChild(fallback);
      }
    }

    // ---------- DRAFTS ----------
    function getLocalDrafts() {
      try {
        const raw = localStorage.getItem('local_drafts') || '[]';
        const arr = JSON.parse(raw);
        if (Array.isArray(arr)) return arr;
        return [];
      } catch (e) {
        console.error('Failed to parse local_drafts', e);
        return [];
      }
    }
    function saveLocalDrafts(arr) {
      localStorage.setItem('local_drafts', JSON.stringify(arr || []));
    }

    function openDraftsModal() {
      if (!draftModal || !draftModalContent) return;
      draftModalContent.innerHTML = '';
      const closeBtn = document.createElement('button');
      closeBtn.className = 'close-btn';
      closeBtn.id = 'closeDraftModal_js';
      closeBtn.setAttribute('aria-label', 'Close dialog');
      closeBtn.innerHTML = '&times;';
      closeBtn.addEventListener('click', () => draftModal.classList.add('hidden'));
      draftModalContent.appendChild(closeBtn);

      const h3 = document.createElement('h3'); h3.textContent = 'Drafts'; draftModalContent.appendChild(h3);

      const listWrap = document.createElement('div');
      listWrap.style.maxHeight = '320px'; listWrap.style.overflowY = 'auto'; listWrap.style.marginBottom = '10px';
      listWrap.id = 'draftList'; draftModalContent.appendChild(listWrap);

      const newLabel = document.createElement('div'); newLabel.style.margin = '6px 0';
      newLabel.textContent = 'Save current order as draft'; draftModalContent.appendChild(newLabel);

      const draftNameInputNew = document.createElement('input');
      draftNameInputNew.type = 'text';
      draftNameInputNew.id = 'draftNameInput_js';
      draftNameInputNew.placeholder = 'Draft name or note...';
      draftNameInputNew.style.width = '95%';
      draftNameInputNew.style.marginBottom = '8px';
      draftModalContent.appendChild(draftNameInputNew);

      const saveDraftBtnNew = document.createElement('button');
      saveDraftBtnNew.id = 'saveDraftBtn_js';
      saveDraftBtnNew.type = 'button';
      saveDraftBtnNew.textContent = 'Save Draft';
      saveDraftBtnNew.style.padding = '6px 24px';
      saveDraftBtnNew.style.fontSize = '16px';
      saveDraftBtnNew.style.background = '#d51ecb';
      saveDraftBtnNew.style.color = '#fff';
      saveDraftBtnNew.style.border = 'none';
      saveDraftBtnNew.style.borderRadius = '7px';
      saveDraftBtnNew.style.cursor = 'pointer';
      draftModalContent.appendChild(saveDraftBtnNew);

      function refreshDraftList() {
        listWrap.innerHTML = '';
        const drafts = getLocalDrafts();
        if (drafts.length === 0) {
          const p = document.createElement('div'); p.style.color = '#666'; p.textContent = 'No drafts saved.'; listWrap.appendChild(p); return;
        }
        drafts.forEach((d, i) => {
          const row = document.createElement('div');
          row.style.display = 'flex';
          row.style.justifyContent = 'space-between';
          row.style.alignItems = 'center';
          row.style.padding = '8px';
          row.style.borderBottom = '1px solid #eee';

          const left = document.createElement('div'); left.style.flex = '1';
          const name = document.createElement('div'); name.textContent = d.name || ('Draft ' + (i+1)); name.style.fontWeight = '600';
          const meta = document.createElement('div'); meta.textContent = d.created ? new Date(d.created).toLocaleString() : ''; meta.style.fontSize = '12px'; meta.style.color = '#666';
          left.appendChild(name); left.appendChild(meta);

          const actions = document.createElement('div'); actions.style.display = 'flex'; actions.style.gap = '6px';
          const loadBtn = document.createElement('button'); loadBtn.type = 'button'; loadBtn.textContent = 'Load'; loadBtn.style.padding = '6px 10px'; loadBtn.style.cursor = 'pointer';
          loadBtn.addEventListener('click', () => {
            order = Array.isArray(d.order) ? JSON.parse(JSON.stringify(d.order)) : [];
            discountType = d.discountType || 'Regular';
            discountRate = DISCOUNT_TYPES[discountType] || 0;
            noteValue = d.note || '';
            draftModal.classList.add('hidden');
            setActiveCategory(currentCategory);
            renderProducts();
            renderOrder();
          });
          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.textContent = 'Delete';
          delBtn.style.padding = '6px 10px';
          delBtn.style.cursor = 'pointer';
          delBtn.style.background = '#fff';
          delBtn.style.border = '1px solid #ccc';
          delBtn.style.borderRadius = '6px';
          delBtn.addEventListener('click', () => {
            const arr = getLocalDrafts();
            arr.splice(i, 1);
            saveLocalDrafts(arr);
            refreshDraftList();
          });
          actions.appendChild(loadBtn); actions.appendChild(delBtn);

          row.appendChild(left); row.appendChild(actions); listWrap.appendChild(row);
        });
      }

      refreshDraftList();
      saveDraftBtnNew.addEventListener('click', () => {
        const name = (draftNameInputNew.value || '').trim() || ('Draft ' + new Date().toLocaleString());
        const payload = { name, order: JSON.parse(JSON.stringify(order || [])), discountType, discountRate, note: noteValue, created: new Date().toISOString() };
        const arr = getLocalDrafts(); arr.push(payload); saveLocalDrafts(arr);
        alert('Draft saved locally.'); draftNameInputNew.value = ''; refreshDraftList();
      });

      draftModal.classList.remove('hidden');
    }

    if (draftBtn) draftBtn.addEventListener('click', () => openDraftsModal());
    if (closeDraftModalFallback) closeDraftModalFallback.addEventListener('click', () => draftModal.classList.add('hidden'));

    // ---------- OTHER UI HANDLERS ----------
    if (newOrderBtn) newOrderBtn.addEventListener('click', () => {
      if (confirm('Clear current order and start a new one?')) {
        order = []; discountRate = DISCOUNT_TYPES['Regular']; discountType = 'Regular'; noteValue = ''; renderOrder();
      }
    });

    if (refreshBtn) refreshBtn.addEventListener('click', async () => {
      await loadProducts(); order = []; discountRate = DISCOUNT_TYPES['Regular']; discountType = 'Regular'; noteValue = ''; renderOrder();
    });

    // Hook the page-level proceed button (single canonical handler)
    if (proceedBtnPage) {
      proceedBtnPage.addEventListener('click', async () => { await handleProceed(); });
    }

    // Hook the page-level bill out button (single canonical handler)
    if (billOutBtn) {
      billOutBtn.addEventListener('click', (e) => { e.preventDefault(); handleBillOut(); });
    }

    // ---------- Bill Out (print without DB changes) ----------
    function handleBillOut() {
      if (order.length === 0) { alert('Cart is empty.'); return; }
      const w = window.open('', '_blank', 'width=800,height=900');
      if (!w) { alert('Please allow popups for printing.'); return; }
      const form = document.createElement('form');
      form.method = 'POST'; form.action = '../clubtryara/php/print_receipt.php'; form.target = w.name;
      const input = document.createElement('input'); input.type = 'hidden'; input.name = 'cart'; input.value = JSON.stringify(order); form.appendChild(input);
      const totals = computeNumbers();
      const totalsInput = document.createElement('input');
      totalsInput.type = 'hidden';
      totalsInput.name = 'totals';
      totalsInput.value = JSON.stringify(totals);
      form.appendChild(totalsInput);
      document.body.appendChild(form); form.submit(); document.body.removeChild(form);
    }

    // ---------- Proceed (update DB stock) ----------
    async function handleProceed() {
      if (order.length === 0) { alert('No items to proceed.'); return; }
      if (!confirm('Proceed with this order and update stock?')) return;
      if (billOutBtn) billOutBtn.disabled = true; if (proceedBtnPage) proceedBtnPage.disabled = true;
      try {
        const payload = order.map(i => ({ id: i.id, qty: i.qty }));
        const res = await fetch('api/update_stock.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ items: payload }) });
        if (!res.ok) throw new Error('Network response was not OK');
        const body = await res.json();
        if (body.success) {
          alert('Stock updated successfully.');
          order = []; await loadProducts(); renderOrder();
        } else {
          if (body.errors && body.errors.length) alert('Some items could not be processed:\n' + body.errors.join('\n'));
          else alert('Could not update stock: ' + (body.message || 'Unknown error'));
        }
      } catch (err) {
        console.error(err); alert('Error while updating stock: ' + (err.message || err));
      } finally {
        if (billOutBtn) billOutBtn.disabled = false; if (proceedBtnPage) proceedBtnPage.disabled = false;
      }
    }

    // Escape closes modal
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && draftModal && !draftModal.classList.contains('hidden')) draftModal.classList.add('hidden');
    });

    // Search input
    if (searchBox) {
      let to;
      searchBox.addEventListener('input', () => {
        clearTimeout(to);
        to = setTimeout(() => { renderProducts(); }, 180);
      });
    }

    // initial load
    await loadProducts();
  })();
});