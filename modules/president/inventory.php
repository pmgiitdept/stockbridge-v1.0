<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['president', 'operations_manager', 'purchasing_officer']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";

$selectedMonth = $_GET['month'] ?? date('Y-m');
$itemFilter = $_GET['item'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$month = $selectedMonth;

$where = "WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
$params = [$selectedMonth];
$types = "s";

if ($itemFilter !== '') {
    $where .= " AND item_name LIKE ?";
    $params[] = "%$itemFilter%";
    $types .= "s";
}

if ($categoryFilter !== '') {
    $where .= " AND category = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}

$summaryInventorySql = "
    SELECT 
        item_code,
        item_name,
        category,
        SUM(IF(movement_type='IN', quantity, 0)) AS total_in,
        SUM(IF(movement_type='OUT', quantity, 0)) AS total_out
    FROM inventory_movements
    $where
    GROUP BY item_code, item_name, category
";

$stmt1 = $conn->prepare($summaryInventorySql);
if (!$stmt1) {
    die("Inventory Summary Error: " . $conn->error);
}

$stmt1->bind_param($types, ...$params);
$stmt1->execute();
$res1 = $stmt1->get_result();

$summaryPrSql = "
    SELECT 
        pi.item_description AS item_name,
        'Supply' AS category,
        0 AS total_in,
        SUM(pi.quantity) AS total_out
    FROM pr_items pi
    JOIN pr_forms pf ON pi.pr_id = pf.pr_id
    WHERE pf.approved_at IS NOT NULL
";

if ($selectedMonth !== '') {
    $summaryPrSql .= " AND DATE_FORMAT(pf.approved_at, '%Y-%m') = '$selectedMonth'";
}

if ($itemFilter !== '') {
    $summaryPrSql .= " AND pi.item_description LIKE '%$itemFilter%'";
}

$summaryPrSql .= " GROUP BY pi.item_description";

$res2 = $conn->query($summaryPrSql);

if (!$res2) {
    die("PR Summary Error: " . $conn->error);
}

$summaryData = [];

while ($row = $res1->fetch_assoc()) {
    $key = $row['item_code'];
    $summaryData[$key] = $row;
}

while ($row = $res2->fetch_assoc()) {
    $key = $row['item_name'];

    if (!isset($summaryData[$key])) {
        $summaryData[$key] = $row;
        $summaryData[$key]['item_code'] = '';
    } else {
        $summaryData[$key]['total_out'] += $row['total_out'];
    }
}

$stmt2 = $conn->prepare("
    SELECT 
        id,
        item_code,
        item_name,
        category,
        quantity,
        movement_type,
        reference_type,
        reference_id,
        created_at
    FROM inventory_movements
    $where
    ORDER BY created_at DESC
");

if (!$stmt2) {
    die("Inventory Movements Error: " . $conn->error);
}

$stmt2->bind_param($types, ...$params);
$stmt2->execute();
$resInventory = $stmt2->get_result();

$historyPrSql = "
    SELECT 
        pi.id,
        '' AS item_code,
        pi.item_description AS item_name,
        'Supply' AS category,
        pi.quantity,
        'OUT' AS movement_type,
        'PR' AS reference_type,
        pf.pr_id AS reference_id,
        pf.approved_at AS created_at
    FROM pr_items pi
    JOIN pr_forms pf ON pi.pr_id = pf.pr_id
    WHERE pf.approved_at IS NOT NULL
";

if ($selectedMonth !== '') {
    $historyPrSql .= " AND DATE_FORMAT(pf.approved_at, '%Y-%m') = '$selectedMonth'";
}

if ($itemFilter !== '') {
    $historyPrSql .= " AND pi.item_description LIKE '%$itemFilter%'";
}

$historyPrSql .= " ORDER BY pf.approved_at DESC";

$resPR = $conn->query($historyPrSql);

if (!$resPR) {
    die("PR History Error: " . $conn->error);
}

$historyData = [];

while ($row = $resInventory->fetch_assoc()) {
    $historyData[] = $row;
}

while ($row = $resPR->fetch_assoc()) {
    $historyData[] = $row;
}

usort($historyData, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
?>

<link rel="stylesheet" href="/contract_system/assets/css/inventory.css">

<div class="main-content">
    <div class="page-header">
        <h1>Inventory Monitoring</h1>
        <p class="page-subtitle">
            Track stock movement, monitor supply usage, and review supplier invoices. 
            Use filters to analyze inventory activity and detect shortages or overstock.
        </p>
    </div>

    <div class="inventory-card">
        <div class="inventory-header">
        <h2>Filter Inventory</h2>

        <div style="display:flex; gap:10px;">
            <button onclick="openModal('addInventoryModal')" class="btn-primary">
                + Add Invoice (Stock Entry)
            </button>

            <button onclick="viewAllAttachments()" class="btn-secondary">
                📎 View Invoice Files
            </button>

            <button onclick="openModal('rebuildModal')" class="btn-secondary">
                🔄 Rebuild Monthly Summary
            </button>
        </div>
    </div>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Month</label>
                <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
            </div>

            <div class="filter-group">
                <label>Item</label>
                <input type="text" name="item" value="<?= htmlspecialchars($itemFilter) ?>">
            </div>

            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All</option>
                    <option value="Supply" <?= $categoryFilter=='Supply'?'selected':'' ?>>Supply</option>
                    <option value="Tool" <?= $categoryFilter=='Tool'?'selected':'' ?>>Tool</option>
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn-primary">Apply</button>
                <a href="inventory.php" class="btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="inventory-card">
        <h2>Overview</h2>

        <div style="display:flex; gap:15px; flex-wrap:wrap;">

            <div class="stat-box">
                <span>Total Items</span>
                <strong><?= count($summaryData) ?></strong>
            </div>

            <div class="stat-box">
                <span>Total IN</span>
                <strong>
                    <?= number_format(array_sum(array_column($summaryData,'total_in')),2) ?>
                </strong>
            </div>

            <div class="stat-box">
                <span>Total OUT</span>
                <strong>
                    <?= number_format(array_sum(array_column($summaryData,'total_out')),2) ?>
                </strong>
            </div>

        </div>
    </div>

    <div class="inventory-card">
        <h2>Stock Summary</h2>
        <p class="section-subtitle">
            Overview of total stock received (IN), issued (OUT), and remaining balance per item.
            Negative values indicate shortages or over-issuance.
        </p>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Total IN</th>
                        <th>Total OUT</th>
                        <th>Remaining</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($summaryData as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['item_code'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td><?= number_format($row['total_in'],2) ?></td>
                        <td><?= number_format($row['total_out'],2) ?></td>
                        <?php 
                        $remaining = $row['total_in'] - $row['total_out'];
                        $remainingClass = $remaining < 0 ? 'negative-stock' : 'positive-stock';
                        ?>

                        <td class="<?= $remainingClass ?>">
                            <strong><?= number_format($remaining, 2) ?></strong>
                            <div style="font-size:10px; color:#6b7280;">
                                <?= $remaining < 0 ? 'Shortage' : 'Available' ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if(empty($summaryData)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:20px; color:#6b7280;">
                        No inventory data found for selected filters.
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="inventory-card">
        <h2>Inventory Movements</h2>
        <p class="section-subtitle">
            Detailed log of all stock entries (IN) and issuances (OUT), including supplier invoices and PR releases.
        </p>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Attachment</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach($historyData as $row): ?>
                    <tr>
                        <td><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><?= number_format($row['quantity'],2) ?></td>
                        <td>
                            <span class="<?= $row['movement_type']=='IN'?'badge-in':'badge-out' ?>">
                                <?= $row['movement_type'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if($row['reference_type'] === 'INVOICE'): ?>
                                <span style="font-weight:600;">Invoice</span><br>
                                <small>#<?= htmlspecialchars($row['reference_id']) ?></small>
                            <?php else: ?>
                                <?= htmlspecialchars($row['reference_type']) ?> <span style="font-weight:600;"></span><br>
                                <small>#<?= htmlspecialchars($row['reference_id']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-primary"
                                onclick="viewAttachments('<?= htmlspecialchars($row['reference_id']) ?>')">
                                View
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if(empty($summaryData)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:20px; color:#6b7280;">
                        No inventory data found for selected filters.
                    </td>
                </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <div class="inventory-card">
        <h2>Monthly Inventory History</h2>
        <p class="section-subtitle">
            Monthly breakdown of stock inflow, usage, and remaining balance carried forward.
        </p>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>IN</th>
                        <th>OUT</th>
                        <th>LEFT</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $histSql = "
                        SELECT 
                            month,
                            item_code,
                            item_name,
                            category,
                            total_in,
                            total_out,
                            ending_balance
                        FROM inventory_monthly_summary
                        WHERE 1=1
                    ";

                    $histParams = [];
                    $histTypes = "";

                    if (!empty($selectedMonth)) {
                        $histSql .= " AND month = ?";
                        $histParams[] = $selectedMonth;
                        $histTypes .= "s";
                    }

                    if (!empty($itemFilter)) {
                        $histSql .= " AND item_name LIKE ?";
                        $histParams[] = "%$itemFilter%";
                        $histTypes .= "s";
                    }

                    if (!empty($categoryFilter)) {
                        $histSql .= " AND category = ?";
                        $histParams[] = $categoryFilter;
                        $histTypes .= "s";
                    }

                    $histSql .= " ORDER BY month DESC, item_name ASC";

                    $histStmt = $conn->prepare($histSql);

                    if (!$histStmt) {
                        die("Monthly Inventory History Error: " . $conn->error);
                    }

                    if (!empty($histParams)) {
                        $histStmt->bind_param($histTypes, ...$histParams);
                    }

                    $histStmt->execute();
                    $histRes = $histStmt->get_result();
                    ?>

                    <?php if ($histRes->num_rows > 0): ?>
                        <?php while($h = $histRes->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($h['month']) ?></td>

                                <td>
                                    <?= htmlspecialchars($h['item_name']) ?>
                                    <div style="font-size:11px; color:#6b7280;">
                                        <?= htmlspecialchars($h['item_code']) ?>
                                    </div>
                                </td>

                                <td><?= htmlspecialchars($h['category']) ?></td>

                                <td><?= number_format($h['total_in'], 2) ?></td>

                                <td><?= number_format($h['total_out'], 2) ?></td>

                                <td>
                                    <strong><?= number_format($h['ending_balance'], 2) ?></strong>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:20px; color:#6b7280;">
                                No monthly inventory data found for selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addInventoryModal" class="custom-modal">
    <div class="modal-content modal-lg">

        <div class="modal-header">
            <h3>📦 Add Supplier Invoice</h3>
            <span class="close-btn" onclick="closeModal('addInventoryModal')">&times;</span>
        </div>

        <form id="addInventoryForm" enctype="multipart/form-data">

            <div class="modal-body">

                <div class="form-box">
                    <label>Invoice No (Reference ID)</label>
                    <input type="text" name="reference_id" placeholder="INV-0001" required>
                </div>

                <div class="form-box">
                    <label>Items</label>

                    <div class="table-wrapper">
                        <table id="itemTable">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th>Unit</th>
                                    <th>Category</th>
                                    <th>Qty</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="itemBody">
                                <tr>
                                    <td><input name="item_code[]" required></td>
                                    <td><input name="item_name[]" required></td>
                                    <td><input name="unit[]" required></td>
                                    <td>
                                        <select name="category[]" required>
                                            <option value="Supply">Supply</option>
                                            <option value="Tool">Tool</option>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" name="quantity[]" required></td>
                                    <td>
                                        <button type="button" class="btn-remove" onclick="removeRow(this)">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn-secondary add-btn" onclick="addRow()">+ Add Item</button>
                </div>

                <div class="form-box">
                    <label>Attachment (Invoice File)</label>
                    <div class="file-upload">
                        <input type="file" name="attachment">
                        <small>Upload PDF, image, or scanned invoice</small>
                    </div>
                </div>

            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addInventoryModal')">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    Save Invoice
                </button>
            </div>

        </form>
    </div>
</div>

<div id="attachmentsModal" class="custom-modal">
    <div class="modal-content">

        <div class="attachments-header">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>📎 Attachments</h3>
                <span class="close-btn" onclick="closeModal('attachmentsModal')">&times;</span>
            </div>
        </div>

        <div class="attachments-body" id="attachmentsList">
        </div>
    </div>
</div>

<div id="fileViewerModal" class="custom-modal">
    <div class="modal-content modal-lg" style="height:85vh; display:flex; flex-direction:column;">

        <div class="modal-header">
            <h3>📄 Attachment Preview</h3>
            <span class="close-btn" onclick="closeFileViewer()">&times;</span>
        </div>

        <div style="flex:1; background:#111; display:flex; align-items:center; justify-content:center;">
            <iframe id="fileViewerFrame" 
                style="width:100%; height:100%; border:none; background:#fff;">
            </iframe>
        </div>

    </div>
</div>

<div id="rebuildModal" class="custom-modal">
    <div class="modal-content">

        <div class="modal-header">
            <h3>🔄 Rebuild Monthly Summary</h3>
            <span class="close-btn" onclick="closeModal('rebuildModal')">&times;</span>
        </div>

        <div class="modal-body" style="padding:20px;">
            <p style="margin-bottom:10px;">
                This will recalculate all monthly inventory data.
            </p>
            <p style="font-size:13px; color:#6b7280;">
                This may take a few seconds depending on your data size.
            </p>
        </div>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="closeModal('rebuildModal')">
                Cancel
            </button>
            <button class="btn-primary" onclick="confirmRebuild()">
                Yes, Rebuild
            </button>
        </div>

    </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
function confirmRebuild(){

    closeModal('rebuildModal');

    const btns = document.querySelectorAll('.btn-primary, .btn-secondary');
    btns.forEach(b => b.disabled = true);

    showToast("Rebuilding monthly summary...", "warning");

    fetch('rebuild_monthly_inventory.php')
    .then(res => res.text())
    .then(msg => {
        showToast("Monthly summary updated!", "success");

        setTimeout(() => location.reload(), 1000);
    })
    .catch(() => {
        showToast("Failed to rebuild summary.", "error");
        btns.forEach(b => b.disabled = false);
    });
}

function openFileViewer(filePath){
    const frame = document.getElementById('fileViewerFrame');
    frame.src = filePath;
    openModal('fileViewerModal');
}

function closeFileViewer(){
    document.getElementById('fileViewerFrame').src = '';
    closeModal('fileViewerModal');
}

function showToast(message, type = 'warning') {
    const container = document.getElementById('toastContainer');

    const toast = document.createElement('div');
    toast.classList.add('toast', `toast-${type}`);
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3600);
}

function addRow() {
    const row = document.createElement("tr");

    row.innerHTML = `
        <td><input name="item_code[]" required></td>
        <td><input name="item_name[]" required></td>
        <td><input name="unit[]" required></td>
        <td>
            <select name="category[]" required>
                <option value="Supply">Supply</option>
                <option value="Tool">Tool</option>
            </select>
        </td>
        <td><input type="number" step="0.01" name="quantity[]" required></td>
        <td class="action-cell">
            <button type="button" class="btn-remove" onclick="removeRow(this)" title="Remove item">
                ✕
            </button>
        </td>
    `;

    document.getElementById("itemBody").appendChild(row);
}

function removeRow(btn) {
    const tbody = document.getElementById("itemBody");

    if (tbody.rows.length <= 1) {
        showToast("At least one item is required.", "warning");
        return;
    }

    const row = btn.closest("tr");

    row.style.transition = "0.2s ease";
    row.style.opacity = "0";
    row.style.transform = "translateX(10px)";

    setTimeout(() => row.remove(), 150);

    showToast("Item removed.", "success");
}

function viewAllAttachments(){

    const params = new URLSearchParams(window.location.search);

    const container = document.getElementById('attachmentsList');

    container.innerHTML = '<p>Loading attachments...</p>';
    openModal('attachmentsModal');

    fetch('get_attachments.php?' + params.toString())
    .then(r=>r.json())
    .then(data=>{

        let html = `
            <div style="margin-bottom:15px;">
                <h3 style="margin:0;">📎 All Uploaded Invoices</h3>
                <small style="color:#6b7280;">Filtered results based on current selection</small>
            </div>
        `;

        if(data.length === 0){
            html += `
                <div style="text-align:center; padding:20px; color:#6b7280;">
                    No attachments found for this filter.
                </div>
            `;
        } else {

            data.forEach(f=>{

                html += `
                    <div class="attachment-item">
                        <div style="display:flex; justify-content:space-between; gap:10px;">

                            <div>
                                <a href="javascript:void(0)" 
                               onclick="openFileViewer('/contract_system/${f.file_path}')"
                                class="attachment-title">
                                    📄 ${f.file_name}
                                </a>

                                <div class="attachment-meta">
                                    Item: <strong>${f.item_name}</strong><br>
                                    Ref #: ${f.reference_id}<br>
                                    Uploaded: ${f.uploaded_at}
                                </div>
                            </div>

                            <div class="attachment-actions">
                                <a href="javascript:void(0)" 
                                onclick="openFileViewer('/contract_system/${f.file_path}')"
                                class="btn-secondary" style="font-size:12px;">
                                    Open
                                </a>
                            </div>

                        </div>
                    </div>
                `;
            });
        }

        container.innerHTML = html;
    })
    .catch(err => {
        container.innerHTML = `
            <div style="color:red;">
                Failed to load attachments.
            </div>
        `;
        showToast("Failed to load attachments.", "error");
        console.error(err);
    });
}

function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }

document.getElementById('addInventoryForm').addEventListener('submit', function(e){
    e.preventDefault();

    let formData = new FormData(this);

    fetch('add_inventory.php', {
        method:'POST',
        body:formData
    })
    .then(r => r.json())
    .then(d => {
        if(d.success){
            showToast("Invoice added successfully!", "success");

            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(d.message || "Failed to save invoice.", "error");
        }
    })
    .catch(() => {
        showToast("Network error. Please try again.", "error");
    });
});

function viewAttachments(refId){
    fetch('get_attachments.php?ref_id='+refId)
    .then(r=>r.json())
    .then(data=>{
        let html = '';

        if(data.length === 0){
            showToast("No attachments found.", "warning");
            html = '<p>No attachments found.</p>';
        } else {
            data.forEach(f=>{
                html += `
                    <div class="attachment-item">
                        <a href="javascript:void(0)" onclick="openFileViewer('/contract_system/${f.file_path}')">${f.file_name}</a>
                        <br>
                        <small>${f.uploaded_at}</small>
                    </div>
                `;
            });
        }

        document.getElementById('attachmentsList').innerHTML = html;
        openModal('attachmentsModal');
    });
}

function openFileViewer(filePath){
    const frame = document.getElementById('fileViewerFrame');

    if(filePath.match(/\.(jpg|jpeg|png|gif|webp)$/i)){
        frame.outerHTML = `<img id="fileViewerFrame" src="${filePath}" 
            style="max-width:100%; max-height:100%; object-fit:contain;">`;
    } else {
        frame.outerHTML = `<iframe id="fileViewerFrame" src="${filePath}" 
            style="width:100%; height:100%; border:none; background:#fff;"></iframe>`;
    }

    openModal('fileViewerModal');
}
</script>

<?php require_once "../../layouts/footer.php"; ?>