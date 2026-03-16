<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);

$limit = 12; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$legend = $_GET['legend'] ?? '';
$searchParam = "%$search%";

if($search && $legend){
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM price_lists 
        WHERE (item_code LIKE ? OR item_description LIKE ?) AND legend = ?");
    $stmtCount->bind_param("sss", $searchParam, $searchParam, $legend);
}
elseif($search){
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM price_lists 
        WHERE item_code LIKE ? OR item_description LIKE ?");
    $stmtCount->bind_param("ss", $searchParam, $searchParam);
}
elseif($legend){
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM price_lists WHERE legend = ?");
    $stmtCount->bind_param("s", $legend);
}
else{
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM price_lists");
}

$stmtCount->execute();
$totalResult = $stmtCount->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRows = $totalRow['total'];
$totalPages = ceil($totalRows / $limit);
$stmtCount->close();

if($search && $legend){
    $stmt = $conn->prepare("SELECT id, reference_id, item_code, item_description, unit,
                            unit_price, pmgi_unit_price, legend
                            FROM price_lists
                            WHERE (item_code LIKE ? OR item_description LIKE ?) AND legend = ?
                            ORDER BY reference_id ASC
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii", $searchParam, $searchParam, $legend, $limit, $offset);
}
elseif($search){
    $stmt = $conn->prepare("SELECT id, reference_id, item_code, item_description, unit,
                            unit_price, pmgi_unit_price, legend
                            FROM price_lists
                            WHERE item_code LIKE ? OR item_description LIKE ?
                            ORDER BY reference_id ASC
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
}
elseif($legend){
    $stmt = $conn->prepare("SELECT id, reference_id, item_code, item_description, unit,
                            unit_price, pmgi_unit_price, legend
                            FROM price_lists
                            WHERE legend = ?
                            ORDER BY reference_id ASC
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("sii", $legend, $limit, $offset);
}
else{
    $stmt = $conn->prepare("SELECT id, reference_id, item_code, item_description, unit,
                            unit_price, pmgi_unit_price, legend
                            FROM price_lists
                            ORDER BY reference_id ASC
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="content-grid">
        <div class="table-card">

            <div class="page-header">
                <h1>Master Lists</h1>
                <p class="page-subtitle">View all available items. All items are referenced for the costing of SMRFs.</p>
            </div>

            <div class="table-header">
                <div class="search-container">
                    <input type="text" id="filterInput" placeholder="Search by Item Code or Description" value="<?= htmlspecialchars($search) ?>">
                    <button id="clearSearch" title="Clear search">&times;</button>
                </div>

                <div class="legend-filter">
                    <select id="legendFilter" class="legend-filter-select">
                        <option value="">All Legends</option>
                        <option value="SC" <?= $legend=='SC'?'selected':'' ?>>SC</option>
                        <option value="TE" <?= $legend=='TE'?'selected':'' ?>>TE</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table id="itemListTable">
                    <thead>
                        <tr>
                            <th>Item No.</th>
                            <th>Item Code</th>
                            <th>Item Description</th>
                            <th>Unit</th>
                            <th>Legend</th>
                            <th>Unit Price <div class="subtext">at Cost with 12% Vat</div></th>
                            <th>PMGI Unit Price <div class="subtext">with 30% Margin</div></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach($items as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['reference_id']) ?></td>
                                    <td><?= htmlspecialchars($row['item_code']) ?></td>
                                    <td><?= htmlspecialchars($row['item_description']) ?></td>
                                    <td><?= htmlspecialchars($row['unit']) ?></td>
                                    <td class="legend-cell">
                                        <select class="legend-select" data-id="<?= $row['id'] ?>">
                                            <option value="SC" <?= $row['legend']=='SC'?'selected':'' ?>>SC</option>
                                            <option value="TE" <?= $row['legend']=='TE'?'selected':'' ?>>TE</option>
                                        </select>
                                    </td>
                                    <td><?= '₱ ' . number_format((float)$row['unit_price'], 2) ?></td>
                                    <td><?= '₱ ' . number_format((float)$row['pmgi_unit_price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">No items found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($totalPages > 1): ?>
            <div class="pagination">

                <?php if($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&legend=<?= urlencode($legend) ?>">
                        &laquo; Previous
                    </a>
                <?php endif; ?>

                <span>Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&legend=<?= urlencode($legend) ?>">
                        Next &raquo;
                    </a>
                <?php endif; ?>

            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
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

document.querySelectorAll('.legend-select').forEach(select => {
    select.addEventListener('change', function(){

        const id = this.dataset.id;
        const legend = this.value;

        fetch('update_legend.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                id:id,
                legend:legend
            })
        })
        .then(res=>res.json())
        .then(data=>{
            if(!data.success){
                alert('Failed to update legend.');
            }
        })
        .catch(()=>{
            alert('Network error.');
        });

    });

});

function updateLegendColor(select){
    select.classList.remove('legend-sc','legend-te');

    if(select.value === 'SC'){
        select.classList.add('legend-sc');
    }else{
        select.classList.add('legend-te');
    }
}

document.querySelectorAll('.legend-select').forEach(select=>{
    updateLegendColor(select);

    select.addEventListener('change',function(){
        updateLegendColor(this);
    });
});

const legendFilter = document.getElementById('legendFilter');

legendFilter.addEventListener('change', function(){
    const url = new URL(window.location.href);

    if(this.value){
        url.searchParams.set('legend', this.value);
    }else{
        url.searchParams.delete('legend');
    }

    url.searchParams.set('page', 1);
    window.location.href = url.href;

});
</script>

<?php require_once "../../layouts/footer.php"; ?>
