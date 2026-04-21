<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['purchasing_officer']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";

// Pagination setup
$rowsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $rowsPerPage;

// Fetch approved PRs
$countQuery = "SELECT COUNT(*) as total FROM pr_forms WHERE status='approved'";
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

$query = "
    SELECT pf.pr_id, pf.period, pf.requesting_department, pf.project, pf.status, pf.created_at, pf.received_by, u.full_name as created_by
    FROM pr_forms pf
    JOIN users u ON pf.created_by = u.id
    WHERE pf.status = 'approved'
    ORDER BY pf.created_at DESC
    LIMIT $rowsPerPage OFFSET $offset
";

$result = $conn->query($query);
$prs = $result->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="page-header">
        <h1>Purchase Requests</h1>
        <p>All approved purchase requests from the President appear here. Click a PR ID to view full details.</p>
    </div>

    <div class="content-grid">
        <div class="table-card">
            <div class="table-responsive">
                <table id="prTable">
                    <thead>
                        <tr>
                            <th>PR ID</th>
                            <th>Requested By</th>
                            <th>Project</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Receiving Status</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($prs)): ?>
                            <?php foreach($prs as $pr): ?>
                            <tr>
                                <td><?= $pr['pr_id'] ?></td>
                                <td><?= htmlspecialchars($pr['created_by']) ?></td>
                                <td><?= htmlspecialchars($pr['project'] ?: '-') ?></td>
                                <td><button class="btn-secondary btn-sm" onclick="openPrDetailsModal('<?= $pr['pr_id'] ?>')">View Items</button></td>
                                <td><span class="status-badge status-approved">Approved</span></td>
                                <td>
                                    <?php if (empty($pr['received_by'])): ?>
                                        <button class="btn-success btn-sm" onclick="markAsReceived('<?= $pr['pr_id'] ?>', this)">Mark as Received</button>
                                    <?php else: ?>
                                        Received
                                    <?php endif; ?>
                                </td>
                                <td><?= date("M d, Y", strtotime($pr['created_at'])) ?></td>
                                <td>
                                    <button class="btn-primary btn-sm" onclick="window.open('export_pr_pdf.php?pr_id=<?= $pr['pr_id'] ?>', '_blank')">Export PDF</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;">No approved purchase requests yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($totalPages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>">&laquo; Previous</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PR Details Modal -->
<div id="prDetailsModal" class="custom-modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%;">
        <span class="close-btn" onclick="closePrDetailsModal()">&times;</span>
        <h3>PR Details</h3>
        <div id="prDetailsContent"><p>Loading...</p></div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="custom-modal" style="display:none; justify-content:center; align-items:center;">
    <div class="modal-content" style="max-width:400px; width:90%; text-align:center;">
        <p id="confirmMessage">Are you sure?</p>
        <div style="margin-top:15px;">
            <button class="btn-primary" id="confirmYesBtn">Yes</button>
            <button class="btn-secondary" id="confirmNoBtn">No</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
let currentBtn = null;
let currentPrId = null;
let confirmCallback = null;
let cancelCallback = null;

function markAsReceived(prId, btn) {
    currentBtn = btn;
    currentPrId = prId;
    openConfirmModal(
        "Are you sure you want to mark this PR as received?",
        () => executeMarkReceived(),
        () => { currentBtn = null; currentPrId = null; }
    );
}

function executeMarkReceived() {
    if (!currentPrId || !currentBtn) return;

    fetch('mark_received.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pr_id: currentPrId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            currentBtn.outerHTML = 'Received';
            showToast(`PR ${currentPrId} marked as received successfully!`, 'success');

            // Update PR Details modal if open
            const receivedInput = document.querySelector('#prDetailsContent input[readonly][value=""]');
            if (receivedInput) receivedInput.value = data.full_name;
        } else {
            showToast(data.message || 'Failed to mark as received.', 'error');
        }
        currentBtn = null;
        currentPrId = null;
    })
    .catch(() => {
        showToast('Network error.', 'error');
        currentBtn = null;
        currentPrId = null;
    });
}

function openConfirmModal(message, onConfirm, onCancel = null) {
    document.getElementById('confirmMessage').textContent = message;
    confirmCallback = onConfirm;
    cancelCallback = onCancel;
    document.getElementById('confirmModal').style.display = 'flex';
}

document.getElementById('confirmYesBtn').addEventListener('click', () => {
    if (confirmCallback) confirmCallback();
    document.getElementById('confirmModal').style.display = 'none';
});

document.getElementById('confirmNoBtn').addEventListener('click', () => {
    if (cancelCallback) cancelCallback();
    document.getElementById('confirmModal').style.display = 'none';
});

function showToast(message, type='success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.classList.add('toast', `toast-${type}`);
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3600);
}

// PR Details modal
const prDetailsModal = document.getElementById('prDetailsModal');
const prDetailsContent = document.getElementById('prDetailsContent');

function openPrDetailsModal(prId) {
    prDetailsModal.style.display = 'flex';
    prDetailsContent.innerHTML = `<p>Loading...</p>`;

    fetch('fetch_pr_details.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pr_id: prId })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            prDetailsContent.innerHTML = `<p>${data.message || 'Failed to load PR details.'}</p>`;
            return;
        }

        const pr = data.pr;
        const items = data.items;
        let html = `
            <div class="pr-form-grid">
                <div><label>PR Date</label><input type="date" class="filter-input" value="${pr.date}" readonly></div>
                <div><label>Period</label><input type="month" class="filter-input" value="${pr.period.slice(0,7)}" readonly></div>
                <div><label>Department</label><input type="text" class="filter-input" value="${pr.requesting_department}" readonly></div>
                <div><label>Project</label><input type="text" class="filter-input" value="${pr.project || ''}" readonly></div>
                <div><label>Purpose</label><input type="text" class="filter-input" value="${pr.purpose_of_requisition || ''}" readonly></div>
                <div><label>Requested By</label><input type="text" class="filter-input" value="${pr.requested_by || ''}" readonly></div>
                <div><label>Reviewed By</label><input type="text" class="filter-input" value="${pr.reviewed_by || ''}" readonly></div>
                <div><label>Approved By</label><input type="text" class="filter-input" value="${pr.approved_by || ''}" readonly></div>
                <div><label>Received By</label><input type="text" class="filter-input" value="${pr.received_by || ''}" readonly></div>
            </div>
            <div style="margin-top:15px;">
                <label class="form-label">Justification</label>
                <textarea class="form-textarea" rows="3" readonly>${pr.justification || ''}</textarea>
            </div>
            <hr style="margin:20px 0;">
            <div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
                <button class="btn-primary" onclick="window.open('export_pr_pdf.php?pr_id=${pr.pr_id}', '_blank')">Export PDF</button>
            </div>
            <h4>PR Items</h4>
            <div class="smrf-table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Description</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>`;
        items.forEach(item => {
            html += `<tr>
                        <td>${item.quantity || ''}</td>
                        <td>${item.unit || ''}</td>
                        <td>${item.item_description || ''}</td>
                        <td>${item.remarks || ''}</td>
                    </tr>`;
        });
        html += `</tbody></table></div>`;
        prDetailsContent.innerHTML = html;
    })
    .catch(() => {
        prDetailsContent.innerHTML = `<p class="text-red-500">Network error.</p>`;
    });
}

function closePrDetailsModal() {
    prDetailsModal.style.display = 'none';
}

// Make PR IDs clickable
document.querySelectorAll('#prTable tbody tr .clickable-cell').forEach(cell => {
    const prId = cell.textContent.trim();
    cell.style.cursor = "pointer";
    cell.addEventListener('click', () => openPrDetailsModal(prId));
});
</script>

<?php require_once "../../layouts/footer.php"; ?>