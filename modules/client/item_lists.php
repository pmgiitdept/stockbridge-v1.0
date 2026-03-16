<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['client']);

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
    $stmt = $conn->prepare("SELECT reference_id, item_code, item_description, unit 
                            FROM price_lists 
                            WHERE item_code LIKE ? OR item_description LIKE ? 
                            ORDER BY reference_id ASC 
                            LIMIT ? OFFSET ?");
    $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
} else {
    $stmt = $conn->prepare("SELECT reference_id, item_code, item_description, unit 
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
                <h1>Item Lists</h1>
                <p class="page-subtitle">View all available items. All items are referenced for submitting forms.</p>
            </div>

            <div class="table-header">
                <div class="search-container">
                    <input type="text" id="filterInput" placeholder="Search by Item Code or Description" value="<?= htmlspecialchars($search) ?>">
                    <button id="clearSearch" title="Clear search">&times;</button>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center;">No items found.</td>
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

                    <span>Page <?= $page ?> of <?= $totalPages ?></span>

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
</script>

<?php require_once "../../layouts/footer.php"; ?>
