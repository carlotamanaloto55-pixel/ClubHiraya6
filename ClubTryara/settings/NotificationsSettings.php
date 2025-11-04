<?php
function renderNotificationsSettings() {
    // ensure session present (Settings.php calls session_start)
    $sound = isset($_SESSION['notify_sound']) ? $_SESSION['notify_sound'] : false;
    $orderAlerts = isset($_SESSION['notify_order']) ? $_SESSION['notify_order'] : false;
    $lowStock = isset($_SESSION['notify_low_stock']) ? $_SESSION['notify_low_stock'] : false;

    // Handle POST (PRG) - only when the notifications form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'notifications') {
        $_SESSION['notify_sound'] = isset($_POST['notify_sound']);
        $_SESSION['notify_order'] = isset($_POST['notify_order']);
        $_SESSION['notify_low_stock'] = isset($_POST['notify_low_stock']);

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Build checked attributes
    $soundChecked = $sound ? 'checked' : '';
    $orderChecked = $orderAlerts ? 'checked' : '';
    $lowStockChecked = $lowStock ? 'checked' : '';

    echo '
    <div>
        <h2>Notifications</h2>
        <form method="POST" class="notifications-form" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" name="form" value="notifications">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div style="font-weight:700;">Enable Sound</div>
                <label class="switch">
                    <input type="checkbox" name="notify_sound" '.$soundChecked.' onchange="this.form.submit()">
                    <span class="slider"><span class="switch-on">ON</span><span class="switch-off">OFF</span></span>
                </label>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div style="font-weight:700;">Order Alerts</div>
                <label class="switch">
                    <input type="checkbox" name="notify_order" '.$orderChecked.' onchange="this.form.submit()">
                    <span class="slider"><span class="switch-on">ON</span><span class="switch-off">OFF</span></span>
                </label>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div style="font-weight:700;">Low Stock Alerts</div>
                <label class="switch">
                    <input type="checkbox" name="notify_low_stock" '.$lowStockChecked.' onchange="this.form.submit()">
                    <span class="slider"><span class="switch-on">ON</span><span class="switch-off">OFF</span></span>
                </label>
            </div>
        </form>
    </div>
    ';

    // Expose notification settings to client-side for immediate use by JS
    $s = $sound ? 'true' : 'false';
    $o = $orderAlerts ? 'true' : 'false';
    $l = $lowStock ? 'true' : 'false';
    echo "
    <script>
      window.SERVER_SETTINGS = window.SERVER_SETTINGS || {};
      window.SERVER_SETTINGS.notifications = {
        sound: $s,
        orderAlerts: $o,
        lowStock: $l
      };
    </script>
    ";
}
?>