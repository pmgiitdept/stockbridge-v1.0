<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['admin','president']);

$search = $_GET['search'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$type   = $_GET['type'] ?? '';
$page   = isset($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

$limit  = 20;
$offset = ($page - 1) * $limit;

$where = [];

if (!empty($search)) {
    $safe = mysqli_real_escape_string($conn, $search);
    $where[] = "(e.error_message LIKE '%$safe%' OR e.error_file LIKE '%$safe%')";
}

if (!empty($from) && !empty($to)) {
    $safeFrom = mysqli_real_escape_string($conn, $from);
    $safeTo   = mysqli_real_escape_string($conn, $to);
    $where[] = "DATE(e.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
}

if (!empty($type)) {
    $safeType = mysqli_real_escape_string($conn, $type);
    $where[] = "e.error_type = '$safeType'";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$todayTotal = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) as total FROM error_logs WHERE DATE(created_at)=CURDATE()
"))['total'];

$weekTotal = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) as total 
    FROM error_logs 
    WHERE YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)
"))['total'];

$monthTotal = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) as total 
    FROM error_logs 
    WHERE MONTH(created_at)=MONTH(CURDATE()) 
    AND YEAR(created_at)=YEAR(CURDATE())
"))['total'];

$grandTotal = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) as total FROM error_logs
"))['total'];

$typeQuery = mysqli_query($conn,"
    SELECT error_type, COUNT(*) as total
    FROM error_logs
    GROUP BY error_type
");

$errorTypes = [];
$errorTotals = [];

while($row = mysqli_fetch_assoc($typeQuery)){
    $errorTypes[] = $row['error_type'];
    $errorTotals[] = $row['total'];
}

$trendQuery = mysqli_query($conn,"
    SELECT DATE(created_at) as day, COUNT(*) as total
    FROM error_logs
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY day
    ORDER BY day ASC
");

$dates = [];
$totals = [];

while($row = mysqli_fetch_assoc($trendQuery)){
    $dates[] = $row['day'];
    $totals[] = $row['total'];
}

$countQuery = "
SELECT COUNT(*) as total
FROM error_logs e
$whereSQL
";

$countResult = mysqli_query($conn, $countQuery);
$totalRows   = mysqli_fetch_assoc($countResult)['total'];
$totalPages  = ceil($totalRows / $limit);

$query = "
SELECT e.*, u.full_name
FROM error_logs e
LEFT JOIN users u ON e.user_id = u.id
$whereSQL
ORDER BY e.created_at DESC
LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);

$topFilesQuery = mysqli_query($conn,"
    SELECT error_file, COUNT(*) as total
    FROM error_logs
    GROUP BY error_file
    ORDER BY total DESC
    LIMIT 5
");

$topFiles = [];
$topCounts = [];

while($row = mysqli_fetch_assoc($topFilesQuery)){
    $topFiles[] = basename($row['error_file']); 
    $topCounts[] = $row['total'];
}
?>

<div class="main-content">

    <div class="page-header">
        <h1>Error Logs</h1>
        <p>Monitor system errors, exceptions, and failures</p>
    </div>

    <div class="summary-grid">
        <div class="summary-card"><h4>Today</h4><p><?= $todayTotal ?></p></div>
        <div class="summary-card"><h4>This Week</h4><p><?= $weekTotal ?></p></div>
        <div class="summary-card"><h4>This Month</h4><p><?= $monthTotal ?></p></div>
        <div class="summary-card"><h4>Total Errors</h4><p><?= $grandTotal ?></p></div>
    </div>

    <?php if($todayTotal >= 20): ?>
        <div class="security-alert">
            ⚠ High number of system errors today (<?= $todayTotal ?>)
        </div>
    <?php endif; ?>

    <div class="analytics-grid">
        <div class="analytics-card">
            <h3>Error Types Distribution</h3>
            <canvas id="typeChart"></canvas>
        </div>

        <div class="analytics-card">
            <h3>Error Trend (Last 7 Days)</h3>
            <canvas id="trendChart"></canvas>
        </div>

        <div class="analytics-card">
            <h3>Top Error Sources</h3>
            <canvas id="sourceChart"></canvas>
        </div>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-form">

            <input type="text" name="search" placeholder="Search error message or file..."
                value="<?= htmlspecialchars($search) ?>">

            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">

            <div class="custom-select-wrapper">
                <select name="type" class="custom-select">
                    <option value="">All Types</option>
                    <option value="ERROR" <?= $type=='ERROR'?'selected':'' ?>>ERROR</option>
                    <option value="EXCEPTION" <?= $type=='EXCEPTION'?'selected':'' ?>>EXCEPTION</option>
                    <option value="FATAL" <?= $type=='FATAL'?'selected':'' ?>>FATAL</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Filter</button>
            <a href="view_errors.php" class="btn-secondary">Reset</a>
        </form>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Error Message</th>
                    <th>File</th>
                    <th>Line</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Request</th>
                    <th>URL</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($result) > 0): ?>
                    <?php while($log = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['full_name'] ?? 'System') ?></td>

                            <td class="error-message">
                                <?= htmlspecialchars($log['error_message']) ?>
                            </td>

                            <td><?= htmlspecialchars($log['error_file']) ?></td>

                            <td><?= $log['error_line'] ?></td>

                            <td>
                                <span class="error-badge error-<?= strtolower($log['error_type']) ?>">
                                    <?= $log['error_type'] ?>
                                </span>
                            </td>

                            <td>
                                <?= date("M d, Y h:i A", strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <span class="method-badge">
                                    <?= htmlspecialchars($error['method'] ?? 'N/A') ?>
                                </span>
                            </td>

                            <td style="max-width:250px; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars($error['url'] ?? 'N/A') ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">No error logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if($totalPages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">
                    &laquo; Previous
                </a>
            <?php endif; ?>

            <span>Page <?= $page ?> of <?= $totalPages ?></span>

            <?php if($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ERROR TYPE CHART
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($errorTypes) ?>,
        datasets: [{
            data: <?= json_encode($errorTotals) ?>
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// TREND CHART
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($dates) ?>,
        datasets: [{
            label: 'Errors',
            data: <?= json_encode($totals) ?>,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, precision:0 }
        }
    }
});

new Chart(document.getElementById('sourceChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($topFiles) ?>,
        datasets: [{
            label: 'Errors',
            data: <?= json_encode($topCounts) ?>
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, precision: 0 }
        }
    }
});
</script>

<?php require_once "../../layouts/footer.php"; ?>