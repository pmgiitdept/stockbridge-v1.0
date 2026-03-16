<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['client']);

$userId = $_SESSION['user_id'];

$statsQuery = $conn->prepare("
    SELECT reference_id, status, MIN(created_at) as submitted_at
    FROM client_forms
    WHERE user_id = ?
    GROUP BY reference_id, status
");
$statsQuery->bind_param("i", $userId);
$statsQuery->execute();
$statsResult = $statsQuery->get_result();

$totalForms = 0;
$pending = 0;
$approved = 0;
$rejected = 0;
$timeline = [];
$recent = [];

while ($row = $statsResult->fetch_assoc()) {
    $totalForms++;
    $status = strtolower($row['status']);

    if ($status === 'pending') $pending++;
    elseif ($status === 'rejected') $rejected++;
    elseif ($status === 'approved' || $status === 'verified') $approved++;

    $date = date("Y-m-d", strtotime($row['submitted_at']));
    if (!isset($timeline[$date])) {
        $timeline[$date] = 0;
    }
    $timeline[$date]++;

    $recent[] = $row;
}
$statsQuery->close();

ksort($timeline);
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="main-content">
    <div class="page-header">
        <h1>Client Dashboard</h1>
        <p class="page-subtitle">Overview of your submitted forms and activity.</p>
    </div>

    <div class="dashboard-cards">
        <div class="card stat-card">
            <h3>Total Forms</h3>
            <p class="stat-number"><?= $totalForms ?></p>
        </div>

        <div class="card stat-card pending">
            <h3>Pending</h3>
            <p class="stat-number"><?= $pending ?></p>
        </div>

        <div class="card stat-card approved">
            <h3>Approved / Verified</h3>
            <p class="stat-number"><?= $approved ?></p>
        </div>

        <div class="card stat-card rejected">
            <h3>Rejected</h3>
            <p class="stat-number"><?= $rejected ?></p>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="card chart-card">
            <h3>Status Distribution</h3>
            <canvas id="statusChart"></canvas>
        </div>

        <div class="card chart-card">
            <h3>Submission Timeline</h3>
            <canvas id="timelineChart"></canvas>
        </div>
    </div>

    <div class="card table-card" style="margin-top:20px;">
        <h3>Recent Submissions</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Reference ID</th>
                        <th>Status</th>
                        <th>Approved By</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($recent)): ?>
                        <?php foreach(array_slice(array_reverse($recent), 0, 5) as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['reference_id']) ?></td>
                                <td>
                                    <?php 
                                        $status = strtolower($item['status']);
                                        $class = "status-badge ";
                                        if($status === 'pending') $class .= "status-pending";
                                        elseif($status === 'rejected') $class .= "status-rejected";
                                        else $class .= "status-approved";
                                    ?>
                                    <span class="<?= $class ?>">
                                        <?= ucfirst($item['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars('-') ?>
                                </td>
                                <td><?= date("M d, Y H:i", strtotime($item['submitted_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align:center;">No submissions yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const statusChart = new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: ['Pending', 'Approved/Verified', 'Rejected'],
        datasets: [{
            data: [<?= $pending ?>, <?= $approved ?>, <?= $rejected ?>],
            backgroundColor: [
                '#f1c40f', 
                '#2ecc71',
                '#e74c3c' 
            ],
            borderColor: '#fff',
            borderWidth: 1
        }]
    },
    options: {
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

const timelineChart = new Chart(document.getElementById('timelineChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($timeline)) ?>,
        datasets: [{
            label: 'Forms Submitted',
            data: <?= json_encode(array_values($timeline)) ?>,
            fill: false,
            tension: 0.3
        }]
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>