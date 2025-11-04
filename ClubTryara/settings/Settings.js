// SETTINGS MODULE FOR CLUBTRYARA

// Default settings
const DEFAULT_SETTINGS = {
  theme: 'light', // 'light' or 'dark'
  accentColor: '#4b4bce', // default
  notifications: {
    sound: false,
    orderAlerts: false,
    lowStockAlerts: false,
  },
  currency: 'PHP',
  tax: 12,
  serviceCharge: 10,
};

// Supported Currencies & Conversion Example
const CURRENCY_SYMBOLS = {
  PHP: '₱',
  USD: '$',
  EUR: '€',
  JPY: '¥',
};

const CURRENCY_RATES = {
  PHP: 1,
  USD: 0.017, // 1 PHP ≈ 0.017 USD
  EUR: 0.016, // 1 PHP ≈ 0.016 EUR
  JPY: 2.57,  // 1 PHP ≈ 2.57 JPY
};

// Save settings to localStorage
function saveSettings(settings) {
  localStorage.setItem('appSettings', JSON.stringify(settings));
}

// Load settings from localStorage
function loadSettings() {
  const settings = localStorage.getItem('appSettings');
  return settings ? JSON.parse(settings) : { ...DEFAULT_SETTINGS };
}

// Toggle Dark/Light Mode
function setTheme(mode) {
  const root = document.documentElement;
  if (mode === 'dark') {
    root.style.setProperty('--bg-color', '#222');
    root.style.setProperty('--panel-color', '#888');
    root.style.setProperty('--text-color', '#fff');
  } else {
    root.style.setProperty('--bg-color', '#eee');
    root.style.setProperty('--panel-color', '#ccc');
    root.style.setProperty('--text-color', '#222');
  }
  const settings = loadSettings();
  settings.theme = mode;
  saveSettings(settings);
}

// Set Accent Color
function setAccentColor(color) {
  document.documentElement.style.setProperty('--accent-color', color);
  const settings = loadSettings();
  settings.accentColor = color;
  saveSettings(settings);
}

// Notification Toggles
function setNotification(type, value) {
  const settings = loadSettings();
  settings.notifications[type] = value;
  saveSettings(settings);
}

// Currency Conversion (Real-time via API, fallback to static rates)
async function convertCurrency(amountPHP, toCurrency) {
  if (toCurrency === 'PHP') return amountPHP;

  // Try fetch real rates
  try {
    const res = await fetch(`https://api.exchangerate-api.com/v4/latest/PHP`);
    const data = await res.json();
    const rate = data.rates[toCurrency] || CURRENCY_RATES[toCurrency];
    return amountPHP * rate;
  } catch (e) {
    // Fallback to static rates
    return amountPHP * (CURRENCY_RATES[toCurrency] || 1);
  }
}

// Format currency
function formatCurrency(amount, currency) {
  return `${CURRENCY_SYMBOLS[currency]}${amount.toFixed(2)}`;
}

// Currency Setting
function setCurrency(currency) {
  const settings = loadSettings();
  settings.currency = currency;
  saveSettings(settings);
  // You should trigger price update in UI after this
}

// Tax & Service Charge Setting
function setSystemSetting(setting, value) {
  const settings = loadSettings();
  settings[setting] = value;
  saveSettings(settings);
}

// Backup & Restore
function backupSettings() {
  const settings = loadSettings();
  return JSON.stringify(settings);
}

function restoreSettings(settingsString) {
  try {
    const settings = JSON.parse(settingsString);
    saveSettings(settings);
    // Optionally refresh UI after restore
  } catch (e) {
    alert('Invalid backup data!');
  }
}

// Export functions for use in other files
export {
  loadSettings,
  saveSettings,
  setTheme,
  setAccentColor,
  setNotification,
  convertCurrency,
  formatCurrency,
  setCurrency,
  setSystemSetting,
  backupSettings,
  restoreSettings,
  DEFAULT_SETTINGS,
  CURRENCY_SYMBOLS,
};


// On page load, apply saved theme:
document.addEventListener('DOMContentLoaded', function() {
  const theme = localStorage.getItem('theme') || 'light';
  setTheme(theme);
});

// Example toggle (you need to connect this to your UI dark mode switch):
// document.getElementById('darkModeToggle').addEventListener('change', function(e) {
//   setTheme(e.target.checked ? 'dark' : 'light');
// });
// --- DARK MODE ---
function setTheme(mode) {
  if (mode === 'dark') {
    document.body.classList.add('dark-mode');
    localStorage.setItem('theme', 'dark');
  } else {
    document.body.classList.remove('dark-mode');
    localStorage.setItem('theme', 'light');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  // Apply saved theme
  setTheme(localStorage.getItem('theme') || 'light');
  // Listen for dark mode toggle
  const darkToggle = document.getElementById('darkModeToggle');
  if (darkToggle) {
    darkToggle.addEventListener('change', e => {
      setTheme(e.target.checked ? 'dark' : 'light');
    });
  }

  // --- CURRENCY ---
  const currencySelect = document.getElementById('currencySelect');
  if (currencySelect) {
    currencySelect.addEventListener('change', async function(e) {
      const currency = e.target.value;
      localStorage.setItem('currency', currency);
      await updatePrices(currency);
    });
    // Set initial currency on load
    const savedCurrency = localStorage.getItem('currency') || 'PHP';
    currencySelect.value = savedCurrency;
    updatePrices(savedCurrency);
  }
});

// --- PRICE CONVERSION ---
const currencyRates = {
  PHP: 1,
  USD: 0.01705, // 1 PHP = 0.01705 USD
  EUR: 0.016,   // Example
  JPY: 2.57,    // Example
};

const currencySymbols = {
  PHP: '₱',
  USD: '$',
  EUR: '€',
  JPY: '¥',
};

async function updatePrices(toCurrency) {
  // If you want live rates, fetch from API here
  // Example: use static rates
  document.querySelectorAll('[data-price-php]').forEach(el => {
    const php = parseFloat(el.getAttribute('data-price-php'));
    const rate = currencyRates[toCurrency] || 1;
    const symbol = currencySymbols[toCurrency] || '₱';
    el.textContent = symbol + (php * rate).toFixed(2);
  });
}