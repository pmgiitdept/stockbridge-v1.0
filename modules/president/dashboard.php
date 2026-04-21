<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['president']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";

$resultSMRFStats = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM smrf_forms
");
if (!$resultSMRFStats) {
    die("SMRF query failed: " . $conn->error);
}
$smrfStats = $resultSMRFStats->fetch_assoc();
$totalSMRF = $smrfStats['total'] ?? 0;
$smrfPending = $smrfStats['pending'] ?? 0;
$smrfApproved = $smrfStats['approved'] ?? 0;
$smrfRejected = $smrfStats['rejected'] ?? 0;

$resultPRStats = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM pr_forms
");
if (!$resultPRStats) {
    die("PR query failed: " . $conn->error);
}
$prStats = $resultPRStats->fetch_assoc();
$totalPR = $prStats['total'] ?? 0;
$prPending = $prStats['pending'] ?? 0;
$prApproved = $prStats['approved'] ?? 0;
$prRejected = $prStats['rejected'] ?? 0;

$totalContracts = $conn->query("SELECT COUNT(*) AS total FROM contracts")->fetch_assoc()['total'] ?? 0;
$activeContracts = 0;     
$completedContracts = 0;  
$terminatedContracts = 0; 

$totalBudget = 0;      
$spentBudget = 0;     
$remainingBudget = $totalBudget - $spentBudget;

$recentActivity = [];
$limit = 50;

$resultRecent = $conn->query("
    SELECT 
        'SMRF' AS type,
        s.reference_id AS reference_no,
        u.full_name,
        s.project AS site,
        SUM(i.amount) AS total_value,
        s.created_at
    FROM smrf_forms s
    LEFT JOIN users u ON s.created_by = u.id
    LEFT JOIN smrf_items i ON s.smrf_id = i.smrf_id
    GROUP BY s.smrf_id, s.reference_id, u.full_name, s.project, s.created_at

    UNION ALL

    SELECT 
        'PR' AS type,
        p.pr_id AS reference_no,
        u.full_name,
        p.project AS site,
        SUM(pi.quantity) AS total_value,
        p.created_at
    FROM pr_forms p
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN pr_items pi ON p.pr_id = pi.pr_id
    GROUP BY p.pr_id, u.full_name, p.project, p.created_at

    ORDER BY created_at DESC
    LIMIT $limit
");
if ($resultRecent) {
    $recentActivity = $resultRecent->fetch_all(MYSQLI_ASSOC);
}

$months = [];
$smrfMap = [];
$prMap = [];

$resultChartSMRF = $conn->query("
    SELECT 
        DATE_FORMAT(created_at,'%Y-%m') as month_key,
        DATE_FORMAT(created_at,'%b %Y') as month_label,
        COUNT(*) as total
    FROM smrf_forms
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
");
while($row = $resultChartSMRF->fetch_assoc()){
    $smrfMap[$row['month_key']] = [
        'label' => $row['month_label'],
        'total' => (int)$row['total']
    ];
}

$resultChartPR = $conn->query("
    SELECT 
        DATE_FORMAT(created_at,'%Y-%m') as month_key,
        DATE_FORMAT(created_at,'%b %Y') as month_label,
        COUNT(*) as total
    FROM pr_forms
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
");
while($row = $resultChartPR->fetch_assoc()){
    $prMap[$row['month_key']] = (int)$row['total'];
}

$chartSMRFData = [];
$chartPRData = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("-$i months"));
    $chartSMRFData[] = $smrfMap[$key]['total'] ?? 0;
    $chartPRData[]   = $prMap[$key] ?? 0;
}

?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.type-badge{ padding:4px 10px; border-radius:15px; font-size:12px; font-weight:600;}
.type-badge.smrf{background:#dcfce7;color:#166534;}
.type-badge.pr{background:#fef3c7;color:#92400e;}
.president-summary{display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:20px;}
.card-shortcut{display:flex; flex-direction:column; align-items:center; justify-content:center; border-radius:8px; cursor:pointer; transition:0.2s;}
.card-shortcut:hover{transform:scale(1.05); box-shadow:0 4px 10px rgba(0,0,0,0.1);}
.table-responsive{max-height:400px; overflow-y:auto; border-radius:8px;}
table thead th{position:sticky; top:0; background:#fff; z-index:2;}
.charts-grid{display:flex; gap:20px; flex-wrap:wrap; margin-bottom:30px; justify-content:space-between;}

.erp-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.erp-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    transition: 0.2s ease;
    border-left: 5px solid #3b82f6;
}

.erp-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.1);
}

.erp-card-header {
    font-size: 14px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 10px;
}

.erp-card-body h2 {
    font-size: 28px;
    margin: 0;
    color: #111827;
}

.erp-card-body p {
    font-size: 13px;
    color: #6b7280;
    margin-top: 5px;
}

.highlight-warning {
    border-left: 5px solid #f59e0b;
    background: #fffbeb;
}

.card-shortcut {
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    cursor:pointer;
    transition:0.2s;
}

.card-shortcut:hover {
    transform:scale(1.05);
    box-shadow:0 6px 15px rgba(0,0,0,0.15);
}
</style>

<div class="main-content">
    <h1>President Dashboard</h1>

    <div class="summary-cards president-summary">

        <a href="../contracts/listed_contracts.php" class="card card-shortcut" style="background:#3b82f6;color:#fff; text-decoration:none;">
            <div class="icon">📄</div>
            <div>
            <h4>Listed Contracts</h4>
        </div>
        </a>

        <a href="../budgets/supplies_monitoring.php" class="card card-shortcut" style="background:#f59e0b;color:#fff; text-decoration:none;">
            <div class="icon">📦</div>
            <div>
                <h4>Supplies Monitoring</h4>
            </div>
        </a>

        <a href="../forms/forms_status.php" class="card card-shortcut" style="background:#10b981;color:#fff; text-decoration:none;">
            <div class="icon">📋</div>
            <div>
                <h4>Forms Status</h4>
            </div>
        </a>

        <a href="../budgets/overall_budget.php" class="card card-shortcut" style="background:#ef4444;color:#fff; text-decoration:none;">
            <div class="icon">💰</div>
            <div>
                <h4>Budget Monitoring</h4>
            </div>
        </a>
    </div>
    <?php
    $totalPendingApprovals = $smrfPending + $prPending;

    $approvalRate = $totalSMRF > 0 
        ? round(($smrfApproved / $totalSMRF) * 100, 1) 
        : 0;
    ?>

    <div class="erp-kpi-grid">

        <!-- Pending Approvals -->
        <div class="erp-card highlight-warning">
            <div class="erp-card-header">
                <span>🚨 Pending Approvals</span>
            </div>
            <div class="erp-card-body">
                <h2><?= $totalPendingApprovals ?></h2>
                <p>SMRF + PR awaiting approval</p>
            </div>
        </div>

        <!-- System Overview -->
        <div class="erp-card">
            <div class="erp-card-header">
                <span>📊 System Overview</span>
            </div>
            <div class="erp-card-body">
                <p><strong>SMRF:</strong> <?= $totalSMRF ?></p>
                <p><strong>PR:</strong> <?= $totalPR ?></p>
                <p><strong>Contracts:</strong> <?= $totalContracts ?></p>
            </div>
        </div>

        <!-- Approval Rate -->
        <div class="erp-card">
            <div class="erp-card-header">
                <span>📈 SMRF Approval Rate</span>
            </div>
            <div class="erp-card-body">
                <h2><?= $approvalRate ?>%</h2>
                <p>Approved vs total SMRF</p>
            </div>
        </div>

    </div>

    <div class="charts-grid">
        <div class="card" style="flex:1; min-width:300px;">
            <h3>SMRF vs PR (Last 6 Months)</h3>
            <canvas id="combinedChart"></canvas>
        </div>
    </div>

    <div class="table-card">
        <div class="page-header">
            <h2>Recent Workflow Activity</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Reference No</th>
                        <th>Submitted By</th>
                        <th>Project</th>
                        <th>Total Value</th>
                        <th>Date Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($recentActivity)): ?>
                        <?php foreach($recentActivity as $row): ?>
                        <tr class="row-<?= strtolower($row['type']) ?>">
                            <td>
                                <span class="type-badge <?= strtolower($row['type']) ?>">
                                    <?= htmlspecialchars($row['type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['reference_no']) ?></td>
                            <td><?= htmlspecialchars($row['full_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['site'] ?? '-') ?></td>
                            <td>
                                <?php 
                                    $value = $row['total_value'] ?? 0;
                                    if($row['type'] === 'SMRF'){
                                        $value = $value * 1.12;
                                        echo '₱ ' . number_format($value, 2);
                                    } else {
                                        echo number_format($value) . ' Quantity';
                                    }
                                ?>
                            </td>
                            <td><?= date("M d, Y H:i", strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No recent activity found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const combinedChart = new Chart(
    document.getElementById('combinedChart'),
    {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [
                {
                    label: 'SMRF',
                    data: <?= json_encode($chartSMRFData) ?>,
                    backgroundColor: 'rgba(16,185,129,0.7)'
                },
                {
                    label: 'PR',
                    data: <?= json_encode($chartPRData) ?>,
                    backgroundColor: 'rgba(245,158,11,0.7)'
                }
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    }
);
</script>

<?php require_once "../../layouts/footer.php"; ?>