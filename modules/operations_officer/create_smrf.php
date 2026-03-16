<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);

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

$itemsQuery = $conn->query("
    SELECT reference_id, item_code, item_description, unit, quantity 
    FROM client_forms
    WHERE status = 'verified'
    ORDER BY id ASC
");
$allItems = [];
while($row = $itemsQuery->fetch_assoc()){
    $allItems[$row['reference_id']][] = $row;
}
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="page-header">
        <h1>Create SMRF</h1>
        <p class="page-subtitle">Create a new SMRF from verified forms or manually.</p>
    </div>

    <button class="btn-primary" id="openSmrfModal">Create SMRF</button>

    <div class="card table-card" style="margin-top:15px;">
        <div class="table-responsive">
            <table id="verifiedFormsTable">
                <thead>
                    <tr>
                        <th>Reference ID</th>
                        <th>Project</th>
                        <th>Submitted At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $verQuery = "
                        SELECT cf.reference_id, u.full_name as project, MIN(cf.created_at) as submitted_at
                        FROM client_forms cf
                        JOIN users u ON cf.user_id = u.id
                        WHERE cf.status = 'verified'
                        GROUP BY cf.reference_id
                        ORDER BY submitted_at DESC
                    ";
                    $verResult = $conn->query($verQuery);
                    while($row = $verResult->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['reference_id']) ?></td>
                            <td><?= htmlspecialchars($row['project']) ?></td>
                            <td><?= date("M Y", strtotime($row['submitted_at'])) ?></td>
                            <td><button class="btn-primary view-btn" data-ref="<?= htmlspecialchars($row['reference_id']) ?>">View</button></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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

<div id="toastContainer" class="toast-container"></div>

<script>
const allItems = <?= json_encode($allItems) ?>;
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
                <td><input type="text" value="${item.item_code}"></td>
                <td><input type="text" value="${item.item_description}"></td>
                <td><input type="number" value="${item.quantity}"></td>
                <td><input type="text" value="${item.unit}"></td>
                <td><input type="text"></td>
                <td><input type="text"></td>
                <td><input type="number"></td>
                <td><input type="number"></td>
                <td><button class="remove-row btn-danger">x</button></td>
            `;
            tbody.appendChild(row);
        });
    }
});

document.getElementById('addItemRow').addEventListener('click', () => {
    const tbody = document.querySelector('#smrfItemsTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text"></td>
        <td><input type="text"></td>
        <td><input type="number"></td>
        <td><input type="text"></td>
        <td><input type="text"></td>
        <td><input type="text"></td>
        <td><input type="number"></td>
        <td><input type="number"></td>
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
            unit_cost: parseFloat(cells[6].value) || 0,
            amount: parseFloat(cells[7].value) || 0
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
</script>

<?php require_once "../../layouts/footer.php"; ?>