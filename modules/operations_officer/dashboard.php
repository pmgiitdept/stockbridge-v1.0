<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";

$resultContracts = $conn->query("
    SELECT COUNT(DISTINCT user_id) as total 
    FROM contracts
");
$totalContracts = $resultContracts ? $resultContracts->fetch_assoc()['total'] : 0;

$resultActive = $conn->query("
    SELECT COUNT(DISTINCT user_id) as total 
    FROM contracts 
    WHERE status = 'active'
");
$totalActiveContracts = $resultActive ? $resultActive->fetch_assoc()['total'] : 0;

$resultTransactions = $conn->query("
    SELECT SUM(total) as total FROM (
        SELECT COUNT(DISTINCT reference_id) as total FROM client_forms
        UNION ALL
        SELECT COUNT(*) as total FROM smrf_forms
        UNION ALL
        SELECT COUNT(*) as total FROM pr_forms
    ) as combined
");

$totalTransactions = $resultTransactions ? $resultTransactions->fetch_assoc()['total'] : 0;

$limit = 10;
$recentSubmissions = [];

$resultRecent = $conn->query("
    SELECT * FROM (

        -- CLIENT FORMS (grouped per submission)
        SELECT 
            'Client Form' AS type,
            cf.reference_id AS reference_no,
            u.full_name,
            '-' AS site,
            MAX(cf.created_at) AS created_at
        FROM client_forms cf
        LEFT JOIN users u ON cf.user_id = u.id
        GROUP BY cf.reference_id

        UNION ALL

        -- SMRF
        SELECT 
            'SMRF' AS type,
            s.smrf_id AS reference_no,
            u.full_name,
            s.project AS site,
            s.created_at
        FROM smrf_forms s
        LEFT JOIN users u ON s.created_by = u.id

        UNION ALL

        -- PR
        SELECT 
            'PR' AS type,
            p.pr_id AS reference_no,
            u.full_name,
            p.project AS site,
            p.created_at
        FROM pr_forms p
        LEFT JOIN users u ON p.created_by = u.id

    ) AS combined
    ORDER BY created_at DESC
    LIMIT $limit
");

if ($resultRecent) {
    $recentSubmissions = $resultRecent->fetch_all(MYSQLI_ASSOC);
}

$chartContractsLabels = [];
$chartContractsData = [];
$resultChart = $conn->query("
    SELECT 
        DATE_FORMAT(created_at,'%b %Y') as month, 
        COUNT(DISTINCT user_id) as total
    FROM contracts
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY MIN(created_at) ASC
");
if($resultChart){
    while($row = $resultChart->fetch_assoc()){
        $chartContractsLabels[] = $row['month'];
        $chartContractsData[] = (int)$row['total'];
    }
}

$chartTransactionsLabels = [];
$chartTransactionsData = [];

$resultChartTransactions = $conn->query("
    SELECT month, SUM(total) as total FROM (

        -- CLIENT FORMS (grouped per submission)
        SELECT 
            DATE_FORMAT(created_at,'%b %Y') as month,
            COUNT(DISTINCT reference_id) as total
        FROM client_forms
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month

        UNION ALL

        -- SMRF
        SELECT 
            DATE_FORMAT(created_at,'%b %Y') as month,
            COUNT(*) as total
        FROM smrf_forms
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month

        UNION ALL

        -- PR
        SELECT 
            DATE_FORMAT(created_at,'%b %Y') as month,
            COUNT(*) as total
        FROM pr_forms
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month

    ) as combined

    GROUP BY month
    ORDER BY STR_TO_DATE(month, '%b %Y') ASC
");

if($resultChartTransactions){
    while($row = $resultChartTransactions->fetch_assoc()){
        $chartTransactionsLabels[] = $row['month'];
        $chartTransactionsData[] = (int)$row['total'];
    }
}

$months = [];
$formsData = [];
$smrfData = [];
$prData = [];

for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));
    $months[$monthKey] = $monthLabel;

    $formsData[$monthKey] = 0;
    $smrfData[$monthKey] = 0;
    $prData[$monthKey] = 0;
}

$resForms = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, 
           COUNT(DISTINCT reference_id) as total
    FROM client_forms
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
");

while($row = $resForms->fetch_assoc()){
    $formsData[$row['month']] = (int)$row['total'];
}

$resSmrf = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, 
           COUNT(*) as total
    FROM smrf_forms
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
");

while($row = $resSmrf->fetch_assoc()){
    $smrfData[$row['month']] = (int)$row['total'];
}

$resPr = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, 
           COUNT(*) as total
    FROM pr_forms
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
");

while($row = $resPr->fetch_assoc()){
    $prData[$row['month']] = (int)$row['total'];
}

$chartLabels = array_values($months);
$chartForms = array_values($formsData);
$chartSmrf = array_values($smrfData);
$chartPr = array_values($prData);
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<style>
.type-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    display:inline-block;
}
.type-badge.form {
    background: #dbeafe;
    color: #1e40af;
}
.type-badge.smrf {
    background: #dcfce7;
    color: #166534;
}
.ops-summary-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.type-badge.pr {
    background: #fef3c7;
    color: #92400e;
}
</style>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="main-content">
    <h1>Operations Officer Dashboard</h1>

    <div class="content-grid">

        <div class="summary-cards ops-summary-cards">
            <div class="card" style="background-color:#3b82f6;">
                <div class="icon">📄</div>
                <div>
                    <h4>Total Contracts</h4>
                    <p><?= $totalContracts ?></p>
                </div>
                <div class="trend up"><span class="arrow">↑</span>--</div>
            </div>

            <div class="card" style="background-color:#10b981;">
                <div class="icon">✅</div>
                <div>
                    <h4>Active Contracts</h4>
                    <p><?= $totalActiveContracts ?></p>
                </div>
                <div class="trend up"><span class="arrow">↑</span>--</div>
            </div>

            <div class="card" style="background-color:#f59e0b;">
                <div class="icon">📝</div>
                <div>
                    <h3>Transactions (Forms, SMRF, PR)</h3>
                    <p><?= $totalTransactions ?></p>
                </div>
                <div class="trend down"><span class="arrow">↓</span>--</div>
            </div>
        </div>

        <div class="charts-grid" style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:30px;">
            <div class="card" style="flex:1; min-width:300px;">
                <h3>Contracts Created (Last 6 Months)</h3>
                <canvas id="contractsChart"></canvas>
            </div>
            <div class="card" style="flex:1; min-width:300px;">
                <h3>Logs / Transactions (Last 6 Months)</h3>
                <canvas id="logsChart"></canvas>
            </div>
        </div>

        <div class="table-card">
            <div class="page-header">
                <h2>Recent Submissions</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Reference No</th>
                            <th>Submitted By</th>
                            <th>Site / Project</th>
                            <th>Date Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentSubmissions)): ?>
                            <?php foreach ($recentSubmissions as $row): ?>
                                <tr>
                                    <td>
                                        <span class="type-badge <?= strtolower($row['type']) ?>">
                                            <?= htmlspecialchars($row['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['reference_no']) ?></td>
                                    <td><?= htmlspecialchars($row['full_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['site'] ?? '-') ?></td>
                                    <td><?= date("M d, Y H:i", strtotime($row['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;">
                                    No recent submissions found.
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
const ctxContracts = document.getElementById('contractsChart').getContext('2d');
new Chart(ctxContracts, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartContractsLabels) ?>,
        datasets: [{
            label: 'Contracts Created',
            data: <?= json_encode($chartContractsData) ?>,
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

const ctxTransactions = document.getElementById('logsChart').getContext('2d');

new Chart(ctxTransactions, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Client Forms',
                data: <?= json_encode($chartForms) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.7)'
            },
            {
                label: 'SMRF',
                data: <?= json_encode($chartSmrf) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.7)'
            },
            {
                label: 'PR',
                data: <?= json_encode($chartPr) ?>,
                backgroundColor: 'rgba(245, 158, 11, 0.7)'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true }
        },
        scales: {
            x: {
                stacked: true
            },
            y: {
                stacked: true,
                beginAtZero: true
            }
        }
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>