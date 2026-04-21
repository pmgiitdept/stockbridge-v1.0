<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['president']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";

$selectedMonth = $_GET['month'] ?? date('Y-m');
$itemFilter = $_GET['item'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

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
        item_name,
        category,
        SUM(IF(movement_type='IN', quantity, 0)) as total_in,
        SUM(IF(movement_type='OUT', quantity, 0)) as total_out
    FROM inventory_movements
    $where
    GROUP BY item_name, category
";

$stmt1 = $conn->prepare($summaryInventorySql);
$stmt1->bind_param($types, ...$params);
$stmt1->execute();
$res1 = $stmt1->get_result();

$summaryPrSql = "
    SELECT 
        pi.item_description AS item_name,
        'Supply' AS category,
        0 as total_in,
        SUM(pi.quantity) as total_out
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

$summaryData = [];

while($row = $res1->fetch_assoc()){
    $key = $row['item_name'];
    $summaryData[$key] = $row;
}

while($row = $res2->fetch_assoc()){
    $key = $row['item_name'];

    if(!isset($summaryData[$key])){
        $summaryData[$key] = $row;
    } else {
        $summaryData[$key]['total_out'] += $row['total_out'];
    }
}

$stmt2 = $conn->prepare("
    SELECT 
        id,
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

$stmt2->bind_param($types, ...$params);
$stmt2->execute();
$resInventory = $stmt2->get_result();

$historyPrSql = "
    SELECT 
        pi.id,
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

$historyData = [];

while($row = $resInventory->fetch_assoc()){
    $historyData[] = $row;
}

while($row = $resPR->fetch_assoc()){
    $historyData[] = $row;
}

usort($historyData, function($a, $b){
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
?>

<style>
.inventory-header {
    display:flex;
    justify-content: space-between;
    align-items:center;
    margin-bottom:15px;
}

.inventory-card {
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 4px 12px rgba(0,0,0,0.06);
    margin-bottom:20px;
}

.table-responsive {
    max-height:420px;
    overflow-y:auto;
}

table {
    width:100%;
    border-collapse:collapse;
}

table th, table td {
    padding:10px;
    border-bottom:1px solid #eee;
}

table th {
    position:sticky;
    top:0;
    background:#f9fafb;
    z-index:2;
}

.badge-in {
    background:#d1fae5;
    color:#065f46;
    padding:4px 8px;
    border-radius:6px;
    font-size:12px;
}

.badge-out {
    background:#fee2e2;
    color:#7f1d1d;
    padding:4px 8px;
    border-radius:6px;
    font-size:12px;
}

.filter-bar {
    display:flex;
    flex-wrap:wrap;
    gap:15px;
    align-items:flex-end;
}

.filter-group {
    display:flex;
    flex-direction:column;
    font-size:13px;
}

.filter-group input,
.filter-group select {
    padding:6px 8px;
    border-radius:6px;
    border:1px solid #ddd;
}

.filter-actions {
    display:flex;
    gap:10px;
}

.custom-modal {
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(17,24,39,0.6);
    backdrop-filter: blur(4px);
    justify-content:center;
    align-items:center;
    z-index:1001;
}

.modal-content {
    background:#fff;
    padding:0;
    border-radius:14px;
    width:460px;
    max-height:85vh;
    overflow:hidden;
    box-shadow:0 20px 60px rgba(0,0,0,0.25);
    animation: pop 0.2s ease-in-out;
}

@keyframes pop {
    from { transform:scale(0.95); opacity:0; }
    to { transform:scale(1); opacity:1; }
}

.modal-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:16px 18px;
    border-bottom:1px solid #eee;
    background:#f9fafb;
}

.modal-header h3 {
    margin:0;
    font-size:16px;
    font-weight:600;
    color:#111827;
}

.close-btn {
    font-size:22px;
    cursor:pointer;
    color:#6b7280;
}
.close-btn:hover {
    color:#111827;
}

.modal-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    padding:18px;
}

.form-box {
    display:flex;
    flex-direction:column;
}

.form-box.full-width {
    grid-column:1 / -1;
}

.form-box label {
    font-size:12px;
    font-weight:600;
    margin-bottom:5px;
    color:#374151;
}

.form-box input,
.form-box select {
    padding:8px 10px;
    border:1px solid #e5e7eb;
    border-radius:8px;
    font-size:13px;
    outline:none;
}

.form-box input:focus,
.form-box select:focus {
    border-color:#3b82f6;
    box-shadow:0 0 0 2px rgba(59,130,246,0.15);
}

.file-upload {
    border:1px dashed #d1d5db;
    padding:10px;
    border-radius:8px;
    text-align:center;
    background:#f9fafb;
}

.file-upload input {
    width:100%;
}

.modal-actions {
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:14px 18px;
    border-top:1px solid #eee;
    background:#fff;
}

.btn-primary {
    background:#3b82f6;
    color:#fff;
    border:none;
    padding:8px 14px;
    border-radius:8px;
    cursor:pointer;
    font-weight:500;
}

.btn-primary:hover {
    background:#2563eb;
}

.btn-secondary {
    background:#e5e7eb;
    color:#111827;
    padding:8px 14px;
    border-radius:8px;
    border:none;
    cursor:pointer;
}
.btn-secondary:hover {
    background:#d1d5db;
}

#attachmentsModal .modal-content {
    width: 600px;
    max-width: 90%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    padding: 0;
}

.attachments-header {
    padding: 16px 18px;
    border-bottom: 1px solid #eee;
    background: #f9fafb;
    position: sticky;
    top: 0;
    z-index: 5;
}

.attachments-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.attachments-body {
    padding: 15px;
    overflow-y: auto;
    flex: 1;
}

.attachment-item {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
    background: #fff;
    transition: 0.2s ease;
}

.attachment-item:hover {
    box-shadow: 0 6px 14px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.attachment-title {
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
}

.attachment-title:hover {
    text-decoration: underline;
}

.attachment-meta {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
    line-height: 1.4;
}

.attachment-actions {
    display: flex;
    align-items: center;
}

.attachments-empty {
    text-align: center;
    padding: 30px 10px;
    color: #6b7280;
}

.attachments-body::-webkit-scrollbar {
    width: 6px;
}
.attachments-body::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}

.positive-stock strong {
    background: #d1fae5;
    padding: 4px 8px;
    border-radius: 6px;
}

.negative-stock strong {
    background: #fee2e2;
    padding: 4px 8px;
    border-radius: 6px;
}
</style>

<div class="main-content">
    <h1>Inventory Monitoring</h1>

    <div class="inventory-card">
        <div class="inventory-header">
        <h2>Filter Inventory</h2>

        <div style="display:flex; gap:10px;">
            <button onclick="openModal('addInventoryModal')" class="btn-primary">
                + Add Supplier Invoice
            </button>

            <button onclick="viewAllAttachments()" class="btn-secondary">
                📎 View All Attachments
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
        <h2>Stock Summary</h2>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
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
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="inventory-card">
        <h2>Inventory Movements</h2>

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
                            <?= htmlspecialchars($row['reference_type']) ?> #<?= $row['reference_id'] ?>
                        </td>
                        <td>
                            <button class="btn-primary"
                                onclick="viewAttachments(<?= $row['reference_id'] ?>)">
                                View
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addInventoryModal" class="custom-modal">
    <div class="modal-content">

        <div class="modal-header">
            <h3>📦 Add Supplier Invoice</h3>
            <span class="close-btn" onclick="closeModal('addInventoryModal')">&times;</span>
        </div>

        <form id="addInventoryForm" enctype="multipart/form-data">

            <div class="modal-grid">

                <div class="form-box">
                    <label>Item Name</label>
                    <input type="text" name="item_name" placeholder="e.g. Mop, Detergent" required>
                </div>

                <div class="form-box">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="Supply">Supply</option>
                        <option value="Tool">Tool</option>
                    </select>
                </div>

                <div class="form-box">
                    <label>Quantity</label>
                    <input type="number" step="0.01" name="quantity" placeholder="0.00" required>
                </div>

                <div class="form-box">
                    <label>Invoice No</label>
                    <input type="text" name="reference_id" placeholder="INV-0001" required>
                </div>

                <div class="form-box full-width">
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

<script>
function viewAllAttachments(){

    const params = new URLSearchParams(window.location.search);

    const container = document.getElementById('attachmentsList');

    // Loading state
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
                                <a href="${f.file_path}" target="_blank" class="attachment-title">
                                    📄 ${f.file_name}
                                </a>

                                <div class="attachment-meta">
                                    Item: <strong>${f.item_name}</strong><br>
                                    Ref #: ${f.reference_id}<br>
                                    Uploaded: ${f.uploaded_at}
                                </div>
                            </div>

                            <div class="attachment-actions">
                                <a href="${f.file_path}" target="_blank" class="btn-secondary" style="font-size:12px;">
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
    .catch(err=>{
        container.innerHTML = `
            <div style="color:red;">
                Failed to load attachments.
            </div>
        `;
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
    .then(r=>r.json())
    .then(d=>{
        if(d.success){
            location.reload();
        } else {
            alert(d.message);
        }
    });
});

function viewAttachments(refId){
    fetch('get_attachments.php?ref_id='+refId)
    .then(r=>r.json())
    .then(data=>{
        let html = '';

        if(data.length === 0){
            html = '<p>No attachments found.</p>';
        } else {
            data.forEach(f=>{
                html += `
                    <div class="attachment-item">
                        <a href="${f.file_path}" target="_blank">${f.file_name}</a>
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
</script>

<?php require_once "../../layouts/footer.php"; ?>