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

                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM price_lists WHERE reference_id = ? AND item_code = ?");
                $checkStmt->bind_param("ss", $reference_id, $item_code);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if($checkResult['count'] == 0){ 
                    $stmt = $conn->prepare("INSERT INTO price_lists 
                    (reference_id, item_code, item_description, unit, unit_price, pmgi_unit_price, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->bind_param("ssssdd", $reference_id, $item_code, $item_description, $unit, $unit_price, $pmgi_unit_price);
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
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM price_lists WHERE item_code LIKE ? OR item_description LIKE ?");
    $stmtCount->bind_param("ss", $searchParam, $searchParam);
} else {
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM price_lists");
}
$stmtCount->execute();
$totalResult = $stmtCount->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRows = $totalRow['total'];
$totalPages = ceil($totalRows / $limit);
$stmtCount->close();

if($search){
    $stmt = $conn->prepare("SELECT * FROM price_lists WHERE item_code LIKE ? OR item_description LIKE ? ORDER BY reference_id ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
} else {
    $stmt = $conn->prepare("SELECT * FROM price_lists ORDER BY reference_id ASC LIMIT ? OFFSET ?");
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
                <h1>Manage Price Lists</h1>
            </div>

            <?php if($pageAlert): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    showToast("<?= addslashes($pageAlert['message']) ?>", "<?= $pageAlert['type'] ?>");
                });
            </script>
            <?php endif; ?>

            <div class="table-header">
                <div class="header-left">
                    <form id="uploadForm" method="POST" enctype="multipart/form-data" class="upload-form">
                        <label class="file-upload">
                            <input type="file" name="price_list_file" id="fileInput" accept=".xls,.xlsx" required>
                            <span class="file-btn">📁 Choose File</span>
                            <span id="fileName" class="file-name">No file chosen</span>
                        </label>
                        <button type="submit" class="btn-primary upload-btn">Upload</button>
                    </form>
                    <span class="upload-label">
                        Upload Excel file (.xls / .xlsx)
                    </span>
                </div>

                <div class="header-center">
                    <div class="search-container">
                        <input type="text" id="filterInput"
                            placeholder="Search item code or description..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button id="clearSearch" title="Clear search">&times;</button>
                    </div>
                </div>

                <div class="header-right">
                    <button id="addItemBtn" class="btn-primary add-btn">
                        + Add Item
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="priceListTable">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Description</th>
                            <th>Unit</th>
                            <th>Unit Price <div class="subtext">at Cost with 12% Vat</div></th>
                            <th>PMGI Unit Price <div class="subtext">with 30% Margin</div></th>
                            <th>Status</th>
                            <th>Uploaded At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="priceTableBody">
                        <?php if (!empty($priceLists)): ?>
                            <?php foreach($priceLists as $row): ?>
                                <tr data-id="<?= $row['id'] ?>">
                                    <td><?= htmlspecialchars($row['item_code']) ?></td>
                                    <td><?= htmlspecialchars($row['item_description']) ?></td>
                                    <td><?= htmlspecialchars($row['unit']) ?></td>
                                    <td><?= '₱ ' . number_format((float)$row['unit_price'], 2) ?></td>
                                    <td><?= '₱ ' . number_format((float)$row['pmgi_unit_price'], 2) ?></td>
                                    <td>
                                        <span class="status-badge <?= $row['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date("M d, Y H:i", strtotime($row['uploaded_at'])) ?></td>
                                    <td>
                                        <button class="btn-edit">Edit</button>
                                        <button class="btn-toggle-status" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-status="<?= $row['status'] ?>">
                                            <?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                        <button class="btn-delete" data-id="<?= $row['id'] ?>">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;">No price list records found.</td>
                            </tr>
                        <?php endif; ?>
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

        document.getElementById('editModal').style.display = 'flex';
    });
});

editForm.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);

    fetch('update_price_list.php', {
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
    fetch('delete_price_list.php', {
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

document.querySelectorAll('.btn-toggle-status').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;

        fetch('toggle_price_status.php', {
            method: 'POST',
            body: new URLSearchParams({ id })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                showToast('Status updated successfully!', 'success');
                setTimeout(()=> location.reload(), 800);
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'));
    });
});
</script>

<?php require_once "../../layouts/footer.php"; ?>