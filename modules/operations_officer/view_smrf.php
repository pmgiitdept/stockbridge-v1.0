<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'president', 'operations_manager', 'purchasing_officer']);

$userName = $_SESSION['full_name'] ?? '';

$rowsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $rowsPerPage;

$countQuery = "SELECT COUNT(*) as total FROM smrf_forms";
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

$query = "
    SELECT sf.smrf_id, sf.reference_id, sf.project, sf.project_code, sf.period, sf.status, sf.created_at, u.full_name as created_by
    FROM smrf_forms sf
    JOIN users u ON sf.created_by = u.id
    ORDER BY sf.created_at DESC
    LIMIT $rowsPerPage OFFSET $offset
";
$result = $conn->query($query);
$smrfs = $result->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="page-header">
        <h1>SMRF List</h1>
        <p class="page-subtitle">All created SMRF forms and their current status.</p>
    </div>

    <div class="table-card">
        <div class="table-filters" style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <input type="text" id="filterSMRF" placeholder="Search by SMRF ID / Project" class="filter-input">
                <input type="month" id="filterPeriod" class="filter-input">
                <button id="resetFilters" class="btn-primary">Reset</button>
            </div>
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
                                    <span class="status-badge status-<?= $smrf['status'] ?>"><?= ucfirst($smrf['status']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($smrf['created_by']) ?></td>
                                <td><?= date("M d, Y H:i", strtotime($smrf['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No SMRF forms found.</td>
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
                    <a href="?<?= http_build_query($queryParams) ?>">&laquo; Previous</a>
                <?php endif; ?>

                <span>Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if($page < $totalPages): ?>
                    <?php 
                    $queryParams['page'] = $page + 1;
                    ?>
                    <a href="?<?= http_build_query($queryParams) ?>">Next &raquo;</a>
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

<div id="createPrModal" class="custom-modal">
    <div class="modal-content" style="max-width: 1100px; width: 95%;">
        <span class="close-btn" onclick="closeCreatePrModal()">&times;</span>
        <h3>Create Purchase Requisition (PR)</h3>

        <div class="pr-form-grid">
            <div>
                <label>PR Date</label>
                <input type="date" id="prDate" class="filter-input">
            </div>

            <div>
                <label>Requesting Department</label>
                <input type="text" id="prDepartment" class="filter-input" placeholder="e.g. Operations">
            </div>

            <div>
                <label>Project</label>
                <input type="text" id="prProject" class="filter-input">
            </div>

            <div>
                <label>Purpose of Requisition</label>
                <input type="text" id="prPurpose" class="filter-input">
            </div>

            <div>
                <label>Requested By</label>
                <input type="text" id="prRequestedBy" class="filter-input" readonly>
            </div>

            <div>
                <label>Reviewed By</label>
                <input type="text" id="prReviewedBy" class="filter-input">
            </div>

            <div>
                <label>Approved By</label>
                <input type="text" id="prApprovedBy" class="filter-input">
            </div>

            <div>
                <label>Received By</label>
                <input type="text" id="prReceivedBy" class="filter-input">
            </div>
        </div>

        <hr style="margin: 20px 0;">

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:15px;">
            <input type="month" id="prPeriod" class="filter-input">
            <button id="loadSmrfByPeriod" class="btn-primary">Load SMRF</button>
            <label class="toggle-switch" style="margin-left:auto;">
                <input type="checkbox" id="mergeItemsToggle">
                <span class="slider"></span>
                <span class="label-text">Merge Similar Items</span>
            </label>
        </div>

        <div id="smrfCheckboxContainer" style="margin-bottom:15px;"></div>

        <div class="smrf-table-wrapper">
            <table id="prItemsTable">
                <thead>
                    <tr>
                        <th>Source SMRF</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Item Description</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" style="text-align:center;">Select a period to load items.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="form-group">
            <label for="prJustification" class="form-label">Justification</label>
            <textarea id="prJustification" class="form-textarea" rows="3" placeholder="Enter justification for this PR"></textarea>
        </div>

        <div style="margin-top:20px; text-align:right;">
            <button class="btn-primary" onclick="submitPR()">Save PR</button>
        </div>

    </div>
</div>

<script>
const VAT_RATE = 1.12;

const filterInput = document.getElementById('filterSMRF');
const filterPeriodInput = document.getElementById('filterPeriod');
const resetBtn = document.getElementById('resetFilters');

function filterTable() {
    const search = filterInput.value.toLowerCase();
    const period = filterPeriodInput.value;

    document.querySelectorAll('#smrfTable tbody tr').forEach(row => {
        const smrfId = row.cells[0].textContent.toLowerCase();
        const project = row.cells[2].textContent.toLowerCase();
        const rowPeriod = row.cells[4].textContent;

        let periodMatch = true;
        if(period) {
            const [year, month] = period.split('-');
            const date = new Date(rowPeriod + " 1");
            periodMatch = date.getFullYear() === parseInt(year) && (date.getMonth() + 1) === parseInt(month);
        }

        const searchMatch = smrfId.includes(search) || project.includes(search);

        row.style.display = (searchMatch && periodMatch) ? '' : 'none';
    });
}

filterInput.addEventListener('input', filterTable);
filterPeriodInput.addEventListener('change', filterTable);
resetBtn.addEventListener('click', () => {
    filterInput.value = '';
    filterPeriodInput.value = '';
    filterTable();
});

const smrfDetailsModal = document.getElementById('smrfDetailsModal');
const smrfDetailsContent = document.getElementById('smrfDetailsContent');

function openSmrfDetailsModal(smrfId, referenceId) {
    smrfDetailsModal.style.display = 'flex';
    smrfDetailsContent.innerHTML = `<p>Loading...</p>`;

    fetch('fetch_smrf_details.php', {
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

const createPrModal = document.getElementById('createPrModal');
const prItemsTableBody = document.querySelector('#prItemsTable tbody');
let loadedSmrfItems = [];

const openPrModalBtn = document.getElementById('openPrModal');

const loggedInUser = <?= json_encode($userName) ?>;

if (openPrModalBtn) {
    openPrModalBtn.addEventListener('click', () => {
        createPrModal.style.display = 'flex';

        const requestedByInput = document.getElementById('prRequestedBy');
        if (requestedByInput) {
            requestedByInput.value = loggedInUser;
        }
    });
}

function closeCreatePrModal() {
    createPrModal.style.display = 'none';
    prItemsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Select a period to load items.</td></tr>`;
    document.getElementById('smrfCheckboxContainer').innerHTML = '';
    loadedSmrfItems = [];
}

document.getElementById('loadSmrfByPeriod').addEventListener('click', () => {
    const period = document.getElementById('prPeriod').value;
    if (!period) {
        alert('Please select a period.');
        return;
    }

    fetch('fetch_smrf_by_period.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ period })
    })
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById('smrfCheckboxContainer');
        container.innerHTML = '';

        if (!data.success || data.smrfs.length === 0) {
            container.innerHTML = '<p>No SMRF found for this period.</p>';
            return;
        }

        let html = `
            <div class="smrf-checkbox-item">
                <input type="checkbox" id="selectAllSmrf">
                <strong>Select All SMRF</strong>
            </div>
        `;

        data.smrfs.forEach(smrf => {
            html += `
                <div class="smrf-checkbox-item">
                    <input type="checkbox" class="smrfCheckbox" value="${smrf.smrf_id}">
                    <span>${smrf.smrf_id} - ${smrf.project}</span>
                </div>
            `;
        });

        container.innerHTML = html;

        document.getElementById('selectAllSmrf').addEventListener('change', function() {
            document.querySelectorAll('.smrfCheckbox').forEach(cb => cb.checked = this.checked);
            loadSelectedSmrfItems();
        });

        document.querySelectorAll('.smrfCheckbox').forEach(cb => {
            cb.addEventListener('change', loadSelectedSmrfItems);
        });
    });
});

function loadSelectedSmrfItems() {
    const selected = Array.from(document.querySelectorAll('.smrfCheckbox:checked'))
        .map(cb => cb.value);
    
    const periodInput = document.getElementById('prPeriod').value;
    const period = periodInput ? periodInput + "-01" : null;

    if (selected.length === 0) {
        prItemsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No SMRF selected.</td></tr>`;
        return;
    }

    fetch('fetch_smrf_items_for_pr.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ smrf_ids: selected, period: period })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) return;

        loadedSmrfItems = data.items;
        renderPrItems();
    });
}

document.getElementById('mergeItemsToggle').addEventListener('change', renderPrItems);

function renderPrItems() {
    const merge = document.getElementById('mergeItemsToggle').checked;
    let items = [...loadedSmrfItems];

    if (merge) {
        const grouped = {};
        items.forEach(item => {
            const key = item.item_description + '|' + item.unit;
            if (!grouped[key]) {
                grouped[key] = { ...item };
            } else {
                grouped[key].quantity = parseFloat(grouped[key].quantity) + parseFloat(item.quantity);
            }
        });
        items = Object.values(grouped);
    }

    let html = '';
    items.forEach(item => {
        html += `
            <tr>
                <td>${item.smrf_id}</td>
                <td>${item.quantity}</td>
                <td>${item.unit}</td>
                <td>${item.item_description}</td>
                <td>${item.remarks || ''}</td>
            </tr>
        `;
    });

    prItemsTableBody.innerHTML = html || `<tr><td colspan="6" style="text-align:center;">No items found.</td></tr>`;
}

function submitPR() {
    const period = document.getElementById('prPeriod').value;
    const date = document.getElementById('prDate').value;
    const department = document.getElementById('prDepartment').value;
    const project = document.getElementById('prProject').value;
    const purpose = document.getElementById('prPurpose').value;
    const requestedBy = document.getElementById('prRequestedBy').value;
    const reviewedBy = document.getElementById('prReviewedBy').value;
    const approvedBy = document.getElementById('prApprovedBy').value;
    const receivedBy = document.getElementById('prReceivedBy').value;
    const justification = document.getElementById('prJustification').value.trim();

    if (!period || !date || !department) {
        alert("Please fill required fields (Period, Date, Department).");
        return;
    }

    const rows = document.querySelectorAll('#prItemsTable tbody tr');
    let items = [];

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 5) return;

        items.push({
            quantity: cells[1].textContent.trim(),
            unit: cells[2].textContent.trim(),
            item_description: cells[3].textContent.trim(),
            remarks: cells[4].textContent.trim()
        });
    });

    if (items.length === 0) {
        alert("No items to save.");
        return;
    }

    fetch('create_pr.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            period: period + "-01",
            date: date,
            requesting_department: department,
            project: project,
            purpose_of_requisition: purpose,
            requested_by: requestedBy,
            reviewed_by: reviewedBy,
            approved_by: approvedBy,
            received_by: receivedBy,
            justification: justification,
            items: items
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`PR Created Successfully!\nPR ID: ${data.pr_id}`);
            closeCreatePrModal();
            location.reload();
        } else {
            alert(data.message || "Failed to create PR.");
        }
    })
    .catch(() => {
        alert("Network error. Please try again.");
    });
}

function exportSmrfPDF(smrfId, referenceId) {

    const params = new URLSearchParams({
        smrf_id: smrfId,
        reference_id: referenceId
    });

    window.open(`export_smrf_pdf.php?${params.toString()}`, '_blank');

}
</script>

<?php require_once "../../layouts/footer.php"; ?>