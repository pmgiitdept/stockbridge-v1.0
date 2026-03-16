<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['client']);

$userId = $_SESSION['user_id'];

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmtCount = $conn->prepare("
    SELECT COUNT(DISTINCT reference_id) as total 
    FROM client_forms 
    WHERE user_id = ?
");
$stmtCount->bind_param("i", $userId);
$stmtCount->execute();
$totalResult = $stmtCount->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRows = $totalRow['total'];
$totalPages = ceil($totalRows / $limit);
$stmtCount->close();

$query = "
    SELECT reference_id, status, MIN(created_at) as submitted_at
    FROM client_forms
    WHERE user_id = ?
    GROUP BY reference_id, status
    ORDER BY submitted_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $userId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$submissions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$itemsQuery = $conn->prepare("
    SELECT reference_id, item_code, item_description, unit, quantity 
    FROM client_forms 
    WHERE user_id = ? 
    ORDER BY id ASC
");
$itemsQuery->bind_param("i", $userId);
$itemsQuery->execute();
$itemsResult = $itemsQuery->get_result();
$allItems = [];
while($row = $itemsResult->fetch_assoc()){
    $allItems[$row['reference_id']][] = $row;
}
$itemsQuery->close();
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="content-grid">
        <div class="table-card">
            <div class="page-header">
                <h1>Submitted Forms</h1>
                <p class="page-subtitle">View all forms you have submitted. Click 'View' to see the items or request again if verified.</p>
            </div>

            <div class="table-responsive">
                <table id="submittedFormsTable">
                    <thead>
                        <tr>
                            <th>Reference ID</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Approved By</th>
                            <th>Action</th>
                            <th>Proceed Request</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($submissions)): ?>
                            <?php foreach($submissions as $submission): ?>
                                <tr>
                                    <td><?= htmlspecialchars($submission['reference_id']) ?></td>
                                    <td>
                                        <?php 
                                            $status = strtolower($submission['status']);
                                            $statusClass = "status-badge ";
                                            if($status === 'pending') $statusClass .= "status-pending";
                                            elseif($status === 'rejected') $statusClass .= "status-rejected";
                                            elseif($status === 'approved' || $status === 'verified') $statusClass .= "status-approved";
                                        ?>
                                        <span class="<?= $statusClass ?>">
                                            <?= htmlspecialchars(ucfirst($submission['status'])) ?>
                                        </span>
                                    </td>

                                    <td><?= date("M d, Y H:i", strtotime($submission['submitted_at'])) ?></td>
                                    <td>
                                        <?php
                                            $approvedBy = '-';
                                        ?>
                                        <?= htmlspecialchars($approvedBy) ?>
                                    </td>
                                    <td>
                                        <button class="btn-primary" data-ref="<?= $submission['reference_id'] ?>">View</button>
                                    </td>

                                    <td>
                                        <?php if(strtolower($submission['status']) === 'verified'): ?>
                                            <a href="create_forms.php?ref=<?= urlencode($submission['reference_id']) ?>" 
                                               class="btn-primary" style="background:#2ecc71; border-color:#2ecc71;">
                                               Request Again
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#999;">-</span>
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
                <div class="pagination">
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
</div>

<div id="viewModal" class="custom-modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <h3>Form Items</h3>
        <div id="modalBody"></div>
    </div>
</div>

<script>
const allItems = <?php echo json_encode($allItems); ?>;

function closeModal() {
    document.getElementById('viewModal').style.display = 'none';
}

document.querySelectorAll('.btn-primary').forEach(btn => {
    if(btn.dataset.ref){
        btn.addEventListener('click', function(){
            const refId = this.dataset.ref;
            const items = allItems[refId] || [];
            let html = '';

            if(items.length > 0){
                html += `<table class="modal-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>`;
                items.forEach(item => {
                    html += `<tr>
                                <td>${item.item_code}</td>
                                <td>${item.item_description}</td>
                                <td>${item.unit}</td>
                                <td>${item.quantity}</td>
                             </tr>`;
                });
                html += `</tbody></table>`;
            } else {
                html = "<p>No items found for this submission.</p>";
            }

            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('viewModal').style.display = 'flex';
        });
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>