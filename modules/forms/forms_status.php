<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['purchasing_officer', 'president', 'admin', 'operations_manager']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";

$prQuery = "SELECT pr_id AS form_id, status, IFNULL(updated_at, created_at) AS updated_at FROM pr_forms";
$prResult = $conn->query($prQuery);
if(!$prResult){ die("PR Query Failed: ".$conn->error); }
$prForms = $prResult->fetch_all(MYSQLI_ASSOC);

$smrfQuery = "SELECT smrf_id AS form_id, status, created_at AS updated_at FROM smrf_forms";
$smrfResult = $conn->query($smrfQuery);
if(!$smrfResult){ die("SMRF Query Failed: ".$conn->error); }
$smrfForms = $smrfResult->fetch_all(MYSQLI_ASSOC);

$clientQuery = "
    SELECT reference_id AS form_id, status, MAX(created_at) AS updated_at, user_id AS created_by
    FROM client_forms
    GROUP BY reference_id, status, user_id
";
$clientResult = $conn->query($clientQuery);
if(!$clientResult){ die("Client Forms Query Failed: ".$conn->error); }

$clientForms = [];
while($row = $clientResult->fetch_assoc()){
    $userId = $row['created_by'];
    $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id=? LIMIT 1");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userRes = $userStmt->get_result()->fetch_assoc();
    $row['created_by'] = $userRes['full_name'] ?? 'Unknown';
    $userStmt->close();
    $clientForms[] = $row;
}

function getStatusClass($status){
    $status = strtolower($status);
    switch($status){
        case 'pending': return 'status-badge status-pending';
        case 'reviewed': return 'status-badge status-reviewed';
        case 'approved': case 'verified': return 'status-badge status-approved';
        case 'rejected': return 'status-badge status-rejected';
        case 'in_progress': return 'status-badge status-in_progress';
        case 'completed': return 'status-badge status-completed';
        default: return 'status-badge status-pending';
    }
}

$monthlyData = [
    'labels' => [],
    'client' => [],
    'smrf' => [],
    'pr' => []
];

// Last 6 months
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime($month));

    $monthlyData['labels'][] = $label;

    // CLIENT
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM client_forms 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $monthlyData['client'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // SMRF
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM smrf_forms 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $monthlyData['smrf'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // PR
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM pr_forms 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $monthlyData['pr'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<?php
$totalCounts = [
    'Client Forms' => count($clientForms),
    'SMRF Forms' => count($smrfForms),
    'PR Forms' => count($prForms)
];

$clientStatusCounts = ['pending'=>0,'reviewed'=>0,'approved'=>0,'verified'=>0,'rejected'=>0,'in_progress'=>0,'completed'=>0];
foreach($clientForms as $f){
    $s = strtolower($f['status']);
    if(isset($clientStatusCounts[$s])) $clientStatusCounts[$s]++;
}

$smrfStatusCounts = ['pending'=>0,'reviewed'=>0,'approved'=>0,'verified'=>0,'rejected'=>0,'in_progress'=>0,'completed'=>0];
foreach($smrfForms as $f){
    $s = strtolower($f['status']);
    if(isset($smrfStatusCounts[$s])) $smrfStatusCounts[$s]++;
}

$prStatusCounts = ['pending'=>0,'reviewed'=>0,'approved'=>0,'verified'=>0,'rejected'=>0,'in_progress'=>0,'completed'=>0];
foreach($prForms as $f){
    $s = strtolower($f['status']);
    if(isset($prStatusCounts[$s])) $prStatusCounts[$s]++;
}
?>

<div class="main-content">
    <h1>Forms Status</h1>
    <p>Overview of all submitted forms across categories.</p>
    
    <div class="forms-tables-container" style="
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        border: 1px solid #e5e7eb;
        padding: 16px;
        border-radius: 8px;
        background: #f9fafb;
        margin-bottom: 32px;
    ">
        <div style="flex:1; min-width:250px;">
            <h3 style="margin-bottom:8px;">Submitted Client Forms</h3>
            <p style="margin-top:0; font-size:0.875rem; color:#6b7280;">
                Shows all forms submitted by clients with their current status and last update.
            </p>
            <div class="table-card" style="padding:0;">
                <div class="table-responsive" style="max-height:400px; overflow:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:#f3f4f6; z-index:1;">
                            <tr>
                                <th>Form ID</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($clientForms)): ?>
                                <?php foreach($clientForms as $form): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($form['form_id']) ?></td>
                                        <td><span class="<?= getStatusClass($form['status']) ?>"><?= ucfirst($form['status']) ?></span></td>
                                        <td><?= date("M d, Y H:i", strtotime($form['updated_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;">No forms submitted yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div style="flex:1; min-width:250px;">
            <h3 style="margin-bottom:8px;">SMRF Forms</h3>
            <p style="margin-top:0; font-size:0.875rem; color:#6b7280;">
                Displays all SMRFs created for projects, including status updates and last activity date.
            </p>
            <div class="table-card" style="padding:0;">
                <div class="table-responsive" style="max-height:400px; overflow:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:#f3f4f6; z-index:1;">
                            <tr>
                                <th>Form ID</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($smrfForms)): ?>
                                <?php foreach($smrfForms as $form): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($form['form_id']) ?></td>
                                        <td><span class="<?= getStatusClass($form['status']) ?>"><?= ucfirst($form['status']) ?></span></td>
                                        <td><?= date("M d, Y H:i", strtotime($form['updated_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;">No SMRF forms yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div style="flex:1; min-width:250px;">
            <h3 style="margin-bottom:8px;">PR Forms</h3>
            <p style="margin-top:0; font-size:0.875rem; color:#6b7280;">
                Shows all purchase requests submitted, their status, and the last updated timestamp.
            </p>
            <div class="table-card" style="padding:0;">
                <div class="table-responsive" style="max-height:400px; overflow:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:#f3f4f6; z-index:1;">
                            <tr>
                                <th>Form ID</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($prForms)): ?>
                                <?php foreach($prForms as $form): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($form['form_id']) ?></td>
                                        <td><span class="<?= getStatusClass($form['status']) ?>"><?= ucfirst($form['status']) ?></span></td>
                                        <td><?= date("M d, Y H:i", strtotime($form['updated_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;">No PR forms yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="charts-container" style="
        display: flex;
        justify-content: center;
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 24px;
        margin-top: 32px;
        border: 1px solid #e5e7eb;
        padding: 16px;
        border-radius: 8px;
        background: #f9fafb;
    ">
        <div style="width: 400px; height: 400px;">
            <canvas id="formsChart" style="width: 100%; height: 100%;"></canvas>
        </div>

        <div style="width: 400px; height: 400px;">
            <canvas id="categoryPieChart" style="width: 100%; height: 100%;"></canvas>
        </div>

        <div style="width: 400px; height: 400px;">
            <canvas id="trendChart" style="width: 100%; height: 100%;"></canvas>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('formsChart').getContext('2d');

const labels = ['Pending','Reviewed','Approved/Verified','Rejected'];
const clientData = [
    <?= $clientStatusCounts['pending'] ?>,
    <?= $clientStatusCounts['reviewed'] ?>,
    <?= $clientStatusCounts['approved'] + $clientStatusCounts['verified'] ?>,
    <?= $clientStatusCounts['rejected'] ?>
];
const smrfData = [
    <?= $smrfStatusCounts['pending'] ?>,
    <?= $smrfStatusCounts['reviewed'] ?>,
    <?= $smrfStatusCounts['approved'] + $smrfStatusCounts['verified'] ?>,
    <?= $smrfStatusCounts['rejected'] ?>
];
const prData = [
    <?= $prStatusCounts['pending'] ?>,
    <?= $prStatusCounts['reviewed'] ?>,
    <?= $prStatusCounts['approved'] + $prStatusCounts['verified'] ?>,
    <?= $prStatusCounts['rejected'] ?>
];

const formsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Submitted Client Forms',
                data: clientData,
                backgroundColor: '#3b82f6'
            },
            {
                label: 'SMRF Forms',
                data: smrfData,
                backgroundColor: '#60a5fa'
            },
            {
                label: 'PR Forms',
                data: prData,
                backgroundColor: '#10b981'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            title: { display: true, text: 'Forms Status Overview' }
        },
        scales: {
            y: { beginAtZero: true, precision:0 }
        }
    }
});

const pieCtx = document.getElementById('categoryPieChart').getContext('2d');

const pieData = {
    labels: ['Client Forms', 'SMRF Forms', 'PR Forms'],
    datasets: [{
        data: [<?= $totalCounts['Client Forms'] ?>, <?= $totalCounts['SMRF Forms'] ?>, <?= $totalCounts['PR Forms'] ?>],
        backgroundColor: ['#3b82f6', '#60a5fa', '#10b981'],
        borderColor: '#fff',
        borderWidth: 2
    }]
};

const categoryPieChart = new Chart(pieCtx, {
    type: 'pie',
    data: pieData,
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            title: { display: true, text: 'Total Forms by Category' },
            datalabels: {
                color: '#fff',
                font: { weight: 'bold', size: 14 },
                formatter: (value, ctx) => value
            }
        }
    },
    plugins: [ChartDataLabels]
});

const trendCtx = document.getElementById('trendChart').getContext('2d');

const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($monthlyData['labels']) ?>,
        datasets: [
            {
                label: 'Client Forms',
                data: <?= json_encode($monthlyData['client']) ?>,
                borderColor: '#3b82f6',
                fill: false,
                tension: 0.3
            },
            {
                label: 'SMRF Forms',
                data: <?= json_encode($monthlyData['smrf']) ?>,
                borderColor: '#60a5fa',
                fill: false,
                tension: 0.3
            },
            {
                label: 'PR Forms',
                data: <?= json_encode($monthlyData['pr']) ?>,
                borderColor: '#10b981',
                fill: false,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Forms Submission Trend (Last 6 Months)'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                precision: 0
            }
        }
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>