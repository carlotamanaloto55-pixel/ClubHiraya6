<?php
function renderThemeSettings() {
    // ensure session available (Settings.php already calls session_start)
    $isDark = isset($_SESSION['dark_mode']) ? $_SESSION['dark_mode'] : false;
    $accent = isset($_SESSION['accent_color']) ? $_SESSION['accent_color'] : '#d33fd3'; // default pink/purple gradient key

    // Handle POST (POST-Redirect-GET) - only when the theme form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'theme') {
        // dark_mode checkbox
        $_SESSION['dark_mode'] = isset($_POST['dark_mode']);

        // accent color
        if (isset($_POST['accent_color'])) {
            $_SESSION['accent_color'] = $_POST['accent_color'];
        }

        // redirect to avoid form resubmission
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Build checked states for accent radios
    $pinkChecked = $accent === '#d33fd3' ? 'checked' : '';
    $blueChecked = $accent === '#4b4bff' ? 'checked' : '';
    $grayChecked = $accent === '#bdbdbd' ? 'checked' : '';

    // Output form HTML
    echo '
    <div>
        <h2>Theme Settings</h2>
        <form method="POST" class="theme-form" style="display:flex;flex-direction:column;gap:16px;">
            <input type="hidden" name="form" value="theme">
            <div class="theme-switch-row">
                <span class="theme-label">Dark Mode</span>
                <label class="switch">
                    <input type="checkbox" name="dark_mode" '.($isDark ? 'checked' : '').' onchange="this.form.submit()">
                    <span class="slider">
                        <span class="switch-on">ON</span>
                        <span class="switch-off">OFF</span>
                    </span>
                </label>
            </div>

            <div>
                <label style="font-weight:bold;display:block;margin-bottom:8px;">Sidebar Color</label>
                <div style="display:flex;align-items:center;gap:12px;">
                    <label title="Pink / Purple">
                        <input type="radio" name="accent_color" value="#d33fd3" '.$pinkChecked.' onchange="this.form.submit()" style="display:none;">
                        <span class="accent-swatch swatch-pink" aria-hidden="true"></span>
                    </label>

                    <label title="Blue">
                        <input type="radio" name="accent_color" value="#4b4bff" '.$blueChecked.' onchange="this.form.submit()" style="display:none;">
                        <span class="accent-swatch swatch-blue" aria-hidden="true"></span>
                    </label>

                    <label title="Gray">
                        <input type="radio" name="accent_color" value="#bdbdbd" '.$grayChecked.' onchange="this.form.submit()" style="display:none;">
                        <span class="accent-swatch swatch-gray" aria-hidden="true"></span>
                    </label>
                </div>
            </div>
        </form>
    </div>
    ';

    // Immediately apply the accent color for this settings page and sync server theme to localStorage.
    $accentValue = htmlspecialchars($accent, ENT_QUOTES);
    $themeValue = $isDark ? 'dark' : 'light';

    echo '
    <script>
      (function(){
        try {
          var accent = "'.$accentValue.'";
          var gradientMap = {
            "#d33fd3": ["#d33fd3", "#a2058f"],
            "#4b4bff": ["#4b4bff", "#001b89"],
            "#bdbdbd": ["#bdbdbd", "#7a7a7a"]
          };
          var g = gradientMap[accent] || gradientMap["#d33fd3"];
          document.documentElement.style.setProperty("--accent-start", g[0]);
          document.documentElement.style.setProperty("--accent-end", g[1]);

          // Persist the server-selected theme to localStorage so client JS will remain in sync.
          try {
            localStorage.setItem("theme", "'.$themeValue.'");
            window.SERVER_THEME = "'.$themeValue.'";
          } catch(e) { /* ignore localStorage errors */ }
        } catch(e) { console.error(e) }
      })();
    </script>
    ';
}
?>