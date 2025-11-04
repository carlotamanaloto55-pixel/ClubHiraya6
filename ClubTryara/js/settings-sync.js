// settings-sync.js - fetches server session settings and exposes them to the client app.
// Place a <script src="js/settings-sync.js" defer></script> before the main app.js in index.php and settings/Settings.php.

(async function(){
  try {
    const res = await fetch('api/get_settings.php', { cache: 'no-store', credentials: 'same-origin' });
    if (!res.ok) return;
    const s = await res.json();

    // Convert server percentages to decimal rates for client computations
    window.APP_SETTINGS = window.APP_SETTINGS || {};
    window.APP_SETTINGS.taxRate = (Number(s.tax) || 12) / 100;
    window.APP_SETTINGS.serviceRate = (Number(s.service_charge) || 10) / 100;
    window.APP_SETTINGS.notifications = s.notifications || { sound: false, orderAlerts: false, lowStock: false };
    window.APP_SETTINGS.currency = s.currency || 'PHP';

    // Keep localStorage theme in sync with server-side choice to avoid toggles reverting
    try {
      localStorage.setItem('theme', s.dark_mode ? 'dark' : 'light');
    } catch (e) { /* ignore */ }

    // If the page has a body class not matching server preference, align it.
    try {
      if (s.dark_mode) document.body.classList.add('dark-mode');
      else document.body.classList.remove('dark-mode');
    } catch (e) {}

    // Fetch currency exchange rates (base PHP) for USD, EUR, JPY. Use exchangerate.host (no API key) with fallback static rates.
    async function fetchRates() {
      const fallback = { USD: 0.01705, EUR: 0.016, JPY: 2.57 };
      try {
        const r = await fetch('https://api.exchangerate.host/latest?base=PHP&symbols=USD,EUR,JPY');
        if (!r.ok) return fallback;
        const j = await r.json();
        if (j && j.rates) {
          // API returns rates as 1 PHP = X USD etc.
          return {
            USD: Number(j.rates.USD) || fallback.USD,
            EUR: Number(j.rates.EUR) || fallback.EUR,
            JPY: Number(j.rates.JPY) || fallback.JPY
          };
        }
        return fallback;
      } catch (err) {
        return fallback;
      }
    }

    window.APP_SETTINGS.rates = await fetchRates();

  } catch (err) {
    console.warn('settings-sync failed', err);
  }
})();