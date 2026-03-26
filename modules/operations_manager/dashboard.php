<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_manager']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";

$resultSMRFStats = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(status='Pending') AS pending,
        SUM(status='Approved') AS approved,
        SUM(status='Rejected') AS rejected
    FROM smrf_forms
");

if (!$resultSMRFStats) {
    die("SMRF Query Error: " . $conn->error);
}
$smrfStats = $resultSMRFStats->fetch_assoc();

$totalSMRF = $smrfStats['total'] ?? 0;
$smrfPending = $smrfStats['pending'] ?? 0;
$smrfApproved = $smrfStats['approved'] ?? 0;
$smrfRejected = $smrfStats['rejected'] ?? 0;

$resultPRStats = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(status='Pending') AS pending,
        SUM(status='Approved') AS approved,
        SUM(status='Rejected') AS rejected
    FROM pr_forms
");

if (!$resultPRStats) {
    die("PR Query Error: " . $conn->error);
}

$prStats = $resultPRStats->fetch_assoc();

$totalPR = $prStats['total'] ?? 0;
$prPending = $prStats['pending'] ?? 0;
$prApproved = $prStats['approved'] ?? 0;
$prRejected = $prStats['rejected'] ?? 0;

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

for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));

    $months[] = $label;

    $chartSMRFData[] = $smrfMap[$key]['total'] ?? 0;
    $chartPRData[]   = $prMap[$key] ?? 0;
}
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.type-badge{
    padding:4px 10px;
    border-radius:15px;
    font-size:12px;
    font-weight:600;
}

.type-badge.smrf{
    background:#dcfce7;
    color:#166534;
}

.type-badge.pr{
    background:#fef3c7;
    color:#92400e;
}

.manager-summary{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:20px;
    margin-bottom:20px;
}

.row-smrf {
    background-color: #ecfdf5; 
}

.row-pr {
    background-color: #fffbeb; 
}

.row-smrf:hover {
    background-color: #d1fae5;
}

.row-pr:hover {
    background-color: #fef3c7;
}

.table-responsive {
    max-height: 400px;  
    overflow-y: auto;
    border-radius: 8px;
}

table thead th {
    position: sticky;
    top: 0;
    background: #ffffff;
    z-index: 2;
}
</style>

<div class="main-content">
    <h1>Operations Manager Dashboard</h1>
        <div class="content-grid">
            <div class="summary-cards manager-summary" style="grid-template-columns: repeat(4,1fr);">
                <div class="card" style="background-color:#3b82f6;">
                    <div class="icon">📄</div>
                    <div>
                        <h4>Total SMRF</h4>
                        <p><?= $totalSMRF ?></p>
                    </div>
                </div>

                <div class="card" style="background-color:#f59e0b;">
                    <div class="icon">⏳</div>
                    <div>
                        <h4>SMRF Pending</h4>
                        <p><?= $smrfPending ?></p>
                    </div>
                </div>

                <div class="card" style="background-color:#10b981;">
                    <div class="icon">✅</div>
                    <div>
                        <h4>SMRF Approved</h4>
                        <p><?= $smrfApproved ?></p>
                    </div>
                </div>

                <div class="card" style="background-color:#ef4444;">
                    <div class="icon">❌</div>
                    <div>
                        <h4>SMRF Rejected</h4>
                        <p><?= $smrfRejected ?></p>
                    </div>
                </div>
            </div>
        </div>

    <div class="charts-grid" style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:30px;">

    <div class="card" style="flex:1; min-width:300px;">
        <h3>SMRF Submissions (Last 6 Months)</h3>
        <canvas id="smrfChart"></canvas>
    </div>

    <div class="card" style="flex:1; min-width:300px;">
        <h3>Purchase Requests (Last 6 Months)</h3>
        <canvas id="prChart"></canvas>
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
                    <tr>
                        <td colspan="6" style="text-align:center;">
                            No recent activity found.
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>

const combinedChart = new Chart(
    document.getElementById('smrfChart'),
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
            scales: {
                y: { beginAtZero: true }
            }
        }
    }
);

const prChart = new Chart(
    document.getElementById('prChart'),
    {
        type:'bar',
        data:{
        labels: <?= json_encode($months) ?>,
        datasets:[{ 
            label:'Purchase Requests',
            data:<?= json_encode($chartPRData) ?>,
            backgroundColor:'rgba(245,158,11,0.7)',
            borderColor:'rgba(245,158,11,1)',
            borderWidth:1
            }]
        },
        options:{
            responsive:true,
            plugins:{legend:{display:false}},
            scales:{y:{beginAtZero:true}}
        }
    }
);

</script>

<?php require_once "../../layouts/footer.php"; ?>