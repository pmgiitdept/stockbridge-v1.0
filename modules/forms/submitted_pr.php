<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);

$rowsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $rowsPerPage;

$countQuery = "SELECT COUNT(*) as total FROM pr_forms";
$totalResult = $conn->query($countQuery);
if(!$totalResult) die("Count query failed: " . $conn->error);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

$query = "
    SELECT pf.pr_id, pf.period, pf.requesting_department, pf.project, pf.status, pf.created_at, u.full_name as created_by
    FROM pr_forms pf
    JOIN users u ON pf.created_by = u.id
    ORDER BY pf.created_at DESC
    LIMIT $rowsPerPage OFFSET $offset
";
$result = $conn->query($query);
if(!$result) die("Query failed: " . $conn->error);
$prs = $result->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="page-header">
        <h1>Submitted PRs</h1>
        <p class="page-subtitle">All created Purchase Requisitions and their current status.</p>
    </div>

    <div class="table-card">
        <div class="table-filters" style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <input type="text" id="filterPR" placeholder="Search by PR ID / Project" class="filter-input">
                <input type="month" id="filterPRPeriod" class="filter-input">
                <button id="resetPRFilters" class="btn-primary">Reset</button>
            </div>
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
                        <th>Created By</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($prs)): ?>
                        <?php foreach($prs as $pr): ?>
                            <tr>
                                <td class="clickable-cell"><?= htmlspecialchars($pr['pr_id']) ?></td>
                                <td><?= date("M Y", strtotime($pr['period'])) ?></td>
                                <td><?= htmlspecialchars($pr['requesting_department']) ?></td>
                                <td><?= htmlspecialchars($pr['project'] ?: '-') ?></td>
                                <td><span class="status-badge status-<?= $pr['status'] ?>"><?= ucfirst($pr['status']) ?></span></td>
                                <td><?= htmlspecialchars($pr['created_by']) ?></td>
                                <td><?= date("M d, Y H:i", strtotime($pr['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No PRs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($totalPages > 1): ?>
            <div class="pagination" style="margin-top:10px; display:flex; justify-content:center; align-items:center; gap:12px;">
                <?php if($page > 1): ?>
                    <?php $queryParams = $_GET; $queryParams['page'] = $page - 1; ?>
                    <a href="?<?= http_build_query($queryParams) ?>">&laquo; Previous</a>
                <?php endif; ?>

                <span>Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if($page < $totalPages): ?>
                    <?php $queryParams['page'] = $page + 1; ?>
                    <a href="?<?= http_build_query($queryParams) ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="prDetailsModal" class="custom-modal">
    <div class="modal-content" style="max-width: 1000px; width: 95%;">
        <span class="close-btn" onclick="closePrDetailsModal()">&times;</span>
        <h3>PR Details</h3>
        <div id="prDetailsContent">
            <p>Loading...</p>
        </div>
    </div>
</div>

<script>
const filterInput = document.getElementById('filterPR');
const filterPeriodInput = document.getElementById('filterPRPeriod');
const resetBtn = document.getElementById('resetPRFilters');

function filterPRTable() {
    const search = filterInput.value.toLowerCase();
    const period = filterPeriodInput.value;

    document.querySelectorAll('#prTable tbody tr').forEach(row => {
        const prId = row.cells[0].textContent.toLowerCase();
        const project = row.cells[3].textContent.toLowerCase();
        const rowPeriod = row.cells[1].textContent;

        let periodMatch = true;
        if(period) {
            const [year, month] = period.split('-');
            const date = new Date(rowPeriod + " 1");
            periodMatch = date.getFullYear() === parseInt(year) && (date.getMonth()+1) === parseInt(month);
        }

        const searchMatch = prId.includes(search) || project.includes(search);
        row.style.display = (searchMatch && periodMatch) ? '' : 'none';
    });
}

filterInput.addEventListener('input', filterPRTable);
filterPeriodInput.addEventListener('change', filterPRTable);
resetBtn.addEventListener('click', () => {
    filterInput.value = '';
    filterPeriodInput.value = '';
    filterPRTable();
});

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
                <div><label>Requesting Department</label><input type="text" class="filter-input" value="${pr.requesting_department}" readonly></div>
                <div><label>Project</label><input type="text" class="filter-input" value="${pr.project || ''}" readonly></div>
                <div><label>Purpose of Requisition</label><input type="text" class="filter-input" value="${pr.purpose_of_requisition || ''}" readonly></div>
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
        prDetailsContent.innerHTML = `<p class="text-red-500">Network error. Please try again.</p>`;
    });
}

function closePrDetailsModal() {
    prDetailsModal.style.display = 'none';
}

document.querySelectorAll('#prTable tbody tr').forEach(row => {
    const prIdCell = row.cells[0];
    const prId = prIdCell.textContent.trim();
    prIdCell.addEventListener('click', () => openPrDetailsModal(prId));
    prIdCell.classList.add('clickable-cell');
});

const exportBtn = document.getElementById('exportPrPdfBtn');
if(exportBtn) {
    exportBtn.addEventListener('click', () => {
        window.open(`export_pr_pdf.php?pr_id=${prId}`, '_blank');
    });
}
</script>

<?php require_once "../../layouts/footer.php"; ?>