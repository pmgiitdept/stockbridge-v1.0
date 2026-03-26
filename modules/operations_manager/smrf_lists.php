<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_manager', 'president']);

$rowsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $rowsPerPage;

$countQuery = "SELECT COUNT(*) as total FROM smrf_forms";
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

$query = "
    SELECT 
        sf.smrf_id,
        sf.reference_id,
        sf.project,
        sf.project_code,
        sf.period,
        sf.status,
        sf.created_at,
        u.full_name as created_by
    FROM smrf_forms sf
    JOIN users u ON sf.created_by = u.id
    ORDER BY sf.created_at DESC
    LIMIT $rowsPerPage OFFSET $offset
";

$result = $conn->query($query);
$smrfs = $result->fetch_all(MYSQLI_ASSOC);

$statusCountsQuery = "
    SELECT 
        SUM(status='pending') AS totalPending,
        SUM(status='approved') AS totalApproved,
        SUM(status='rejected') AS totalRejected
    FROM smrf_forms
";
$statusCountsResult = $conn->query($statusCountsQuery);
$statusCounts = $statusCountsResult->fetch_assoc();

$totalPending = $statusCounts['totalPending'] ?? 0;
$totalApproved = $statusCounts['totalApproved'] ?? 0;
$totalRejected = $statusCounts['totalRejected'] ?? 0;
$totalSMRF = $totalRows; 
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="page-header">
        <h1>SMRF Workflow Monitoring</h1>
            <p class="page-subtitle">
            Review SMRF submissions and update their workflow status.
        </p>
    </div>

    <div class="content-grid" style="margin-bottom:20px;">
        <div class="summary-cards manager-summary" style="display:flex; gap:12px; flex-wrap:wrap;">
            <div class="card" style="background-color:#3b82f6; color:white; flex:1; padding:12px; border-radius:8px;">
                <div class="icon" style="font-size:24px;">📄</div>
                <div>
                    <h4>Total SMRF Forms</h4>
                    <p><?= $totalSMRF ?></p>
                </div>
            </div>

            <div class="card" style="background-color:#facc15; color:white; flex:1; padding:12px; border-radius:8px;">
                <div class="icon" style="font-size:24px;">⏳</div>
                <div>
                    <h4>Pending</h4>
                    <p><?= $totalPending ?></p>
                </div>
            </div>

            <div class="card" style="background-color:#10b981; color:white; flex:1; padding:12px; border-radius:8px;">
                <div class="icon" style="font-size:24px;">✅</div>
                <div>
                    <h4>Approved</h4>
                    <p><?= $totalApproved ?></p>
                </div>
            </div>

            <div class="card" style="background-color:#ef4444; color:white; flex:1; padding:12px; border-radius:8px;">
                <div class="icon" style="font-size:24px;">❌</div>
                <div>
                    <h4>Rejected</h4>
                    <p><?= $totalRejected ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-filters" style="margin-bottom:10px; display:flex; gap:12px; flex-wrap:wrap;">
            <input type="text" id="filterSMRF" placeholder="Search by SMRF ID / Project" class="filter-input">
            <input type="month" id="filterPeriod" class="filter-input">
            <button id="resetFilters" class="btn-primary">Reset</button>
        </div>

        <div class="table-responsive">
            <table id="smrfTable">
                <thead>
                    <tr>
                        <th>SMRF ID</th>
                        <th>Reference ID</th>
                        <th>Project</th>
                        <th>Project Code</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Update Status</th>
                        <th>Created By</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                
                <tbody>
                    <?php if(!empty($smrfs)): ?>
                    <?php foreach($smrfs as $smrf): ?>

                    <tr>
                        <td>
                            <span class="clickable-cell"><?= htmlspecialchars($smrf['smrf_id']) ?></span>
                        </td>
                        <td>
                            <span class="clickable-cell"><?= htmlspecialchars($smrf['reference_id'] ?: '-') ?></span>
                        </td>
                        <td><?= htmlspecialchars($smrf['project']) ?></td>
                        <td><?= htmlspecialchars($smrf['project_code'] ?: '-') ?></td>
                        <td><?= date("M Y", strtotime($smrf['period'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= $smrf['status'] ?>">
                                <?= ucfirst($smrf['status']) ?>
                            </span>
                        </td>
                        <td>
                            <select class="statusDropdown status-<?= $smrf['status'] ?>" data-id="<?= $smrf['smrf_id'] ?>">
                                <option value="pending" <?= $smrf['status']=='pending'?'selected':'' ?>>Pending</option>
                                <option value="approved" <?= $smrf['status']=='approved'?'selected':'' ?>>Approved</option>
                                <option value="rejected" <?= $smrf['status']=='rejected'?'selected':'' ?>>Rejected</option>
                            </select>
                        </td>
                        <td><?= htmlspecialchars($smrf['created_by']) ?></td>
                        <td><?= date("M d, Y H:i", strtotime($smrf['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center;">
                        No SMRF forms found.
                        </td>
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

<div id="smrfDetailsModal" class="custom-modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeSmrfDetailsModal()">&times;</span>
        <h3>SMRF Details</h3>

        <div id="smrfDetailsContent">
            <p>Loading...</p>
        </div>
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

const filterInput = document.getElementById('filterSMRF');
const filterPeriodInput = document.getElementById('filterPeriod');
const resetBtn = document.getElementById('resetFilters');

function filterTable(){
    const search = filterInput.value.toLowerCase();
    const period = filterPeriodInput.value;

    document.querySelectorAll('#smrfTable tbody tr').forEach(row => {

        const smrfId = row.cells[0].textContent.toLowerCase();
        const project = row.cells[2].textContent.toLowerCase();
        const rowPeriod = row.cells[4].textContent;

        let periodMatch = true;

        if(period){

            const [year,month] = period.split('-');
            const date = new Date(rowPeriod + " 1");

            periodMatch =
            date.getFullYear() === parseInt(year)
            &&
            (date.getMonth()+1) === parseInt(month);

        }
        const searchMatch = smrfId.includes(search) || project.includes(search);
        row.style.display = (searchMatch && periodMatch) ? '' : 'none';
    });
}

filterInput.addEventListener('input',filterTable);
filterPeriodInput.addEventListener('change',filterTable);

resetBtn.addEventListener('click',()=>{
    filterInput.value='';
    filterPeriodInput.value='';
    filterTable();
});

document.querySelectorAll('.statusDropdown').forEach(dropdown=>{
    dropdown.addEventListener('change',function(){

        const smrfId = this.dataset.id;
        const status = this.value;

        this.className = 'statusDropdown status-' + status;

        fetch('update_smrf_status.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                smrf_id:smrfId,
                status:status
            })
        })

        .then(res=>res.json())
        .then(data=>{
            if(data.success){
                showToast("Status updated successfully.");
                setTimeout(()=>location.reload(),1200);
            }else{
                showToast(data.message || "Failed to update status.","error");
            }
        })

        .catch(()=>{
            showToast("Network error.","error");
        });
    });
});

const smrfDetailsModal = document.getElementById('smrfDetailsModal');
const smrfDetailsContent = document.getElementById('smrfDetailsContent');

function openSmrfDetailsModal(smrfId, referenceId) {
    smrfDetailsModal.style.display = 'flex';
    smrfDetailsContent.innerHTML = `<p>Loading...</p>`;

    fetch('../operations_officer/fetch_smrf_details.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ smrf_id: smrfId, reference_id: referenceId })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            let smrf = data.smrf;

            let html = `
                <div class="smrf-details-grid">
                    <div class="smrf-details-item">
                        <div class="smrf-details-label">SMRF ID</div>
                        <div class="smrf-details-value">${smrf.smrf_id}</div>
                    </div>

                    <div class="smrf-details-item">
                        <div class="smrf-details-label">Reference ID</div>
                        <div class="smrf-details-value">${smrf.reference_id || '-'}</div>
                    </div>

                    <div class="smrf-details-item">
                        <div class="smrf-details-label">Project</div>
                        <div class="smrf-details-value">${smrf.project}</div>
                    </div>

                    <div class="smrf-details-item">
                        <div class="smrf-details-label">Project Code</div>
                        <div class="smrf-details-value">${smrf.project_code || '-'}</div>
                    </div>

                    <div class="smrf-details-item">
                        <div class="smrf-details-label">Period</div>
                        <div class="smrf-details-value">${smrf.period}</div>
                    </div>

                    <div class="smrf-details-item">
                        <div class="smrf-details-label">Status</div>
                        <div class="smrf-details-value">
                            <span class="status-badge status-${smrf.status}">
                                ${smrf.status}
                            </span>
                        </div>
                    </div>

                    <div class="smrf-details-item">
                        <div class="smrf-details-label">Created By</div>
                        <div class="smrf-details-value">${smrf.created_by}</div>
                    </div>

                    <div class="smrf-details-item">
                        <div class="smrf-details-label">Created At</div>
                        <div class="smrf-details-value">${smrf.created_at}</div>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; margin-bottom:10px;">
                    <h4>Items</h4>
                    <button class="btn-primary" onclick="exportSmrfPDF('${smrf.smrf_id}', '${smrf.reference_id || ''}')">
                        Export PDF
                    </button>
                </div>
                <div class="smrf-table-wrapper">
                    <table id="smrfDetailsItemsTable">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Legend</th>
                                <th>Unit Cost</th>
                                <th>Amount</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            let totalSupplies = 0;
            let totalTools = 0;

            data.items.forEach(item => {
                const quantity = parseInt(item.quantity) || 0; 
                const unitCost = parseFloat(item.unit_cost) || 0;
                const amount = parseFloat(item.amount) || 0;

                if(item.legend === 'SC'){
                    totalSupplies += amount;
                }

                if(item.legend === 'TE'){
                    totalTools += amount;
                }

                html += `
                    <tr>
                        <td>${item.item_code}</td>
                        <td>${item.item_description}</td>
                        <td>${quantity}</td>
                        <td>${item.unit}</td>
                        <td>${item.legend}</td>
                        <td>₱ ${unitCost.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                        <td>₱ ${amount.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                        <td>${item.remarks}</td>
                    </tr>
                `;
            });

            const subTotal = totalSupplies + totalTools;
            const overallTotal = subTotal * 1.12; 

            html += `
            </tbody></table></div>
            <div class="smrf-summary-cards">

                <div class="summary-card supplies-card">
                    <div class="summary-label">Total Cost of Supplies (SC)</div>
                    <div class="summary-value">₱${totalSupplies.toLocaleString(undefined,{minimumFractionDigits:2})}</div>
                </div>

                <div class="summary-card tools-card">
                    <div class="summary-label">Total Cost of Tools (TE)</div>
                    <div class="summary-value">₱${totalTools.toLocaleString(undefined,{minimumFractionDigits:2})}</div>
                </div>

                <div class="summary-card total-card">
                    <div class="summary-label">Overall Total w/ VAT</div>
                    <div class="summary-value">₱${overallTotal.toLocaleString(undefined,{minimumFractionDigits:2})}</div>
                </div>

            </div>
            `;
            smrfDetailsContent.innerHTML = html;
        } else {
            smrfDetailsContent.innerHTML = `<p>${data.message || 'Failed to load SMRF details.'}</p>`;
        }
    })
    .catch(() => {
        smrfDetailsContent.innerHTML = `<p class="text-red-500">Network error. Please try again.</p>`;
    });
}

function closeSmrfDetailsModal() {
    smrfDetailsModal.style.display = 'none';
}

document.querySelectorAll('#smrfTable tbody tr').forEach(row => {
    const smrfIdCell = row.cells[0];
    const refIdCell = row.cells[1];

    const smrfId = smrfIdCell.textContent.trim();
    const referenceId = refIdCell.textContent.trim() === '-' ? null : refIdCell.textContent.trim();

    smrfIdCell.innerHTML = `<span class="clickable-cell">${smrfId}</span>`;
    if(referenceId) {
        refIdCell.innerHTML = `<span class="clickable-cell">${referenceId}</span>`;
    }

    const smrfSpan = smrfIdCell.querySelector('.clickable-cell');
    smrfSpan.addEventListener('click', () => openSmrfDetailsModal(smrfId, referenceId));

    if(referenceId) {
        const refSpan = refIdCell.querySelector('.clickable-cell');
        refSpan.addEventListener('click', () => openSmrfDetailsModal(smrfId, referenceId));
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>