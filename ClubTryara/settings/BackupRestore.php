<?php
function renderBackupRestore() {
    echo '
    <div class="backup">
        <h2>Backup / Restore Sales Report</h2>

        <form method="POST" action="../salesreport/export_sales.php" style="margin-bottom:12px;">
            <button type="submit" name="export" class="export-btn">Export Sales Report (CSV)</button>
        </form>

        <form method="POST" action="../salesreport/import_sales.php" enctype="multipart/form-data">
            <label style="display:block;margin-bottom:8px;">Upload Sales Backup (CSV)</label>
            <input type="file" name="sales_csv" accept=".csv" required>
            <br><br>
            <button type="submit" class="import-btn">Import Sales Backup</button>
        </form>
    </div>
    ';
}
?>