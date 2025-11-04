<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Tryara</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/tables-select.css">

    <!-- Run settings-sync BEFORE the main app so theme and notification flags from the server are applied first -->
    <script defer src="js/settings-sync.js"></script>
    <script defer src="js/app.js"></script>
    <script defer src="js/tables-select.js"></script>
</head>
<body<?php if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo ' class="dark-mode"'; ?>>

    <noscript>
        <div class="noscript-warning">This app requires JavaScript to function correctly. Please enable JavaScript.</div>
    </noscript>

    <!-- Sidebar -->
    <aside class="sidebar" role="complementary" aria-label="Sidebar">
        <div class="sidebar-header">
            <img src="../clubtryara/assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
        </div>

        <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
            <a href="php/home.php" class="sidebar-btn active" aria-current="page">
                <span class="sidebar-icon"><img src="../clubtryara/assets/logos/home.png" alt="Home icon"></span>
                <span>Home</span>
            </a>
            <a href="../ClubTryara/tables/tables.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../clubtryara/assets/logos/table.png" alt="Tables icon"></span>
                <span>Tables</span>
            </a>
            <a href="../clubtryara/inventory/inventory.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../clubtryara/assets/logos/inventory.png" alt="Inventory icon"></span>
                <span>Inventory</span>
            </a>
            <a href="php/sales_report.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../clubtryara/assets/logos/sales.png" alt="Sales report icon"></span>
                <span>Sales Report</span>
            </a>
            <a href="../clubtryara/settings/settings.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../clubtryara/assets/logos/setting.png" alt="Settings icon"></span>
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
        <!-- Top Bar -->
        <div class="topbar">
            <div class="search-section">
                <input type="text" class="search-input" placeholder="Search products" id="searchBox" aria-label="Search products">
            </div>
            <button class="select-table-btn" type="button" aria-haspopup="dialog">
                Select Table <span class="table-icon"><img src="../clubtryara/assets/logos/table.png" alt="Table icon"></span>
            </button>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Products Section -->
            <section class="products-section" aria-label="Products">
                <div class="category-tabs" id="categoryTabs" role="tablist" aria-label="Categories">
                    <!-- JS will populate category buttons here -->
                </div>

                <div class="foods-grid" id="foodsGrid" aria-live="polite">
                    <!-- Foods will be loaded dynamically from MySQL by app.js -->
                </div>
            </section>

            <!-- Order Section -->
            <aside class="order-section" aria-label="Order">
                <div class="order-actions">
                    <button class="order-action-btn plus" id="newOrderBtn" type="button" title="New order">+</button>
                    <button class="order-action-btn draft" id="draftBtn" type="button" title="Save draft"><img src="../clubtryara/assets/logos/draft.png" alt="Draft"></button>
                    <button class="order-action-btn refresh" id="refreshBtn" type="button" title="Reset order"><img src="../clubtryara/assets/logos/reset.png" alt="Reset"></button>
                </div>

                <div class="order-list" id="orderList" aria-live="polite"></div>

                <div class="order-compute" id="orderCompute" aria-live="polite">
                    <!-- Reserved table UI will be injected here by tables-select.js -->
                </div>

                <div class="order-buttons">
                    <button class="hold-btn" id="billOutBtn" type="button">Bill Out</button>
                    <button class="proceed-btn" id="proceedBtn" type="button">Proceed</button>
                </div>
            </aside>
        </div>
    </main>

    <!-- Draft Modal -->
    <div class="modal hidden" id="draftModal" role="dialog" aria-modal="true" aria-labelledby="draftModalTitle" tabindex="-1">
        <div class="modal-content" role="document">
            <button class="close-btn" id="closeDraftModal" aria-label="Close dialog">&times;</button>
            <h3 id="draftModalTitle">Save Current Order to Draft</h3>
            <input type="text" id="draftNameInput" placeholder="Draft name or note..." style="width:95%;margin-bottom:12px;" aria-label="Draft name">
            <div style="text-align:right;">
                <button id="saveDraftBtn" type="button" style="padding:6px 24px;font-size:16px;background:#d51ecb;color:#fff;border:none;border-radius:7px;">Save Draft</button>
            </div>
        </div>
    </div>

    <!-- Reserved Tables Modal -->
    <div class="modal hidden" id="tablesModal" role="dialog" aria-modal="true" aria-labelledby="tablesModalTitle" tabindex="-1">
        <div class="modal-content" role="document" style="max-width:900px;">
            <button class="close-btn" id="closeTablesModal" aria-label="Close dialog">&times;</button>
            <h3 id="tablesModalTitle">Reserved Tables</h3>

            <div id="tables-loading" style="padding:12px 0;">Loading...</div>

            <div id="tables-empty" style="display:none;padding:12px 0;">No reserved tables found.</div>

            <div style="overflow:auto;max-height:320px;">
                <table class="table" id="tables-list" style="width:100%;border-collapse:collapse;display:none;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:6px;">Name</th>
                            <th style="text-align:left;padding:6px;">Table #</th>
                            <th style="text-align:left;padding:6px;">Party size</th>
                            <th style="text-align:left;padding:6px;">Status</th>
                            <th style="text-align:right;padding:6px;">Price</th>
                            <th style="padding:6px;"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>