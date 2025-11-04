<?php
// Settings page - centralized POST handling to avoid "headers already sent" warnings.
// Start session and process form POSTs BEFORE any output so header() redirects work.
session_start();

// Centralized POST handling (POST-Redirect-GET)
// Each settings form includes a hidden input `form` with values: theme, system, notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form'])) {
    $form = $_POST['form'];

    if ($form === 'theme') {
        // Dark mode checkbox and accent color
        $_SESSION['dark_mode'] = isset($_POST['dark_mode']);
        if (isset($_POST['accent_color'])) {
            $_SESSION['accent_color'] = $_POST['accent_color'];
        }
    } elseif ($form === 'system') {
        if (isset($_POST['currency'])) $_SESSION['currency'] = $_POST['currency'];
        if (isset($_POST['tax'])) $_SESSION['tax'] = $_POST['tax'];
        if (isset($_POST['service_charge'])) $_SESSION['service_charge'] = $_POST['service_charge'];
    } elseif ($form === 'notifications') {
        $_SESSION['notify_sound'] = isset($_POST['notify_sound']);
        $_SESSION['notify_order'] = isset($_POST['notify_order']);
        $_SESSION['notify_low_stock'] = isset($_POST['notify_low_stock']);
    }

    // PRG: redirect back to this page to avoid form re-submissions and ensure subsequent output is fresh
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Include render functions (these should only output HTML — POSTs handled above)
include 'ThemeSettings.php';
include 'SystemSettings.php';
include 'NotificationsSettings.php';
include 'BackupRestore.php';
include 'ChangePassword.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Tryara</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Styles -->
    <!-- settings/ is one level down from ClubTryara/, so go up one level to reach the shared css -->
    <link rel="stylesheet" href="../css/style.css">

    <!-- Load only the small settings-sync script here (it will sync server session -> client).
         Do NOT load the full POS app.js on this page. -->
    <script defer src="../js/settings-sync.js"></script>
</head>
<body<?php if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo ' class="dark-mode"'; ?>>
    <noscript>
        <div class="noscript-warning">This app requires JavaScript to function correctly. Please enable JavaScript.</div>
    </noscript>

    <!-- Sidebar -->
    <aside class="sidebar" role="complementary" aria-label="Sidebar">
        <div class="sidebar-header">
            <img src="../../clubtryara/assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
        </div>

        <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
            <a href="../index.php" class="sidebar-btn" aria-current="page">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/home.png" alt="Home icon"></span>
                <span>Home</span>
            </a>
            <a href="../../ClubTryara/tables/tables.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/table.png" alt="Tables icon"></span>
                <span>Tables</span>
            </a>
            <a href="../inventory/inventory.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/inventory.png" alt="Inventory icon"></span>
                <span>Inventory</span>
            </a>
            <a href="../php/sales_report.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Sales report icon"></span>
                <span>Sales Report</span>
            </a>
            <a href="../settings/settings.php" class="sidebar-btn active">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/setting.png" alt="Settings icon"></span>
                <span>Settings</span>
            </a>
        </nav>

        
        <div style="flex:1" aria-hidden="true"></div>

        <button class="sidebar-logout" type="button" aria-label="Logout">
            <span>Logout</span>
        </button>
    </aside>

    <!-- Main Content -->
    <main class="main-content" role="main" aria-label="Main content">

<link rel="stylesheet" href="settings.css">

<div class="settings-container">
    <div class="settings-box">
        <?php renderChangePassword(); ?>
    </div>
    <div class="settings-row">
        <div class="settings-box"><?php renderThemeSettings(); ?></div>
        <div class="settings-box"><?php renderSystemSettings(); ?></div>
    </div>
    <div class="settings-row">
        <div class="settings-box"><?php renderNotificationsSettings(); ?></div>
        <div class="settings-box"><?php renderBackupRestore(); ?></div>
    </div>
</div>
</main>
</body>
</html>