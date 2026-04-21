<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['purchasing_officer']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";

$totalPRReceived = 0;

$resultPRReceived = $conn->query("
    SELECT COUNT(*) as total 
    FROM pr_forms 
    WHERE received_by IS NOT NULL
");

$totalPRReceived = $resultPRReceived 
    ? $resultPRReceived->fetch_assoc()['total'] 
    : 0;

$resultItems = $conn->query("SELECT COUNT(*) as total FROM price_lists");
$totalItems = $resultItems ? $resultItems->fetch_assoc()['total'] : 0;

$sqlLogs = "
    SELECT COUNT(*) as total
    FROM price_list_audit_details d
    INNER JOIN audit_logs l ON d.audit_log_id = l.id
    INNER JOIN users u ON l.user_id = u.id
    WHERE l.module = 'Price List Management'
";
$resultLogs = $conn->query($sqlLogs);
$totalLogs = $resultLogs ? $resultLogs->fetch_assoc()['total'] : 0;

$limit = 10;
$recentPriceListChanges = [];
$resultPriceList = $conn->query("SELECT * FROM price_lists ORDER BY uploaded_at DESC LIMIT $limit");
if($resultPriceList){
    $recentPriceListChanges = $resultPriceList->fetch_all(MYSQLI_ASSOC);
}

$recentPurchaseRequests = [];

$queryPR = "
    SELECT pf.pr_id, pf.project, pf.status, pf.created_at, u.full_name as created_by
    FROM pr_forms pf
    JOIN users u ON pf.created_by = u.id
    WHERE pf.status = 'approved'
    ORDER BY pf.created_at DESC
    LIMIT 10
";

$resultPR = $conn->query($queryPR);
if ($resultPR) {
    $recentPurchaseRequests = $resultPR->fetch_all(MYSQLI_ASSOC);
}

$chartItemsData = [];
$chartItemsLabels = [];
$resultChart = $conn->query("
    SELECT DATE_FORMAT(uploaded_at,'%b %Y') as month, COUNT(*) as total
    FROM price_lists
    WHERE uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY uploaded_at ASC
");
if($resultChart){
    while($row = $resultChart->fetch_assoc()){
        $chartItemsLabels[] = $row['month'];
        $chartItemsData[] = (int)$row['total'];
    }
}

$chartLogsData = [];
$chartLogsLabels = [];
$resultChartLogs = $conn->query("
    SELECT 
        DATE_FORMAT(l.created_at,'%b %Y') as month, 
        COUNT(*) as total
    FROM price_list_audit_details d
    INNER JOIN audit_logs l ON d.audit_log_id = l.id
    INNER JOIN users u ON l.user_id = u.id
    WHERE l.module = 'Price List Management'
      AND l.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(l.created_at,'%Y-%m')
    ORDER BY MIN(l.created_at) ASC
");
if($resultChartLogs){
    while($row = $resultChartLogs->fetch_assoc()){
        $chartLogsLabels[] = $row['month'];
        $chartLogsData[] = (int)$row['total'];
    }
}

function calculatePercentageChange($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100);
}

$currentPR = $conn->query("
    SELECT COUNT(*) as total 
    FROM pr_forms 
    WHERE received_by IS NOT NULL
    AND MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
")->fetch_assoc()['total'] ?? 0;

$previousPR = $conn->query("
    SELECT COUNT(*) as total 
    FROM pr_forms 
    WHERE received_by IS NOT NULL
    AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetch_assoc()['total'] ?? 0;

$prChange = calculatePercentageChange($currentPR, $previousPR);

$currentItems = $conn->query("
    SELECT COUNT(*) as total 
    FROM price_lists
    WHERE MONTH(uploaded_at) = MONTH(CURDATE())
    AND YEAR(uploaded_at) = YEAR(CURDATE())
")->fetch_assoc()['total'] ?? 0;

$previousItems = $conn->query("
    SELECT COUNT(*) as total 
    FROM price_lists
    WHERE MONTH(uploaded_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(uploaded_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetch_assoc()['total'] ?? 0;

$itemChange = calculatePercentageChange($currentItems, $previousItems);

$currentLogs = $conn->query("
    SELECT COUNT(*) as total
    FROM price_list_audit_details d
    INNER JOIN audit_logs l ON d.audit_log_id = l.id
    WHERE l.module = 'Price List Management'
    AND MONTH(l.created_at) = MONTH(CURDATE())
    AND YEAR(l.created_at) = YEAR(CURDATE())
")->fetch_assoc()['total'] ?? 0;

$previousLogs = $conn->query("
    SELECT COUNT(*) as total
    FROM price_list_audit_details d
    INNER JOIN audit_logs l ON d.audit_log_id = l.id
    WHERE l.module = 'Price List Management'
    AND MONTH(l.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(l.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetch_assoc()['total'] ?? 0;

$logChange = calculatePercentageChange($currentLogs, $previousLogs);
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="main-content">
    <h1>Purchasing Officer Dashboard</h1>
    <div class="content-grid">

        <div class="summary-cards">
            <div class="card" style="background-color:#3b82f6;">
                <div class="icon">📄</div>
                <div>
                    <h4>Total PRs Received</h4>
                    <p><?= $totalPRReceived ?></p>
                </div>
                <div class="trend <?= $prChange > 0 ? 'up' : ($prChange < 0 ? 'down' : '') ?>" 
                    title="Compared to last month">
                    
                    <span class="arrow">
                        <?= $prChange > 0 ? '↑' : ($prChange < 0 ? '↓' : '→') ?>
                    </span>
                    
                    <?= abs($prChange) ?>%
                    
                    <small class="trend-label">vs last month</small>
                </div>
            </div>

            <div class="card" style="background-color:#f59e0b;">
                <div class="icon">📦</div>
                <div>
                    <h4>Total Price List Items</h4>
                    <p><?= $totalItems ?></p>
                </div>
                <div class="trend <?= $itemChange > 0 ? 'up' : ($itemChange < 0 ? 'down' : '') ?>" 
                    title="Compared to last month">
                    
                    <span class="arrow">
                        <?= $itemChange > 0 ? '↑' : ($itemChange < 0 ? '↓' : '→') ?>
                    </span>
                    
                    <?= abs($itemChange) ?>%
                    
                    <small class="trend-label">vs last month</small>
                </div>
            </div>

            <div class="card" style="background-color:#10b981;">
                <div class="icon">📝</div>
                <div>
                    <h4>Total Logs</h4>
                    <p><?= $totalLogs ?></p>
                </div>
                <div class="trend <?= $logChange > 0 ? 'up' : ($logChange < 0 ? 'down' : '') ?>" 
                    title="Compared to last month">
                    
                    <span class="arrow">
                        <?= $logChange > 0 ? '↑' : ($logChange < 0 ? '↓' : '→') ?>
                    </span>
                    
                    <?= abs($logChange) ?>%
                    
                    <small class="trend-label">vs last month</small>
                </div>
            </div>
        </div>

        <div class="charts-grid" style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:30px;">
            <div class="card" style="flex:1; min-width:300px;">
                <h3>Price List Items Added (Last 6 Months)</h3>
                <canvas id="itemsChart"></canvas>
            </div>
            <div class="card" style="flex:1; min-width:300px;">
                <h3>Total Logs (Last 6 Months)</h3>
                <canvas id="logsChart"></canvas>
            </div>
        </div>

        <div class="table-card">
            <div class="page-header">
                <h2>Recent Price List Changes</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Reference ID</th>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>Unit</th>
                            <th>Unit Price</th>
                            <th>PMGI Unit Price</th>
                            <th>Uploaded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($recentPriceListChanges)): ?>
                            <?php foreach($recentPriceListChanges as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['reference_id']) ?></td>
                                    <td><?= htmlspecialchars($row['item_code']) ?></td>
                                    <td><?= htmlspecialchars($row['item_description']) ?></td>
                                    <td><?= htmlspecialchars($row['unit']) ?></td>
                                    <td><?= '₱ '.number_format((float)$row['unit_price'],2) ?></td>
                                    <td><?= '₱ '.number_format((float)$row['pmgi_unit_price'],2) ?></td>
                                    <td><?= date("M d, Y H:i", strtotime($row['uploaded_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">No recent changes found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-card">
            <div class="page-header">
                <h2>Recent Purchase Requests</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Requested By</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($recentPurchaseRequests)): ?>
                            <?php foreach($recentPurchaseRequests as $pr): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pr['pr_id']) ?></td>
                                    <td><?= htmlspecialchars($pr['created_by']) ?></td>
                                    <td><?= htmlspecialchars($pr['project'] ?: '-') ?></td>
                                    <td>
                                        <span class="status-badge status-approved">Approved</span>
                                    </td>
                                    <td><?= date("M d, Y", strtotime($pr['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">No purchase requests yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
const ctxItems = document.getElementById('itemsChart').getContext('2d');
new Chart(ctxItems, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartItemsLabels) ?>,
        datasets: [{
            label: 'Items Added',
            data: <?= json_encode($chartItemsData) ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.2)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

const ctxLogs = document.getElementById('logsChart').getContext('2d');
new Chart(ctxLogs, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLogsLabels) ?>,
        datasets: [{
            label: 'Logs / Transactions',
            data: <?= json_encode($chartLogsData) ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>
