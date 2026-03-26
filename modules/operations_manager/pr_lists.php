<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_manager']);

$rowsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $rowsPerPage;

$countQuery = "SELECT COUNT(*) as total FROM pr_forms";
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
    ORDER BY pf.created_at DESC
    LIMIT $rowsPerPage OFFSET $offset
";

$result = $conn->query($query);
$prs = $result->fetch_all(MYSQLI_ASSOC);

$statusCountsQuery = "
    SELECT
        SUM(status='pending') AS totalPending,
        SUM(status='reviewed') AS totalReviewed,
        SUM(status='approved') AS totalApproved,
        SUM(status='rejected') AS totalRejected,
        SUM(status='in_progress') AS totalInProgress,
        SUM(status='completed') AS totalCompleted
    FROM pr_forms
";
$statusCountsResult = $conn->query($statusCountsQuery);
$statusCounts = $statusCountsResult->fetch_assoc();

$totalPending = $statusCounts['totalPending'] ?? 0;
$totalReviewed = $statusCounts['totalReviewed'] ?? 0;
$totalApproved = $statusCounts['totalApproved'] ?? 0;
$totalRejected = $statusCounts['totalRejected'] ?? 0;
$totalInProgress = $statusCounts['totalInProgress'] ?? 0;
$totalCompleted = $statusCounts['totalCompleted'] ?? 0;
$totalPR = $totalRows; 
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="page-header">
        <h1>PR Workflow Monitoring</h1>
        <p class="page-subtitle">
            Review Purchase Requisitions and manage their workflow status.
        </p>
    </div>

    <div class="content-grid" style="margin-bottom:20px;">
        <div class="summary-cards manager-summary" style="display:flex; gap:12px; flex-wrap:wrap;">
            <div class="card" style="background-color:#3b82f6; color:white; flex:1; padding:12px; border-radius:8px;">
    <div class="icon" style="font-size:28px;">🗂️</div>
        <div>
            <h4>Total PR Forms</h4>
            <p><?= $totalPR ?></p>
        </div>
    </div>

    <div class="card" style="background-color:#facc15; color:white; flex:1; padding:12px; border-radius:8px;">
        <div class="icon" style="font-size:28px;">⏳</div> 
        <div>
            <h4>Pending</h4>
            <p><?= $totalPending ?></p>
        </div>
    </div>

    <div class="card" style="background-color:#60a5fa; color:white; flex:1; padding:12px; border-radius:8px;">
        <div class="icon" style="font-size:28px;">📋</div>
        <div>
            <h4>Reviewed</h4>
            <p><?= $totalReviewed ?></p>
        </div>
    </div>

    <div class="card" style="background-color:#ef4444; color:white; flex:1; padding:12px; border-radius:8px;">
        <div class="icon" style="font-size:28px;">❌</div> 
        <div>
            <h4>Rejected</h4>
            <p><?= $totalRejected ?></p>
        </div>
    </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-filters" style="margin-bottom:10px; display:flex; gap:12px; flex-wrap:wrap;">
            <input type="text" id="filterPR" placeholder="Search by PR ID / Project" class="filter-input">
            <input type="month" id="filterPRPeriod" class="filter-input">
            <button id="resetPRFilters" class="btn-primary">Reset</button>
        </div>

        <div class="table-responsive">
            <table id="prTable">
                <thead>
                    <tr>
                        <th>PR ID</th>
                        <th>Period</th>
                        <th>Requesting Dept</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Update Status</th>
                        <th>Created By</th>
                        <th>Created At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if(!empty($prs)): ?>
                        <?php foreach($prs as $pr): ?>
                        <tr>
                            <td>
                                <span class="clickable-cell"><?= htmlspecialchars($pr['pr_id']) ?></span>
                            </td>
                            <td><?= date("M Y", strtotime($pr['period'])) ?></td>
                            <td><?= htmlspecialchars($pr['requesting_department']) ?></td>
                            <td><?= htmlspecialchars($pr['project'] ?: '-') ?></td>

                            <td>
                                <span class="status-badge status-<?= $pr['status'] ?>">
                                    <?= ucfirst($pr['status']) ?>
                                </span>
                            </td>

                            <td>
                                <select class="statusDropdown status-<?= $pr['status'] ?>" data-id="<?= $pr['pr_id'] ?>">
                                    <option value="pending" <?= $pr['status']=='pending'?'selected':'' ?>>Pending</option>
                                    <option value="reviewed" <?= $pr['status']=='reviewed'?'selected':'' ?>>Reviewed</option>
                                    <option value="rejected" <?= $pr['status']=='rejected'?'selected':'' ?>>Rejected</option>
                                </select>
                            </td>

                            <td><?= htmlspecialchars($pr['created_by']) ?></td>
                            <td><?= date("M d, Y H:i", strtotime($pr['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No PRs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($totalPages > 1): ?>
        <div class="pagination" style="margin-top:10px; display:flex; justify-content:center; gap:12px;">
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

<div id="prDetailsModal" class="custom-modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%;">
        <span class="close-btn" onclick="closePrDetailsModal()">&times;</span>
        <h3>PR Details</h3>
        <div id="prDetailsContent"><p>Loading...</p></div>
    </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
function showToast(message, type = "success") {

    const container = document.getElementById("toastContainer");

    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add("show");
    }, 100);

    setTimeout(() => {
        toast.classList.remove("show");

        setTimeout(() => {
            toast.remove();
        }, 300);

    }, 3000);
}

document.querySelectorAll('.statusDropdown').forEach(dropdown=>{
    dropdown.addEventListener('change',function(){

        const prId = this.dataset.id;
        const status = this.value;

        this.className = 'statusDropdown status-' + status;

        fetch('update_pr_status.php',{
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
                showToast("PR status updated.");
                setTimeout(()=>location.reload(),1200);
            }else{
                showToast(data.message || "Failed to update.","error");
            }
        })

        .catch(()=>{
            showToast("Network error.","error");
        });
    });
});

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

document.querySelectorAll('#prTable tbody tr').forEach(row => {
    const prIdCell = row.cells[0];
    const prId = prIdCell.textContent.trim();

    const span = prIdCell.querySelector('.clickable-cell');
    if (span) {
        span.addEventListener('click', () => openPrDetailsModal(prId));
    }
});

</script>

<?php require_once "../../layouts/footer.php"; ?>