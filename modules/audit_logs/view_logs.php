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
$page   = isset($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

$limit  = 20;
$offset = ($page - 1) * $limit;

$whereConditions = [];

if (!empty($search)) {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $whereConditions[] = "(u.full_name LIKE '%$safeSearch%' 
                           OR a.action LIKE '%$safeSearch%' 
                           OR a.module LIKE '%$safeSearch%')";
}

if (!empty($from) && !empty($to)) {
    $safeFrom = mysqli_real_escape_string($conn, $from);
    $safeTo   = mysqli_real_escape_string($conn, $to);
    $whereConditions[] = "DATE(a.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
}

$whereSQL = '';
if (!empty($whereConditions)) {
    $whereSQL = "WHERE " . implode(" AND ", $whereConditions);
}

$todayQuery = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM audit_logs 
    WHERE DATE(created_at) = CURDATE()
");
$todayTotal = mysqli_fetch_assoc($todayQuery)['total'];

$weekQuery = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM audit_logs 
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
");
$weekTotal = mysqli_fetch_assoc($weekQuery)['total'];

$monthQuery = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM audit_logs 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
");
$monthTotal = mysqli_fetch_assoc($monthQuery)['total'];

$totalQuery = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM audit_logs
");
$grandTotal = mysqli_fetch_assoc($totalQuery)['total'];

$deleteTodayQuery = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM audit_logs 
    WHERE DATE(created_at) = CURDATE()
    AND action LIKE '%delete%'
");
$deleteToday = mysqli_fetch_assoc($deleteTodayQuery)['total'];

$analyticsQuery = "
    SELECT DATE(created_at) as log_date, COUNT(*) as total
    FROM audit_logs
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY log_date ASC
";

$analyticsResult = mysqli_query($conn, $analyticsQuery);

$dates = [];
$totals = [];

while($row = mysqli_fetch_assoc($analyticsResult)){
    $dates[] = $row['log_date'];
    $totals[] = $row['total'];
}

$countQuery = "
    SELECT COUNT(*) as total
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    $whereSQL
";

$countResult = mysqli_query($conn, $countQuery);
$totalRows   = mysqli_fetch_assoc($countResult)['total'];
$totalPages  = ceil($totalRows / $limit);

$moduleQuery = mysqli_query($conn, "
    SELECT module, COUNT(*) as total
    FROM audit_logs
    GROUP BY module
    ORDER BY total DESC
");

$modules = [];
$moduleTotals = [];

while($row = mysqli_fetch_assoc($moduleQuery)){
    $modules[] = $row['module'];
    $moduleTotals[] = $row['total'];
}

$actionTypeQuery = mysqli_query($conn, "
    SELECT 
        CASE 
            WHEN action LIKE '%create%' THEN 'Create'
            WHEN action LIKE '%update%' THEN 'Update'
            WHEN action LIKE '%delete%' THEN 'Delete'
            WHEN action LIKE '%login%' THEN 'Login'
            ELSE 'Other'
        END as action_type,
        COUNT(*) as total
    FROM audit_logs
    GROUP BY action_type
");

$actionTypes = [];
$actionTotals = [];

while($row = mysqli_fetch_assoc($actionTypeQuery)){
    $actionTypes[] = $row['action_type'];
    $actionTotals[] = $row['total'];
}

$logsQuery = "
    SELECT a.id, u.full_name, a.module, a.action, a.created_at
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    $whereSQL
    ORDER BY a.created_at DESC
    LIMIT $limit OFFSET $offset
";

$logs_result = mysqli_query($conn, $logsQuery);
?>

<div class="main-content">

    <div class="page-header">
        <h1>Audit Logs</h1>
        <p>Monitor system activity and administrative actions</p>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <h4>Today</h4>
            <p><?php echo $todayTotal; ?></p>
        </div>

        <div class="summary-card">
            <h4>This Week</h4>
            <p><?php echo $weekTotal; ?></p>
        </div>

        <div class="summary-card">
            <h4>This Month</h4>
            <p><?php echo $monthTotal; ?></p>
        </div>

        <div class="summary-card">
            <h4>Total Logs</h4>
            <p><?php echo $grandTotal; ?></p>
        </div>
    </div>

    <?php if($deleteToday >= 5): ?>
    <div class="security-alert">
        ⚠ High number of deletions today (<?php echo $deleteToday; ?>). Review activity.
    </div>
    <?php endif; ?>

    <div class="analytics-grid">
        <div class="analytics-card">
            <h3>Actions Per Module</h3>
            <div class="chart-container">
                <canvas id="moduleChart"></canvas>
            </div>
        </div>

        <div class="analytics-card">
            <h3>Action Type Distribution</h3>
            <div class="chart-container">
                <canvas id="actionPieChart"></canvas>
            </div>
        </div>

        <div class="analytics-card">
            <h3>Activity Overview (Last 7 Days)</h3>
            <div class="chart-container">  
                <canvas id="activityChart"></canvas>
            </div>
        </div>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search user, action, module..."
                value="<?php echo htmlspecialchars($search); ?>">

            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">

            <button type="submit" class="btn-primary">Filter</button>
            <a href="view_logs.php" class="btn-secondary">Reset</a>
        </form>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($logs_result) > 0): ?>
                    <?php while($log = mysqli_fetch_assoc($logs_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></td>

                            <td>
                                <span class="module-badge">
                                    <?php echo htmlspecialchars($log['module']); ?>
                                </span>
                            </td>

                            <?php
                            $actionText = strtolower($log['action']);
                            $badgeClass = "badge-default";

                            if (strpos($actionText, 'create') !== false) {
                                $badgeClass = "badge-create";
                            } elseif (strpos($actionText, 'update') !== false) {
                                $badgeClass = "badge-update";
                            } elseif (strpos($actionText, 'delete') !== false) {
                                $badgeClass = "badge-delete";
                            } elseif (strpos($actionText, 'login') !== false) {
                                $badgeClass = "badge-login";
                            }
                            ?>

                            <td>
                                <span class="action-badge <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>

                            <td>
                                <?php echo date("M d, Y h:i A", strtotime($log['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center;">No audit logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($totalPages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">
                    &laquo; Previous
                </a>
            <?php endif; ?>

            <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

            <?php if($page < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('activityChart');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [{
            label: 'Actions',
            data: <?php echo json_encode($totals); ?>,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision:0
                }
            }
        }
    }
});

new Chart(document.getElementById('moduleChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($modules); ?>,
        datasets: [{
            label: 'Total Actions',
            data: <?php echo json_encode($moduleTotals); ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, precision:0 } }
    }
});

new Chart(document.getElementById('actionPieChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($actionTypes); ?>,
        datasets: [{
            data: <?php echo json_encode($actionTotals); ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
    }
});

</script>
<?php require_once "../../layouts/footer.php"; ?>
