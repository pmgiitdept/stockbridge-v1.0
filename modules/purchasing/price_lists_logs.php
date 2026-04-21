<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['purchasing_officer', 'purchasing_manager', 'president']);

$limit = 12; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$selectedMonth   = $_GET['month'] ?? '';
$userFilter      = $_GET['user'] ?? '';
$fieldFilter     = $_GET['field'] ?? '';
$referenceFilter = $_GET['reference_id'] ?? '';
$itemCodeFilter  = $_GET['item_code'] ?? '';

$where = ["l.module = 'Price List Management'"];
$params = [];
$types = "";

if(!empty($selectedMonth)) {
    $where[] = "DATE_FORMAT(l.created_at,'%Y-%m') = ?";
    $params[] = $selectedMonth;
    $types .= "s";
}
if(!empty($userFilter)) {
    $where[] = "u.full_name LIKE ?";
    $params[] = "%$userFilter%";
    $types .= "s";
}
if(!empty($fieldFilter)) {
    $where[] = "d.field_name LIKE ?";
    $params[] = "%$fieldFilter%";
    $types .= "s";
}
if(!empty($referenceFilter)) {
    $where[] = "d.reference_id LIKE ?";
    $params[] = "%$referenceFilter%";
    $types .= "s";
}
if(!empty($itemCodeFilter)) {
    $where[] = "d.item_code LIKE ?";
    $params[] = "%$itemCodeFilter%";
    $types .= "s";
}

$whereSQL = implode(" AND ", $where);

$sqlCount = "SELECT COUNT(*) as total 
             FROM price_list_audit_details d
             JOIN audit_logs l ON d.audit_log_id = l.id
             JOIN users u ON l.user_id = u.id
             WHERE $whereSQL";

$totalStmt = $conn->prepare($sqlCount);
if(!empty($params)) $totalStmt->bind_param($types, ...$params);
$totalStmt->execute();
$totalResult = $totalStmt->get_result()->fetch_assoc();
$totalRows = $totalResult['total'];
$totalPages = ceil($totalRows / $limit);

$sqlData = "SELECT d.*, l.created_at, u.full_name as user_name
            FROM price_list_audit_details d
            JOIN audit_logs l ON d.audit_log_id = l.id
            JOIN users u ON l.user_id = u.id
            WHERE $whereSQL
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?";

$paramsData = $params;
$paramsData[] = $limit;
$paramsData[] = $offset;
$typesData = $types . "ii";

$stmt = $conn->prepare($sqlData);
$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">

<div class="main-content">
    <div class="content-grid">
        <div class="table-card">
            <div class="page-header">
                <h1>📄 Price List Audit Logs</h1>
                <p class="page-subtitle">
                    Review all changes made to price list items. Use the filter below to narrow down by month.
                </p>
            </div>

            <div class="table-header">
                <div class="filter-container">
                    <span class="filter-label">Filter Logs:</span>
                    <span class="filter-help">Use multiple filters to narrow down log entries.</span>
                    <form method="GET" class="filter-form">
                        <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="filter-input" placeholder="Month">

                        <input type="text" name="user" value="<?= htmlspecialchars($_GET['user'] ?? '') ?>" class="filter-input" placeholder="User Name">
                        <input type="text" name="field" value="<?= htmlspecialchars($_GET['field'] ?? '') ?>" class="filter-input" placeholder="Field Name">
                        <input type="text" name="reference_id" value="<?= htmlspecialchars($_GET['reference_id'] ?? '') ?>" class="filter-input" placeholder="Reference ID">
                        <input type="text" name="item_code" value="<?= htmlspecialchars($_GET['item_code'] ?? '') ?>" class="filter-input" placeholder="Item Code">

                        <button type="submit" class="btn-primary filter-btn">Apply Filter</button>
                        <a href="price_lists_logs.php" class="btn-cancel filter-btn">Clear</a>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table id="logsTable">
                    <thead>
                        <tr>
                            <th>User 👤</th>
                            <th title="Which field was changed">Field 🔧</th>
                            <th title="Item Code">Item Code 📦</th>
                            <th title="Previous value before change">Old Value 🔙</th>
                            <th title="New value after change">New Value 🔜</th>
                            <th>Date & Time 📅</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($logs)): ?>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['user_name']) ?></td>
                                    <td><?= htmlspecialchars($log['field_name']) ?></td>
                                    <td><?= htmlspecialchars($log['item_code']) ?></td>
                                    <td class="old-value"><?= htmlspecialchars($log['old_value'] ?? '-') ?></td>
                                    <td class="new-value"><?= htmlspecialchars($log['new_value'] ?? '-') ?></td>
                                    <td><?= date("M d, Y H:i", strtotime($log['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">No logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($totalRows > 0): ?>
                    <p class="page-summary">
                        Showing <?= count($logs) ?> of <?= $totalRows ?> log entries.
                    </p>
            <?php endif; ?>

            <?php if($totalPages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    <span>Page <?= $page ?> of <?= $totalPages ?></span>
                    <?php if($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once "../../layouts/footer.php"; ?>