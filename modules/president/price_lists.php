<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once __DIR__.'/../../vendor/autoload.php';

authorize(['purchasing_officer', 'president']);

use PhpOffice\PhpSpreadsheet\IOFactory;

$pageAlert = "";

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['price_list_file'])){
    $fileTmpPath = $_FILES['price_list_file']['tmp_name'];
    $fileName = $_FILES['price_list_file']['name'];
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

    if(in_array($fileExtension, ['xls','xlsx'])){
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        $inserted = 0;
        for($row=7; $row<=$highestRow; $row++){
            $reference_id = trim($sheet->getCell("A$row")->getValue());
            $item_code = trim($sheet->getCell("B$row")->getValue());
            $item_description = trim($sheet->getCell("C$row")->getValue());
            $unit = trim($sheet->getCell("D$row")->getValue());
            $unit_price = $sheet->getCell("E$row")->getCalculatedValue();
            $pmgi_unit_price = $sheet->getCell("F$row")->getCalculatedValue();

            if($reference_id && $item_code && $item_description && $unit && $unit_price && $pmgi_unit_price){

                $unit_price = floatval(str_replace([',','₱',' '], '', $unit_price));
                $pmgi_unit_price = floatval(str_replace([',','₱',' '], '', $pmgi_unit_price));

                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM price_lists WHERE reference_id = ? AND item_code = ? AND status = 'active'");
                $checkStmt->bind_param("ss", $reference_id, $item_code);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if($checkResult['count'] == 0){ 
                    $margin = 30.00;

                    $stmt = $conn->prepare("INSERT INTO price_lists 
                    (reference_id, item_code, item_description, unit, unit_price, margin_percent, pmgi_unit_price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssddd", 
                        $reference_id, 
                        $item_code, 
                        $item_description, 
                        $unit, 
                        $unit_price, 
                        $margin,
                        $pmgi_unit_price
                    );
                    $stmt->execute();
                    $stmt->close();
                    $inserted++;
                }
            }
        }

        if ($inserted > 0) {
            logAudit(
                $conn,
                $_SESSION['user_id'],
                "Uploaded $inserted price list items from file: $fileName",
                "Price List Management"
            );
        }

        $pageAlert = ['type'=>'success', 'message'=>"$inserted rows inserted successfully."];
    } else {
        $pageAlert = ['type'=>'error', 'message'=>"Invalid file format. Please upload an Excel file."];
    }
}

$limit = 12; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$searchParam = "%$search%";

if($search){
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM price_lists WHERE item_code LIKE ? OR item_description LIKE ? AND status = 'active'");
    $stmtCount->bind_param("ss", $searchParam, $searchParam);
} else {
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM price_lists WHERE status = 'active'");
}
$stmtCount->execute();
$totalResult = $stmtCount->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRows = $totalRow['total'];
$totalPages = ceil($totalRows / $limit);
$stmtCount->close();

if($search){
    $stmt = $conn->prepare("SELECT * FROM price_lists 
        WHERE item_code LIKE ? OR item_description LIKE ? AND status = 'active'
        ORDER BY reference_id ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
} else {
    $stmt = $conn->prepare("SELECT * FROM price_lists 
        WHERE status = 'active'
        ORDER BY reference_id ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$priceLists = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<link rel="icon" href="../../assets/images/stockbridge-logo.PNG">
<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="content-grid">
        <div class="table-card">

            <div class="page-header">
                <h1>Records of Price Lists</h1>
            </div>

            <?php if($pageAlert): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    showToast("<?= addslashes($pageAlert['message']) ?>", "<?= $pageAlert['type'] ?>");
                });
            </script>
            <?php endif; ?>

            <div class="table-header">
                

                <div class="header-center">
                    <div class="search-container">
                        <input type="text" id="filterInput"
                            placeholder="Search item code or description..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button id="clearSearch">&times;</button>
                    </div>
                </div>

                <div class="header-right">

                    <div class="bulk-controls">
                        <select id="bulkCategory">
                            <option value="">All</option>
                            <option value="SC">Supplies</option>
                            <option value="TE">Tools</option>
                        </select>

                        <input type="number" id="bulkMarginInput" step="0.01" placeholder="Margin %">

                        <button id="applyBulkMarginBtn" class="btn-primary">
                            Apply
                        </button>
                    </div>

                    <button id="addItemBtn" class="btn-primary add-btn">
                        + Add Item
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="priceListTable">
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Unit</th>
                        <th>Category</th>
                        <th>Unit Price</th>
                        <th>PMGI Price</th>
                        <th>Margin %</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($priceLists as $row): ?>
                    <tr data-id="<?= $row['id'] ?>" data-legend="<?= $row['legend'] ?>">
                        <td>
                            <input type="checkbox" class="rowCheckbox" value="<?= $row['id'] ?>">
                        </td>
                        <td><?= htmlspecialchars($row['item_code']) ?></td>
                        <td><?= htmlspecialchars($row['item_description']) ?></td>
                        <td><?= htmlspecialchars($row['unit']) ?></td>

                        <td>
                            <span class="<?= $row['legend']=='SC'?'legend-sc':'legend-te' ?>">
                                <?= $row['legend'] ?>
                            </span>
                        </td>

                        <td>₱ <?= number_format($row['unit_price'],2) ?></td>
                        <td>₱ <?= number_format($row['pmgi_unit_price'],2) ?></td>
                        <td><?= $row['margin_percent'] ?>%</td>

                        <td>
                            <span class="status-badge <?= $row['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>

                        <td>
                            <button class="btn-edit">Edit</button>
                            <button class="btn-delete" data-id="<?= $row['id'] ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if($totalPages > 1): ?>
                <div class="pagination">
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
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<div id="editModal" class="custom-modal">
    <div class="modal-content edit-modal">
        <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
        <h3>Edit Price List</h3>
        <form id="editForm">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label>Reference ID</label>
                <input type="text" name="reference_id" id="editReferenceId" required>
            </div>
            <div class="form-group">
                <label>Item Code</label>
                <input type="text" name="item_code" id="editItemCode" required>
            </div>
            <div class="form-group">
                <label>Item Description</label>
                <input type="text" name="item_description" id="editItemDescription" required>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <input type="text" name="unit" id="editUnit" required>
            </div>
            <div class="form-group">
                <label>Unit Price</label>
                <input type="number" step="0.01" name="unit_price" id="editUnitPrice" required>
            </div>
            <div class="form-group">
                <label>PMGI Unit Price</label>
                <input type="number" step="0.01" name="pmgi_unit_price" id="editPmgiUnitPrice" required>
            </div>

            <div class="form-group">
                <label>Margin (%)</label>
                <input type="number" step="0.01" name="margin_percent" id="editMargin" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px;">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
        <div id="editMessage" style="margin-top:10px;"></div>
    </div>
</div>

<div id="addModal" class="custom-modal">
    <div class="modal-content add-modal">
        <span class="close-btn" onclick="closeModal('addModal')">&times;</span>
        <h3>Add Price List Item</h3>
        <form id="addForm">
            <div class="form-group">
                <label>Item Code</label>
                <input type="text" name="item_code" id="addItemCode" required>
            </div>
            <div class="form-group">
                <label>Item Description</label>
                <input type="text" name="item_description" id="addItemDescription" required>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <input type="text" name="unit" id="addUnit" required>
            </div>
            <div class="form-group">
                <label>Unit Price</label>
                <input type="number" step="0.01" name="unit_price" id="addUnitPrice" required>
            </div>
            <div class="form-group">
                <label>PMGI Unit Price</label>
                <input type="number" step="0.01" name="pmgi_unit_price" id="addPmgiUnitPrice" required>
            </div>

            <div class="form-group">
                <label>Margin (%)</label>
                <input type="number" step="0.01" name="margin_percent" id="addMargin" value="30" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px;">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Item</button>
            </div>
        </form>
        <div id="addMessage" style="margin-top:10px;"></div>
    </div>
</div>

<div id="deleteModal" class="custom-modal">
    <div class="modal-content delete-modal">
        <span class="close-btn" onclick="closeModal('deleteModal')">&times;</span>
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this price list item?</p>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px;">
            <button type="button" class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
            <button type="button" id="confirmDeleteBtn" class="btn-primary">Delete</button>
        </div>
    </div>
</div>

<script>

const fileInput = document.getElementById('fileInput');
const fileName = document.getElementById('fileName');

fileInput.addEventListener('change', function(){
    if(this.files.length > 0){
        fileName.textContent = this.files[0].name;
    } else {
        fileName.textContent = "No file chosen";
    }
});

const addForm = document.getElementById('addForm');
const addMessage = document.getElementById('addMessage');

document.getElementById('addItemBtn').addEventListener('click', () => {
    document.getElementById('addModal').style.display = 'flex';
});

addForm.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);

    fetch('add_price_list.php', {
        method:'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            showToast('Price list item added successfully!', 'success');
            setTimeout(()=>{ location.reload(); }, 1000);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(err=>{
        addMessage.style.color = 'red';
        addMessage.textContent = 'Network error.';
    });
});

const editForm = document.getElementById('editForm');
const editMessage = document.getElementById('editMessage');

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('tr');
        document.getElementById('editId').value = row.dataset.id;
        document.getElementById('editReferenceId').value = row.children[0].textContent.trim();
        document.getElementById('editItemCode').value = row.children[1].textContent.trim();
        document.getElementById('editItemDescription').value = row.children[2].textContent.trim();
        document.getElementById('editUnit').value = row.children[3].textContent.trim();
        document.getElementById('editUnitPrice').value = row.children[4].textContent.replace(/[₱,\s]/g,'');
        document.getElementById('editPmgiUnitPrice').value = row.children[5].textContent.replace(/[₱,\s]/g,'');
        document.getElementById('editMargin').value = row.children[6].textContent.replace('%','').trim();

        document.getElementById('editModal').style.display = 'flex';
    });
});

function computePMGI(unitPriceInput, marginInput, outputInput) {
    const unitPrice = parseFloat(unitPriceInput.value) || 0;
    const margin = parseFloat(marginInput.value) || 0;

    const computed = unitPrice + (unitPrice * (margin / 100));
    outputInput.value = computed.toFixed(2);
}

const editUnitPrice = document.getElementById('editUnitPrice');
const editMargin = document.getElementById('editMargin');
const editPmgi = document.getElementById('editPmgiUnitPrice');

[editUnitPrice, editMargin].forEach(input => {
    input.addEventListener('input', () => {
        computePMGI(editUnitPrice, editMargin, editPmgi);
    });
});

const addUnitPrice = document.getElementById('addUnitPrice');
const addMargin = document.getElementById('addMargin');
const addPmgi = document.getElementById('addPmgiUnitPrice');

[addUnitPrice, addMargin].forEach(input => {
    input.addEventListener('input', () => {
        computePMGI(addUnitPrice, addMargin, addPmgi);
    });
});

editForm.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);

    fetch('../purchasing/update_price_list.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            showToast('Price list updated successfully!', 'success');
            setTimeout(()=>{ location.reload(); }, 1000);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(err=>{
        editMessage.style.color = 'red';
        editMessage.textContent = 'Network error.';
    });
});

let deleteId = null; 

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function() {
        deleteId = this.dataset.id;
        document.getElementById('deleteModal').style.display = 'flex';
    });
});

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!deleteId) return;
    fetch('../purchasing/delete_price_list.php', {
        method: 'POST',
        body: new URLSearchParams({ id: deleteId })
    })
    .then(res => res.json())
    .then(data => {
        closeModal('deleteModal');
        deleteId = null;
        if(data.success){
            showToast('Price list deleted successfully!', 'success');
            setTimeout(()=>{ location.reload(); }, 1000);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(err => {
        closeModal('deleteModal');
        deleteId = null;
        showToast('Network error.', 'error');
    });
});

const filterInput = document.getElementById('filterInput');
const clearSearchBtn = document.getElementById('clearSearch');

function toggleClearButton() {
    clearSearchBtn.style.display = filterInput.value.trim() ? 'block' : 'none';
}

toggleClearButton();

filterInput.addEventListener('keypress', function(e){
    if(e.key === 'Enter'){
        const searchValue = this.value.trim();
        const url = new URL(window.location.href);
        url.searchParams.set('search', searchValue);
        url.searchParams.set('page', 1); 
        window.location.href = url.href;
    }
});

filterInput.addEventListener('input', toggleClearButton);

clearSearchBtn.addEventListener('click', function(){
    filterInput.value = '';
    toggleClearButton();
    const url = new URL(window.location.href);
    url.searchParams.delete('search'); 
    url.searchParams.set('page', 1);   
    window.location.href = url.href;
});

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.classList.add('toast', `toast-${type}`);
    toast.textContent = message;
    
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3500);
}

// SELECT ALL
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.rowCheckbox').forEach(cb => {
        cb.checked = this.checked;
    });
});

// BULK APPLY
document.getElementById('applyBulkMarginBtn').addEventListener('click', function(){

    const margin = document.getElementById('bulkMarginInput').value;
    const category = document.getElementById('bulkCategory').value;

    if(!margin){
        showToast('Enter margin value', 'error');
        return;
    }

    let selectedIds = [];

    document.querySelectorAll('.rowCheckbox:checked').forEach(cb=>{
        selectedIds.push(cb.value);
    });

    fetch('bulk_update_margin.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            ids: selectedIds,
            category: category,
            margin: margin
        })
    })
    .then(res=>res.json())
    .then(data=>{
        if(data.success){
            showToast('Bulk update successful', 'success');
            setTimeout(()=>location.reload(),1000);
        }else{
            showToast(data.message,'error');
        }
    })
    .catch(()=>{
        showToast('Network error','error');
    });

});

document.querySelectorAll('.btn-toggle-status').forEach(btn => {
    btn.addEventListener('click', function(){

        const id = this.dataset.id;

        fetch('../purchasing/toggle_price_status.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                showToast('Status updated successfully', 'success');
                setTimeout(()=> location.reload(), 800);
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(() => {
            showToast('Network error', 'error');
        });

    });
});

</script>

<?php require_once "../../layouts/footer.php"; ?>