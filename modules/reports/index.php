<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['admin','president']);

$reports_result = mysqli_query($conn, "
    SELECT r.*, u.full_name, u.email
    FROM user_reports r
    LEFT JOIN users u ON r.user_id = u.id
    ORDER BY r.id ASC
");

if(!$reports_result){
    die("Query Failed: " . mysqli_error($conn));
}

$first_report = mysqli_fetch_assoc($reports_result);
mysqli_data_seek($reports_result, 0);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
?>

<link rel="icon" href="assets/images/stockbridge-logo.PNG">
<link rel="stylesheet" href="/contract_system/assets/css/manage_users.css">

<div class="main-content">

    <div class="page-header">
        <h1>User Reports & Requests</h1>
    </div>

    <div id="pageAlert"></div>

    <div class="content-grid">
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Reason(s) / Request(s)</th>
                        <th>Status</th>
                        <th>Action Taken</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($report = mysqli_fetch_assoc($reports_result)): ?>
                    <tr data-report-id="<?php echo $report['id']; ?>"
                        data-user-id="<?php echo $report['user_id']; ?>"
                        data-type="<?php echo htmlspecialchars($report['type']); ?>"
                        data-description="<?php echo htmlspecialchars($report['description']); ?>"
                        data-action="<?php echo htmlspecialchars($report['action_taken']); ?>"
                        data-status="<?php echo $report['status']; ?>"
                    >
                        <td><?php echo $report['id']; ?></td>
                        <td><?php echo htmlspecialchars($report['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($report['email']); ?></td>
                        <td><?php echo ucfirst(str_replace("_"," ",$report['type'])); ?></td>
                        <td><?php echo htmlspecialchars($report['description']); ?></td>
                        <td>
                            <?php
                                $status_class = match($report['status']) {
                                    'complete' => 'complete',
                                    'in_progress' => 'in-progress',
                                    default => 'pending'
                                };
                            ?>
                            <span class="status <?php echo $status_class; ?>">
                                <?php echo ucfirst(str_replace("_"," ",$report['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if(!empty($report['action_taken'])): ?>
                                <span class="status in-progress">Action Taken</span>
                            <?php else: ?>
                                <span class="status pending">No Action</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($report['status'] !== 'complete'): ?>
                                <button class="action-btn" data-id="<?php echo $report['id']; ?>">Take Action</button>
                            <?php else: ?>
                                <span>—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="user-info-card" id="report-info-panel">
            <h2 id="report-user-name"><?php echo htmlspecialchars($first_report['full_name'] ?? 'N/A'); ?></h2>
            <div class="info-section" id="report-email"><strong>Email:</strong> <?php echo htmlspecialchars($first_report['email'] ?? 'N/A'); ?></div>
            <div class="info-section" id="report-type"><strong>Type:</strong> <?php echo ucfirst(str_replace("_"," ",$first_report['type'] ?? 'N/A')); ?></div>
            <div class="info-section" id="report-description"><strong>Description:</strong> <?php echo htmlspecialchars($first_report['description'] ?? 'N/A'); ?></div>
            <div class="info-section" id="report-status"><strong>Status:</strong> <?php echo $first_report['status'] ?? 'N/A'; ?></div>
            <div class="info-section" id="report-action"><strong>Action Taken:</strong> <?php echo htmlspecialchars($first_report['action_taken'] ?? '—'); ?></div>
            <div class="meta" id="report-created"><strong>Created:</strong> <?php echo date("M d, Y H:i", strtotime($first_report['created_at'] ?? 'now')); ?></div>
            <div class="meta" id="report-updated"><strong>Last Updated:</strong> <?php echo date("M d, Y H:i", strtotime($first_report['updated_at'] ?? 'now')); ?></div>
        </div>
    </div>

</div>

<div id="actionModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h2 class="modal-title">Take Action</h2>

        <div class="report-summary">
            <div class="summary-row">
                <span class="label">Report ID:</span>
                <span id="modal-report-id" class="value">—</span>
            </div>
            <div class="summary-row">
                <span class="label">User:</span>
                <span id="modal-report-user" class="value">—</span>
            </div>
            <div class="summary-row">
                <span class="label">Description:</span>
                <p id="modal-report-desc" class="value description">—</p>
            </div>
            <div class="summary-row">
                <span class="label">Current Status:</span>
                <span id="modal-report-status" class="status pending">Pending</span>
            </div>
        </div>

        <form id="actionForm">
            <input type="hidden" name="report_id" id="action_report_id">

            <div class="form-group">
                <label for="action_taken">Action Taken</label>
                <textarea name="action_taken" id="action_taken" rows="4" placeholder="Enter action taken..." required></textarea>
            </div>

            <div class="form-group">
                <label for="action_status">Status</label>
                <select name="status" id="action_status" required>
                    <option value="in_progress">In Progress</option>
                    <option value="complete">Complete</option>
                </select>
            </div>

            <button type="submit" class="primary-btn">Save Action</button>
        </form>
    </div>
</div>

<?php require_once "../../layouts/footer.php"; ?>

<script>
function showAlert(message, type="success"){
    const alertBox = document.getElementById("pageAlert");
    alertBox.innerHTML = `<div class="alert ${type}">${message}</div>`;
    setTimeout(()=>{ alertBox.innerHTML=""; },10000);
}

document.addEventListener("DOMContentLoaded", function(){
    const actionModal = document.getElementById("actionModal");
    const closeBtn = actionModal.querySelector(".close-btn");
    const actionForm = document.getElementById("actionForm");

    document.querySelectorAll(".table-card tbody tr").forEach(row => {
        row.addEventListener("click", ()=>{
            document.getElementById("report-user-name").innerText = row.cells[1].innerText;
            document.getElementById("report-email").innerHTML = "<strong>Email:</strong> "+row.cells[2].innerText;
            document.getElementById("report-type").innerHTML = "<strong>Type:</strong> "+row.cells[3].innerText;
            document.getElementById("report-description").innerHTML = "<strong>Description:</strong> "+row.cells[4].innerText;
            
            const statusText = row.dataset.status.replace("_"," ");
            let statusClass = "";
            if(row.dataset.status === "complete") statusClass = "complete";
            else if(row.dataset.status === "in_progress") statusClass = "in-progress";
            else statusClass = "pending";

            document.getElementById("report-status").innerHTML = `<strong>Status:</strong> <span class="status ${statusClass}">${statusText}</span>`;

            document.getElementById("report-action").innerHTML = "<strong>Action Taken:</strong> "+(row.dataset.action||'—');
            document.getElementById("report-created").innerHTML = "<strong>Created:</strong> "+row.cells[5].innerText;
            document.getElementById("report-updated").innerHTML = "<strong>Last Updated:</strong> "+(row.dataset.updated || 'N/A');
        });
    });

    let statusClassMap = {
    'pending': 'pending',
    'in_progress': 'in-progress',
    'complete': 'complete'
    };

    document.querySelectorAll(".action-btn").forEach(btn => {
        btn.addEventListener("click", e => {
            e.stopPropagation();

            const row = btn.closest("tr");
            const reportId = btn.dataset.id;

            document.getElementById("action_report_id").value = reportId;
            document.getElementById("action_taken").value = row.dataset.action || '';
            document.getElementById("action_status").value = row.dataset.status;

            document.getElementById("modal-report-id").innerText = reportId;
            document.getElementById("modal-report-user").innerText = row.cells[1].innerText;
            document.getElementById("modal-report-desc").innerText = row.cells[4].innerText;

            const modalStatus = document.getElementById("modal-report-status");
            modalStatus.innerText = row.dataset.status.replace("_"," ");
            modalStatus.className = 'status ' + (statusClassMap[row.dataset.status] || 'pending');

            document.getElementById("actionModal").style.display = "flex";
        });
    });

    closeBtn.addEventListener("click", ()=>actionModal.style.display="none");
    window.addEventListener("click", e=>{ if(e.target==actionModal) actionModal.style.display="none"; });

    actionForm.addEventListener("submit", function(e){
        e.preventDefault();
        const formData = new FormData(this);
        fetch("/contract_system/modules/reports/update_report.php", {
            method: "POST",
            body: formData
        }).then(res=>res.json()).then(data=>{
            if(data.success){
                showAlert("Action saved successfully!", "success");
                setTimeout(()=>location.reload(), 1000);
            } else showAlert("Error: "+data.message,"error");
        }).catch(err=>{
            console.error(err);
            showAlert("Network error while saving action.","error");
        });
    });
});
</script>