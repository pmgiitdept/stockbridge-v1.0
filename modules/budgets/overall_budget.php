<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'operations_manager', 'president']);

$projects = $conn->query("
    SELECT u.full_name AS project, c.field
    FROM contracts c
    JOIN users u ON c.user_id = u.id
    GROUP BY u.full_name, c.field
")->fetch_all(MYSQLI_ASSOC);

$months_per_frequency = [
    "Monthly"=>1,"Every 2 months"=>2,"Quarterly"=>3,
    "Semi-Annually"=>6,"Annually"=>12,
    "Every 1.5 years"=>18,"Every 2 years"=>24,
    "Every 3 years"=>36,"Every 4 years"=>48
];

$projectTotals = [];

$totalContract = 0;
$totalActual = 0;

foreach($projects as $proj){

    $stmt = $conn->prepare("
        SELECT SUM(amount) as total
        FROM smrf_items si
        JOIN smrf_forms sf ON si.smrf_id = sf.smrf_id
        WHERE sf.project = ?
    ");
    $stmt->bind_param("s", $proj['project']);
    $stmt->execute();
    $actual = floatval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt2 = $conn->prepare("
        SELECT quantity, cost_per_unit, frequency
        FROM contracts c
        JOIN users u ON c.user_id = u.id
        WHERE u.full_name = ?
    ");
    $stmt2->bind_param("s",$proj['project']);
    $stmt2->execute();
    $rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    $contract = 0;

    foreach($rows as $r){
        $qty = (float)$r['quantity'];
        $cost = (float)$r['cost_per_unit'];
        $freq = $r['frequency'] ?? 'Monthly';

        $months = $months_per_frequency[$freq] ?? 1;
        $contract += ($qty * $cost) / $months;
    }

    $contract *= 1.12;

    $projectTotals[] = [
        'project'=>$proj['project'],
        'contract'=>$contract,
        'actual'=>$actual
    ];

    $totalContract += $contract;
    $totalActual += $actual;
}

$riskProjects = [];

foreach($projectTotals as $p){

    $contract = $p['contract'];
    $actual = $p['actual'];

    $variance = $contract - $actual;
    $util = $contract > 0 ? ($actual / $contract) * 100 : 0;

    if($actual > $contract){
        $status = 'Over Budget';
        $color = '#ef4444';
    } elseif($util >= 80){
        $status = 'At Risk';
        $color = '#f59e0b';
    } else {
        $status = 'Healthy';
        $color = '#10b981';
    }

    $riskProjects[] = [
        'project' => $p['project'],
        'contract' => $contract,
        'actual' => $actual,
        'variance' => $variance,
        'util' => $util,
        'status' => $status,
        'color' => $color
    ];
}

$monthlyBudgetData = [];
$monthlyActualData = [];
$monthlyLabels = [];

$monthsQuery = "
    SELECT DISTINCT DATE_FORMAT(period,'%Y-%m') as month
    FROM smrf_forms
    ORDER BY month ASC
    LIMIT 12
";
$monthsResult = $conn->query($monthsQuery)->fetch_all(MYSQLI_ASSOC);

foreach($monthsResult as $m){
    $monthlyLabels[] = $m['month'];

    $budgetQuery = "
        SELECT SUM(c.quantity * c.cost_per_unit) as total_contract
        FROM contracts c
    ";
    $budgetRes = $conn->query($budgetQuery)->fetch_assoc();
    $monthlyBudgetData[] = floatval($budgetRes['total_contract'] ?? 0);

    $actualQuery = "
        SELECT SUM(amount) as total_actual
        FROM smrf_items si
        JOIN smrf_forms sf ON si.smrf_id = sf.smrf_id
        WHERE DATE_FORMAT(sf.period,'%Y-%m') = '".$m['month']."'
    ";
    $actualRes = $conn->query($actualQuery)->fetch_assoc();
    $monthlyActualData[] = floatval($actualRes['total_actual'] ?? 0);
}

$remaining = $totalContract - $totalActual;
$utilization = $totalContract > 0 ? ($totalActual / $totalContract) * 100 : 0;

$labels = array_column($projectTotals, 'project');
$contractData = array_column($projectTotals, 'contract');
$actualData = array_column($projectTotals, 'actual');
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<link rel="stylesheet" href="/contract_system/assets/css/overall_budget.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="main-content">

    <div class="page-header">
        <h1>Overall Budget</h1>
        <p class="page-subtitle">Monitor total budget, spending, and utilization across all projects.</p>
    </div>

    <div class="budget-summary">
        <div class="card" style="background:#3b82f6; position:relative;">
            <div class="card-header">
                <h4>Total Contracts Cost</h4>
                <span class="info-icon" onclick="openModal('budgetInfo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10
                                10-4.477 10-10S17.523 2 12 2zm.75 15h-1.5v-6h1.5v6zm0-8h-1.5V7h1.5v2z"/>
                    </svg>
                </span>
            </div>
            <p>₱ <?= number_format($totalContract,2) ?></p>
        </div>

        <div class="card" style="background:#ef4444; position:relative;">
            <div class="card-header">
            <h4>Total Spent </h4>
                <span class="info-icon" onclick="openModal('spentInfo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10
                                10-4.477 10-10S17.523 2 12 2zm.75 15h-1.5v-6h1.5v6zm0-8h-1.5V7h1.5v2z"/>
                    </svg>
                </span>
            </div>
            <p>₱ <?= number_format($totalActual,2) ?></p>
        </div>

        <div class="card" style="background:#10b981; position:relative;">
            <div class="card-header">
                <h4>Remaining</h4>
                <span class="info-icon" onclick="openModal('remainingInfo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10
                                10-4.477 10-10S17.523 2 12 2zm.75 15h-1.5v-6h1.5v6zm0-8h-1.5V7h1.5v2z"/>
                    </svg>
                </span>
            </div>
            <p>₱ <?= number_format($remaining,2) ?></p>
        </div>

        <div class="card" style="background:#f59e0b; position:relative;">
            <div class="card-header">
            <h4>Utilization</h4>
                <span class="info-icon" onclick="openModal('utilInfo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10
                                10-4.477 10-10S17.523 2 12 2zm.75 15h-1.5v-6h1.5v6zm0-8h-1.5V7h1.5v2z"/>
                    </svg>
                </span>
            </div>
            <p><?= number_format($utilization,1) ?>%</p>
        </div>
    </div>

    <div class="card">
        <h3>Budget vs Actual per Project</h3>
        <canvas id="budgetChart"></canvas>
    </div>

    <div class="card" style="margin-top:20px;">
        <h3>Budget Risk Monitoring</h3>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Budget</th>
                        <th>Actual</th>
                        <th>Variance</th>
                        <th>Utilization</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach($riskProjects as $row): ?>
                    <?php
                        $rowBg = '';

                        if($row['status'] === 'Over Budget'){
                            $rowBg = 'background:rgba(239,68,68,0.08);';
                        } elseif($row['status'] === 'At Risk'){
                            $rowBg = 'background:rgba(245,158,11,0.08);';
                        } else {
                            $rowBg = 'background:rgba(16,185,129,0.08);';
                        }
                    ?>
                    <tr style="<?= $rowBg ?>">
                        <td style="font-weight:600;">
                            <?= htmlspecialchars($row['project']) ?>
                        </td>
                        <td>₱ <?= number_format($row['contract'],2) ?></td>
                        <td>₱ <?= number_format($row['actual'],2) ?></td>
                        <td style="font-weight:600;">
                            <?php if($row['variance'] < 0): ?>
                                <span style="color:#dc2626;">
                                    ₱ <?= number_format($row['variance'],2) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#16a34a;">
                                    ₱ <?= number_format($row['variance'],2) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= number_format($row['util'],1) ?>%
                        </td>
                        <td>
                            <span style="
                                padding:4px 10px;
                                border-radius:12px;
                                color:#fff;
                                font-size:12px;
                                font-weight:600;
                                background:<?= $row['color'] ?>;
                            ">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top:20px;">
        <h3>Monthly Budget Trend</h3>
        <canvas id="monthlyBudgetChart" style="height:300px;"></canvas>
    </div>
</div>

<div id="budgetInfo" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('budgetInfo')">&times;</span>
        <h4>Total Budget Calculation</h4>
        <p>Sum of all contract amounts per project, adjusted for frequency and VAT (₱ Contract * 1.12).</p>
    </div>
</div>

<div id="spentInfo" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('spentInfo')">&times;</span>
        <h4>Total Spent Calculation</h4>
        <p>Sum of all actual spending recorded in SMRF items for all projects.</p>
    </div>
</div>

<div id="remainingInfo" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('remainingInfo')">&times;</span>
        <h4>Remaining Budget</h4>
        <p>Total Budget minus Total Spent.</p>
    </div>
</div>

<div id="utilInfo" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('utilInfo')">&times;</span>
        <h4>Utilization</h4>
        <p>(Total Spent ÷ Total Budget) * 100%</p>
    </div>
</div>

<script>

function openModal(id){
    document.getElementById(id).style.display = 'block';
}

function closeModal(id){
    document.getElementById(id).style.display = 'none';
}

window.onclick = function(event){
    const modals = document.querySelectorAll('.modal');
    modals.forEach(m=>{
        if(event.target == m) m.style.display = 'none';
    });
}

new Chart(document.getElementById('budgetChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            {
                label: 'Budget',
                data: <?= json_encode($contractData) ?>,
                backgroundColor: 'rgba(59,130,246,0.7)'
            },
            {
                label: 'Actual',
                data: <?= json_encode($actualData) ?>,
                backgroundColor: 'rgba(239,68,68,0.7)'
            }
        ]
    },
    options: {
        responsive:true,
        scales:{ y:{ beginAtZero:true } }
    }
});

const monthlyLabels = <?= json_encode($monthlyLabels) ?>;
const monthlyBudget = <?= json_encode($monthlyBudgetData) ?>;
const monthlyActual = <?= json_encode($monthlyActualData) ?>;

new Chart(document.getElementById('monthlyBudgetChart'), {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: [
            {
                label: 'Budget',
                data: monthlyBudget,
                borderColor: '#0d6efd',
                backgroundColor: '#0d6efd33',
                fill: true,
                tension: 0.3
            },
            {
                label: 'Actual',
                data: monthlyActual,
                borderColor: '#ef4444',
                backgroundColor: '#ef444433',
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            title: { display: true, text: 'Monthly Budget vs Actual' },
            tooltip: {
                mode: 'index',
                intersect: false,
                padding: 10,
                backgroundColor: '#333',
                titleColor: '#fff',
                bodyColor: '#fff',
                cornerRadius: 6
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => '₱ ' + v.toLocaleString()
                },
                grid: { color: '#eaeaea' }
            },
            x: { grid: { color: '#f5f5f5' } }
        }
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>