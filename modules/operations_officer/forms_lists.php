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

$countQuery = "
    SELECT COUNT(DISTINCT cf.reference_id) as total
    FROM client_forms cf
    JOIN users u ON cf.user_id = u.id
";
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

$query = "
    SELECT cf.reference_id, cf.status, MIN(cf.created_at) as submitted_at, u.full_name as client_name
    FROM client_forms cf
    JOIN users u ON cf.user_id = u.id
    GROUP BY cf.reference_id, cf.status
    ORDER BY submitted_at DESC
    LIMIT $rowsPerPage OFFSET $offset
";
$result = $conn->query($query);
$submissions = $result->fetch_all(MYSQLI_ASSOC);

$itemsQuery = $conn->query("
    SELECT reference_id, item_code, item_description, unit, quantity 
    FROM client_forms 
    ORDER BY id ASC
");
$allItems = [];
while($row = $itemsQuery->fetch_assoc()){
    $allItems[$row['reference_id']][] = $row;
}

$verifiedQuery = "
    SELECT DISTINCT reference_id 
    FROM client_forms 
    WHERE status = 'verified'
    ORDER BY reference_id DESC
";
$verifiedResult = $conn->query($verifiedQuery);
$verifiedForms = [];
while($row = $verifiedResult->fetch_assoc()){
    $verifiedForms[] = $row['reference_id'];
}
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="page-header">
        <h1>Project Submitted Forms</h1>
        <p class="page-subtitle">View project submissions and update their status.</p>
    </div>

    <button class="btn-primary" id="openSmrfModal">Create SMRF</button>

    <div class="content-grid-2col">
    
        <div class="table-card">
            <div class="table-filters" style="margin-bottom:10px; display:flex; gap:12px; flex-wrap:wrap;">
                <input type="text" id="filterName" placeholder="Search by Project/User" class="filter-input">
                <input type="month" id="filterDate" class="filter-input">
                <button id="resetFilters" class="btn-primary">Reset</button>
            </div>

            <div class="table-responsive">
                <table id="formsListTable">
                    <thead>
                        <tr>
                            <th>Reference ID</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($submissions)): ?>
                            <?php foreach($submissions as $submission): ?>
                                <tr data-ref="<?= htmlspecialchars($submission['reference_id']) ?>">
                                    <td><?= htmlspecialchars($submission['reference_id']) ?></td>
                                    <td><?= htmlspecialchars($submission['client_name']) ?></td>
                                    <td>
                                        <select class="status-select status-<?= htmlspecialchars($submission['status']) ?>" data-ref="<?= htmlspecialchars($submission['reference_id']) ?>">
                                            <option value="pending" <?= $submission['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="verified" <?= $submission['status'] === 'verified' ? 'selected' : '' ?>>Verified</option>
                                            <option value="rejected" <?= $submission['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                    </td>
                                    <td><?= date("M d, Y H:i", strtotime($submission['submitted_at'])) ?></td>
                                    <td>
                                        <button class="btn-primary view-btn" data-ref="<?= htmlspecialchars($submission['reference_id']) ?>">
                                            View
                                        </button>
                                    </td>
                                    <td class="approved-by">
                                        <?php if($submission['status'] === 'verified'): ?>
                                            <?= htmlspecialchars($_SESSION['full_name']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">No submitted forms found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($totalPages > 1): ?>
                <div class="pagination" style="margin-top:10px; display:flex; justify-content:center; align-items:center; gap:12px;">
                    <?php if($page > 1): ?>
                        <?php 
                        $queryParams = $_GET;
                        $queryParams['page'] = $page - 1;
                        ?>
                        <a href="?<?php echo http_build_query($queryParams); ?>">&laquo; Previous</a>
                    <?php endif; ?>

                    <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                    <?php if($page < $totalPages): ?>
                        <?php 
                        $queryParams['page'] = $page + 1;
                        ?>
                        <a href="?<?php echo http_build_query($queryParams); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card details-card">
            <div class="details-header">
                <h3>Form Items</h3>
                <span id="selectedRefText" class="ref-text">
                    Select a form to view its items.
                </span>
            </div>

            <div id="detailsBody" class="details-body">
                <div class="details-placeholder">
                    <p>No form selected.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="smrfModal" class="custom-modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeSmrfModal()">&times;</span>
        <h3>Create SMRF</h3>

        <div class="smrf-form">
            <div class="smrf-form-grid">
                <div>
                    <label>Reference ID (optional)</label>
                    <select id="smrfReference">
                        <option value="">-- Manual Entry --</option>
                        <?php foreach($verifiedForms as $ref): ?>
                            <option value="<?= $ref ?>"><?= $ref ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Project</label>
                    <input type="text" id="smrfProject">
                </div>

                <div>
                    <label>Project Code</label>
                    <input type="text" id="smrfProjectCode">
                </div>

                <div>
                    <label>Period</label>
                    <input type="month" id="smrfPeriod">
                </div>
            </div>

            <h4>Items</h4>
            <div class="smrf-table-wrapper">
                <table id="smrfItemsTable">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Remarks</th>
                            <th>Legend</th>
                            <th>Unit Cost</th>
                            <th>Amount</th>
                            <th><button id="addItemRow" class="btn-primary">+</button></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <button class="btn-primary" id="submitSmrf">Submit SMRF</button>
        </div>
    </div>
</div>

<div id="rejectionModal" class="custom-modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeRejectionModal()">&times;</span>
        <h3>Reason for Rejection</h3>
        <textarea id="rejectionReasonInput" rows="4" style="width:100%; padding:8px;"></textarea>
        <div style="margin-top:10px; display:flex; justify-content:flex-end; gap:8px;">
            <button class="btn-primary" onclick="submitRejectionReason()">Submit</button>
            <button class="btn-secondary" onclick="closeRejectionModal()">Cancel</button>
        </div>
    </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<?php
$priceQuery = $conn->query("SELECT item_code, pmgi_unit_price, legend FROM price_lists");
$priceMap = [];
$legendMap = [];

while($row = $priceQuery->fetch_assoc()){
    $priceMap[$row['item_code']] = $row['pmgi_unit_price'];
    $legendMap[$row['item_code']] = $row['legend']; 
}
?>

<script>
const pesoFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2
});

function formatPeso(value){
    if(!value || isNaN(value)) return '';
    return pesoFormatter.format(value);
}

function parsePeso(value){
    if(!value) return 0;
    return parseFloat(value.replace(/[₱,]/g,'')) || 0;
}

const priceMap = <?= json_encode($priceMap) ?>;
const allItems = <?= json_encode($allItems) ?>;
const legendMap = <?= json_encode($legendMap) ?>;

document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        const refId = this.dataset.ref;
        const items = allItems[refId] || [];

        document.getElementById('selectedRefText').textContent = 
            `Showing items for Reference ID: ${refId}`;

        document.querySelectorAll('#formsListTable tr').forEach(row => {
            row.classList.remove('active-row');
        });
        this.closest('tr').classList.add('active-row');

        let html = '';

        if(items.length > 0){
            html += `
                <table class="details-table">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            items.forEach(item => {
                html += `
                    <tr>
                        <td>${item.item_code}</td>
                        <td>${item.item_description}</td>
                        <td>${item.unit}</td>
                        <td>${item.quantity}</td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
        } else {
            html = `<div class="empty-state"><p>No items found for this submission.</p></div>`;
        }

        document.getElementById('detailsBody').innerHTML = html;
    });
});

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

let currentRejectingRef = null;

document.querySelectorAll('.status-select').forEach(select => {

    updateStatusColor(select, select.value);

    select.addEventListener('change', function(){
        const refId = this.dataset.ref;
        const newStatus = this.value;
        const dropdown = this;

        if(newStatus === 'rejected') {
            currentRejectingRef = refId;
            document.getElementById('rejectionReasonInput').value = '';
            document.getElementById('rejectionModal').style.display = 'flex';
            return; 
        }

        updateStatusColor(dropdown, newStatus);
        updateStatusOnServer(refId, newStatus, null, dropdown);
    });
});

function closeRejectionModal() {
    document.getElementById('rejectionModal').style.display = 'none';
}

function submitRejectionReason() {
    const reason = document.getElementById('rejectionReasonInput').value.trim();
    if(!reason) {
        alert('Please enter a reason for rejection.');
        return;
    }

    const dropdown = document.querySelector(`.status-select[data-ref="${currentRejectingRef}"]`);
    updateStatusColor(dropdown, 'rejected');
    dropdown.value = 'rejected';
    updateStatusOnServer(currentRejectingRef, 'rejected', reason, dropdown);

    closeRejectionModal();
}

function updateStatusOnServer(refId, status, rejectionReason, dropdown) {
    dropdown.disabled = true;

    fetch('update_form_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            reference_id: refId, 
            status: status,
            rejection_reason: rejectionReason || null
        })
    })
    .then(res => res.json())
    .then(data => {
        dropdown.disabled = false;
        const row = document.querySelector(`tr[data-ref="${refId}"]`);
        const approvedCell = row.querySelector('.approved-by');

        if(data.success){
            approvedCell.textContent = status === 'verified' ? '<?= $_SESSION['full_name'] ?>' : '-';

            if(status === 'rejected') {
                const detailsBody = document.getElementById('detailsBody');
                const reasonHtml = `<div class="rejection-reason" style="margin-top:10px; color:red;">
                    <strong>Rejection Reason:</strong> ${rejectionReason}
                </div>`;
                detailsBody.insertAdjacentHTML('beforeend', reasonHtml);
            }

            showToast(`Status updated to ${status.toUpperCase()}.`, 'success');
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(() => {
        dropdown.disabled = false;
        showToast('Network error. Please try again.', 'error');
    });
}

function updateStatusColor(element, status) {
    element.classList.remove('status-pending', 'status-verified', 'status-rejected');

    if (status === 'pending') {
        element.classList.add('status-pending');
    } else if (status === 'verified') {
        element.classList.add('status-verified');
    } else if (status === 'rejected') {
        element.classList.add('status-rejected');
    }
}

const filterNameInput = document.getElementById('filterName');
const filterDateInput = document.getElementById('filterDate');
const resetFiltersBtn = document.getElementById('resetFilters');

function filterTable() {
    const nameFilter = filterNameInput.value.toLowerCase();
    const dateFilter = filterDateInput.value;

    document.querySelectorAll('#formsListTable tbody tr').forEach(row => {
        const projectName = row.cells[1].textContent.toLowerCase();
        const submittedAt = row.cells[3].textContent; 
        
        let dateMatch = true;
        if(dateFilter) {
            const [year, month] = dateFilter.split('-');
            const rowDate = new Date(submittedAt);
            dateMatch = rowDate.getFullYear() === parseInt(year) && (rowDate.getMonth() + 1) === parseInt(month);
        }

        const nameMatch = projectName.includes(nameFilter);

        row.style.display = (nameMatch && dateMatch) ? '' : 'none';
    });
}

filterNameInput.addEventListener('input', filterTable);
filterDateInput.addEventListener('change', filterTable);

resetFiltersBtn.addEventListener('click', () => {
    filterNameInput.value = '';
    filterDateInput.value = '';
    filterTable();
});

const smrfModal = document.getElementById('smrfModal');

document.getElementById('openSmrfModal').addEventListener('click', () => {
    smrfModal.style.display = 'flex';
});

function closeSmrfModal() {
    smrfModal.style.display = 'none';
}

document.getElementById('smrfReference').addEventListener('change', function() {
    const ref = this.value;
    const tbody = document.querySelector('#smrfItemsTable tbody');
    tbody.innerHTML = '';

    if(ref && allItems[ref]){
        allItems[ref].forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" class="item-code" value="${item.item_code}"></td>
                <td><input type="text" value="${item.item_description}"></td>
                <td><input type="number" class="qty" value="${item.quantity}"></td>
                <td><input type="text" value="${item.unit}"></td>
                <td><input type="text"></td>
                <td><input type="text" class="legend" readonly></td>
                <td><input type="text" class="unit-cost" readonly></td>
                <td><input type="text" class="amount" readonly></td>
                <td><button class="remove-row btn-danger">x</button></td>
            `;
            tbody.appendChild(row);

            const itemCode = item.item_code;
            const qty = parseFloat(item.quantity) || 0;

            if(priceMap[itemCode] !== undefined){
                row.querySelector('.unit-cost').value = formatPeso(priceMap[itemCode]);
            }

            const unitCost = parsePeso(row.querySelector('.unit-cost').value);
            row.querySelector('.amount').value = formatPeso(qty * unitCost);
            
            if(legendMap[itemCode] !== undefined){
                row.querySelector('.legend').value = legendMap[itemCode]; 
            }
        });
    }
});

document.getElementById('addItemRow').addEventListener('click', () => {
    const tbody = document.querySelector('#smrfItemsTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="item-code"></td>
        <td><input type="text"></td>
        <td><input type="number" class="qty"></td>
        <td><input type="text"></td>
        <td><input type="text"></td>
        <td><input type="text"></td>
        <td><input type="text" class="unit-cost" readonly></td>
        <td><input type="text" class="amount" readonly></td>
        <td><button class="remove-row btn-danger">x</button></td>
    `;
    tbody.appendChild(row);
});

document.addEventListener('click', function(e){
    if(e.target.classList.contains('remove-row')){
        e.target.closest('tr').remove();
    }
});

document.getElementById('submitSmrf').addEventListener('click', () => {
    const smrfId = 'SMRF-' + Date.now(); 
    const referenceId = document.getElementById('smrfReference').value;
    const project = document.getElementById('smrfProject').value;
    const projectCode = document.getElementById('smrfProjectCode').value;
    const period = document.getElementById('smrfPeriod').value;

    const items = [];
    document.querySelectorAll('#smrfItemsTable tbody tr').forEach(row => {
        const cells = row.querySelectorAll('input');
        items.push({
            item_code: cells[0].value,
            item_description: cells[1].value,
            quantity: parseFloat(cells[2].value) || 0,
            unit: cells[3].value,
            remarks: cells[4].value,
            legend: cells[5].value,
            unit_cost: parsePeso(cells[6].value),
            amount: parsePeso(cells[7].value)
        });
    });

    fetch('save_smrf.php', {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify({ smrf_id: smrfId, reference_id: referenceId, project, project_code: projectCode, period, items })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            alert('SMRF created successfully!');
            location.reload();
        } else {
            alert(data.message || 'Failed to create SMRF.');
        }
    });
});

document.addEventListener('input', function(e){

    const row = e.target.closest('tr');
    if(!row) return;

    const itemCodeInput = row.querySelector('.item-code');
    const qtyInput = row.querySelector('.qty');
    const unitCostInput = row.querySelector('.unit-cost');
    const amountInput = row.querySelector('.amount');
    const legendInput = row.querySelector('.legend');

    if(!itemCodeInput || !qtyInput) return;

    const itemCode = itemCodeInput.value.trim();
    const qty = parseFloat(qtyInput.value) || 0;

    if(priceMap[itemCode] !== undefined){
        unitCostInput.value = formatPeso(priceMap[itemCode]);
    } else {
        unitCostInput.value = '';
    }

    const unitCost = parsePeso(unitCostInput.value);
    amountInput.value = formatPeso(qty * unitCost);
    
    if(legendMap[itemCode] !== undefined){
        legendInput.value = legendMap[itemCode];
    } else {
        legendInput.value = '';
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>