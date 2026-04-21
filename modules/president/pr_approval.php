<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['president']);

$rowsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $rowsPerPage;

$countQuery = "SELECT COUNT(*) as total FROM pr_forms WHERE status='reviewed'";
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

$query = "
    SELECT 
        pf.pr_id,
        pf.period,
        pf.requesting_department,
        pf.project,
        pf.status,
        pf.created_at,
        u.full_name as created_by
    FROM pr_forms pf
    JOIN users u ON pf.created_by = u.id
    WHERE pf.status = 'reviewed'
    ORDER BY pf.created_at DESC
    LIMIT $rowsPerPage OFFSET $offset
";

$result = $conn->query($query);
$prs = $result->fetch_all(MYSQLI_ASSOC);

$query = "
    SELECT 
        pf.pr_id,
        pf.period,
        pf.requesting_department,
        pf.project,
        pf.status,
        pf.created_at,
        u.full_name as created_by
    FROM pr_forms pf
    JOIN users u ON pf.created_by = u.id
    WHERE pf.status = 'reviewed'
    ORDER BY pf.created_at DESC
    LIMIT $rowsPerPage OFFSET $offset
";

$result = $conn->query($query);
$prs = $result->fetch_all(MYSQLI_ASSOC);

$historyQuery = "
    SELECT 
        pf.pr_id,
        pf.period,
        pf.requesting_department,
        pf.project,
        pf.status,
        pf.created_at,
        u.full_name as created_by
    FROM pr_forms pf
    JOIN users u ON pf.created_by = u.id
    WHERE pf.status IN ('approved','verified','rejected')
    ORDER BY pf.created_at DESC
    LIMIT 10
";

$historyResult = $conn->query($historyQuery);
$historyPRs = $historyResult->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="page-header">
        <h1>PR Approval</h1>
        <p class="page-subtitle">
            Final approval of reviewed Purchase Requisitions.
        </p>
    </div>

    <div class="dual-table-container">

        <!-- LEFT: FOR APPROVAL -->
        <div class="table-card">
            <h3 style="margin-bottom:10px;">Pending Approval</h3>

            <div class="table-responsive">
                <table id="prTable">
                    <thead>
                        <tr>
                            <th>PR ID</th>
                            <th>Period</th>
                            <th>Department</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($prs)): ?>
                            <?php foreach($prs as $pr): ?>
                            <tr>
                                <td><span class="clickable-cell"><?= $pr['pr_id'] ?></span></td>
                                <td><?= date("M Y", strtotime($pr['period'])) ?></td>
                                <td><?= $pr['requesting_department'] ?></td>
                                <td><?= $pr['project'] ?: '-' ?></td>

                                <td>
                                    <span class="status-badge status-reviewed">Reviewed</span>
                                </td>

                                <td>
                                    <select class="decisionDropdown" data-id="<?= $pr['pr_id'] ?>">
                                        <option value="">Select</option>
                                        <option value="approved">Approve</option>
                                        <option value="verified">Verify</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;">No reviewed PRs.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- RIGHT: HISTORY -->
        <div class="table-card">
            <h3 style="margin-bottom:10px;">Processed PRs</h3>

            <div class="table-responsive">
                <table id="historyTable">
                    <thead>
                        <tr>
                            <th>PR ID</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if(!empty($historyPRs)): ?>
                            <?php foreach($historyPRs as $pr): ?>
                            <tr>
                                <td><span class="clickable-cell"><?= $pr['pr_id'] ?></span></td>
                                <td><?= $pr['project'] ?: '-' ?></td>

                                <td>
                                    <span class="status-badge status-<?= $pr['status'] ?>">
                                        <?= ucfirst($pr['status']) ?>
                                    </span>
                                </td>

                                <td><?= date("M d, Y", strtotime($pr['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No processed PRs.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="prDetailsModal" class="custom-modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%;">
        <span class="close-btn" onclick="closePrDetailsModal()">&times;</span>
        <h3>PR Details</h3>
        <div id="prDetailsContent"><p>Loading...</p></div>
    </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<div id="confirmModal" class="custom-modal">
    <div class="modal-content" style="max-width:400px; text-align:center;">
        <h3 id="confirmMessage">Are you sure?</h3>

        <div style="margin-top:20px; display:flex; justify-content:center; gap:12px;">
            <button id="confirmYes" class="btn-primary">Yes</button>
            <button id="confirmNo" class="btn-danger">Cancel</button>
        </div>
    </div>
</div>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');

    const toast = document.createElement('div');
    toast.classList.add('toast', `toast-${type}`);
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3600);
}

let confirmCallback = null;
let cancelCallback = null;

const confirmModal = document.getElementById('confirmModal');
const confirmMessage = document.getElementById('confirmMessage');

function openConfirmModal(message, onConfirm, onCancel = null) {
    confirmMessage.textContent = message;

    confirmCallback = onConfirm;
    cancelCallback = onCancel;

    confirmModal.style.display = 'flex';
}

function closeConfirmModal() {
    confirmModal.style.display = 'none';
}

// Button handlers
document.getElementById('confirmYes').onclick = () => {
    if (confirmCallback) confirmCallback();
    closeConfirmModal();
};

document.getElementById('confirmNo').onclick = () => {
    if (cancelCallback) cancelCallback();
    closeConfirmModal();
};

document.querySelectorAll('.decisionDropdown').forEach(dropdown=>{
    dropdown.addEventListener('change',function(){

        const prId = this.dataset.id;
        const status = this.value;

        if(!status) return;

        openConfirmModal(
            "Are you sure you want to " + status + " this PR?",
            () => executeDecision(this, prId, status),
            () => this.value = ""
        );

        return; // IMPORTANT: stop execution here
    });
});

function executeDecision(dropdown, prId, status) {

    fetch('update_pr_status_president.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            pr_id:prId,
            status:status
        })
    })
    .then(res=>res.json())
    .then(data=>{
        if(data.success){

            showToast("PR " + status + " successfully!", "success");

            const row = dropdown.closest('tr');
            const statusBadge = row.querySelector('.status-badge');

            statusBadge.className = 'status-badge status-' + status;
            statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);

            dropdown.disabled = true;

            setTimeout(()=>location.reload(),1200);

        }else{
            showToast(data.message || "Failed.","error");
            dropdown.value = "";
        }
    })
    .catch(()=>{
        showToast("Network error.","error");
        dropdown.value = "";
    });
}

const prDetailsModal = document.getElementById('prDetailsModal');
const prDetailsContent = document.getElementById('prDetailsContent');

function openPrDetailsModal(prId) {
    prDetailsModal.style.display = 'flex';
    prDetailsContent.innerHTML = `<p>Loading...</p>`;

    fetch('../forms/fetch_pr_details.php', {
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
                <div><label>Requesting Department</label><input type="text" class="filter-input" value="${pr.requesting_department}" readonly></div>
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
                <button class="btn-primary" onclick="window.open('export_pr_pdf.php?pr_id=${pr.pr_id}', '_blank')">
                    Export PDF
                </button>
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
                    <tbody>
        `;

        items.forEach(item => {
            html += `
                <tr>
                    <td>${item.quantity || ''}</td>
                    <td>${item.unit || ''}</td>
                    <td>${item.item_description || ''}</td>
                    <td>${item.remarks || ''}</td>
                </tr>
            `;
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

document.querySelectorAll('#prTable tbody tr, #historyTable tbody tr').forEach(row => {
    const prIdCell = row.cells[0];
    const prId = prIdCell.textContent.trim();

    const clickable = prIdCell.querySelector('.clickable-cell');

    if (clickable) {
        clickable.addEventListener('click', () => openPrDetailsModal(prId));
    } else {
        // For history table (no span), make whole cell clickable
        prIdCell.style.cursor = "pointer";
        prIdCell.addEventListener('click', () => openPrDetailsModal(prId));
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>