<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['client']);

$priceListQuery = $conn->query("SELECT reference_id, item_code, item_description, unit FROM price_lists ORDER BY item_description ASC");
$priceLists = $priceListQuery->fetch_all(MYSQLI_ASSOC);

$prefillRows = [];
if(isset($_GET['ref'])){
    $refId = $_GET['ref'];
    $prefillQuery = $conn->prepare("SELECT item_code, item_description, unit, quantity FROM client_forms WHERE reference_id = ? AND user_id = ?");
    $prefillQuery->bind_param("si", $refId, $_SESSION['user_id']);
    $prefillQuery->execute();
    $prefillResult = $prefillQuery->get_result();
    while($row = $prefillResult->fetch_assoc()){
        $prefillRows[] = $row;
    }
    $prefillQuery->close();
}
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="content-grid">
        <div class="table-card">

            <div class="page-header">
                <h1>Create Form</h1>
                <p class="page-subtitle">Select items by code OR type description with suggestions.</p>
            </div>

            <?php if(isset($_GET['ref']) && !empty($prefillRows)): ?>
                <div class="info-banner">
                    Prefilled from previous request: <strong><?= htmlspecialchars($_GET['ref']) ?></strong>
                </div>
            <?php endif; ?>

            <form id="clientForm">
                <table id="formTable">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Description (Searchable)</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="formTableBody">
                        <?php if(!empty($prefillRows)): ?>
                            <?php foreach($prefillRows as $row): ?>
                                <tr class="form-row">
                                    <td>
                                        <select class="item-code" required>
                                            <option value="">-- Select Item --</option>
                                            <?php foreach($priceLists as $item): ?>
                                                <option 
                                                    data-description="<?= htmlspecialchars($item['item_description']) ?>" 
                                                    data-unit="<?= htmlspecialchars($item['unit']) ?>" 
                                                    value="<?= htmlspecialchars($item['item_code']) ?>"
                                                    <?= $item['item_code'] === $row['item_code'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($item['item_code']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="autocomplete-wrapper">
                                            <input type="text" class="item-description" value="<?= htmlspecialchars($row['item_description']) ?>" placeholder="Type item description..." required>
                                            <div class="autocomplete-list"></div>
                                        </div>
                                    </td>
                                    <td><input type="text" class="item-unit" value="<?= htmlspecialchars($row['unit']) ?>" readonly></td>
                                    <td><input type="number" class="item-quantity" value="<?= htmlspecialchars($row['quantity']) ?>" min="1" required></td>
                                    <td><button type="button" class="btn-remove">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="form-row">
                                <td>
                                    <select class="item-code" required>
                                        <option value="">-- Select Item --</option>
                                        <?php foreach($priceLists as $item): ?>
                                            <option 
                                                data-description="<?= htmlspecialchars($item['item_description']) ?>" 
                                                data-unit="<?= htmlspecialchars($item['unit']) ?>" 
                                                value="<?= htmlspecialchars($item['item_code']) ?>">
                                                <?= htmlspecialchars($item['item_code']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="autocomplete-wrapper">
                                        <input type="text" class="item-description" placeholder="Type item description..." required>
                                        <div class="autocomplete-list"></div>
                                    </div>
                                </td>
                                <td><input type="text" class="item-unit" readonly></td>
                                <td><input type="number" class="item-quantity" min="1" required></td>
                                <td><button type="button" class="btn-remove">Remove</button></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top: 15px; display:flex; gap:10px;">
                    <button type="button" id="addRowBtn" class="btn-primary">+ Add Item</button>
                    <button type="submit" class="btn-primary">Submit Form</button>
                </div>
                <div id="formMessage" style="margin-top:10px;"></div>
            </form>

        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

</div>

<script>
const addRowBtn = document.getElementById('addRowBtn');
const formTableBody = document.getElementById('formTableBody');
const priceLists = <?php echo json_encode($priceLists); ?>;

addRowBtn.addEventListener('click', () => {
    const newRow = document.createElement('tr');
    newRow.classList.add('form-row');
    newRow.innerHTML = `
        <td>
            <select class="item-code" required>
                <option value="">-- Select Item --</option>
                ${priceLists.map(item => 
                    `<option value="${item.item_code}" data-description="${item.item_description}" data-unit="${item.unit}">
                        ${item.item_code}
                    </option>`).join('')}
            </select>
        </td>
        <td>
            <div class="autocomplete-wrapper">
                <input type="text" class="item-description" placeholder="Type item description..." required>
                <div class="autocomplete-list"></div>
            </div>
        </td>
        <td><input type="text" class="item-unit" readonly></td>
        <td><input type="number" class="item-quantity" min="1" required></td>
        <td><button type="button" class="btn-remove">Remove</button></td>
    `;
    formTableBody.appendChild(newRow);
    attachRowEvents(newRow);
});

function showToast(message, type = 'warning') {
    const container = document.getElementById('toastContainer');

    const toast = document.createElement('div');
    toast.classList.add('toast', `toast-${type}`);
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3600);
}

function attachRowEvents(row) {
    const codeSelect = row.querySelector('.item-code');
    const descInput = row.querySelector('.item-description');
    const unitInput = row.querySelector('.item-unit');
    const removeBtn = row.querySelector('.btn-remove');
    const autoList = row.querySelector('.autocomplete-list');

    codeSelect.addEventListener('change', function() {
        const selected = this.selectedOptions[0];
        descInput.value = selected.dataset.description || '';
        unitInput.value = selected.dataset.unit || '';
    });

    descInput.addEventListener('input', function() {
        const search = this.value.toLowerCase();
        autoList.innerHTML = '';
        if(!search) {
            autoList.style.display = 'none';
            return;
        }

        const matches = priceLists.filter(item =>
            item.item_description.toLowerCase().includes(search)
        ).slice(0, 8);

        matches.forEach(item => {
            const div = document.createElement('div');
            div.classList.add('autocomplete-item');
            div.textContent = item.item_description;
            div.addEventListener('click', () => {
                descInput.value = item.item_description;
                unitInput.value = item.unit;
                codeSelect.value = item.item_code;
                autoList.style.display = 'none';
            });
            autoList.appendChild(div);
        });

        autoList.style.display = matches.length ? 'block' : 'none';
    });

    document.addEventListener('click', (e) => {
        if(!row.contains(e.target)){
            autoList.style.display = 'none';
        }
    });

    removeBtn.addEventListener('click', function() {
        if (formTableBody.rows.length > 1) {
            row.remove();
            showToast('Item row removed.', 'success');
        } else {
            showToast('At least one item row is required.', 'warning');
        }
    });
}

document.querySelectorAll('.form-row').forEach(row => {
    attachRowEvents(row);

    const select = row.querySelector('.item-code');
    if(select && select.value){
        select.dispatchEvent(new Event('change'));
    }
});

document.getElementById('clientForm').addEventListener('submit', function(e){
    e.preventDefault();

    const loader = document.getElementById("page-loader");
    loader.classList.remove("hidden");

    const rows = Array.from(document.querySelectorAll('.form-row'));
    const formData = rows.map(row => ({
        item_code: row.querySelector('.item-code').value,
        item_description: row.querySelector('.item-description').value,
        unit: row.querySelector('.item-unit').value,
        quantity: parseInt(row.querySelector('.item-quantity').value)
    }));

    for (const r of formData) {
        if (!r.item_code || !r.item_description || !r.quantity) {
            loader.classList.add("hidden");
            showToast('Please fill all required fields.', 'warning');
            return;
        }
    }

    fetch('submit_form.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(res => res.json())
    .then(data => {
        loader.classList.add("hidden");

        if(data.success){
            showToast(data.message, 'success');
            formTableBody.innerHTML = '';
            addRowBtn.click(); 
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => {
        loader.classList.add("hidden");
        showToast('Network error. Please try again.', 'error');
    });
});
</script>

<?php require_once "../../layouts/footer.php"; ?>