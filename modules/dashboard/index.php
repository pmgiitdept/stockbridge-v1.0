<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['admin']);

$result = mysqli_query($conn, "SELECT COUNT(*) as total_users FROM users");
$total_users = mysqli_fetch_assoc($result)['total_users'];

$pending_forms = 0; 

$result = mysqli_query($conn, "SELECT COUNT(DISTINCT branch) as total_branches FROM user_profiles");
$total_branches = mysqli_fetch_assoc($result)['total_branches'];

$recent_users_result = mysqli_query($conn, "
    SELECT full_name, role, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
");

$chartLabels = [];
$chartData = [];
$resultChart = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as total
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY created_at ASC
");
if ($resultChart) {
    while ($row = $resultChart->fetch_assoc()) {
        $chartLabels[] = $row['month'];
        $chartData[] = (int)$row['total'];
    }
}
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="main-content">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']); ?></h1>
    <p>Here’s a quick overview of the system status:</p>

    <div class="summary-cards">
        <div class="card" style="background-color:#3b82f6;">
            <div class="icon">👤</div>
            <div>
                <h4>Total Users</h4>
                <p><?= $total_users ?></p>
            </div>
            <div class="trend up"><span class="arrow">↑</span>5%</div>
        </div>

        <div class="card" style="background-color:#f59e0b;">
            <div class="icon">📄</div>
            <div>
                <h4>Pending Forms</h4>
                <p><?= $pending_forms ?></p>
            </div>
            <div class="trend down"><span class="arrow">↓</span>2%</div>
        </div>

        <div class="card" style="background-color:#10b981;">
            <div class="icon">🏢</div>
            <div>
                <h4>Active Branches</h4>
                <p><?= $total_branches ?></p>
            </div>
            <div class="trend up"><span class="arrow">↑</span>8%</div>
        </div>
    </div>

    <h2>Quick Actions</h2>
    <div class="quick-actions">
        <div class="quick-card">
            <div class="icon">👤</div>
            <a href="../users/manage_users.php" class="primary-btn">Manage Users</a>
            <small>View and edit user accounts</small>
        </div>
        <div class="quick-card">
            <div class="icon">➕</div>
            <a href="../users/create_user.php" class="primary-btn">Create User</a>
            <small>Add a new admin or staff account</small>
        </div>
        <div class="quick-card">
            <div class="icon">📄</div>
            <a href="../contracts/listed_contracts.php" class="primary-btn">Contract Management</a>
            <small>Review and manage contracts</small>
        </div>
        <div class="quick-card">
            <div class="icon">📝</div>
            <a href="../forms/forms_status.php" class="primary-btn">Forms Management</a>
            <small>View submitted forms</small>
        </div>
    </div>

    <div class="table-card">
        <div class="page-header">
            <h2>Recent Users Added</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = mysqli_fetch_assoc($recent_users_result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['full_name']); ?></td>
                            <td><?= ucfirst(str_replace("_"," ",$user['role'])); ?></td>
                            <td><?= date("M d, Y H:i", strtotime($user['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "../../layouts/footer.php"; ?>