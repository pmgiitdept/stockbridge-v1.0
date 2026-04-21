<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['admin']);

$users_result = mysqli_query($conn, "
    SELECT u.id, u.full_name, u.email, u.role, u.created_at, u.status,
           p.contact, p.address, p.branch, p.signature, p.profile_picture
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    ORDER BY u.id ASC
");

if(!$users_result){
    die("Query Failed: " . mysqli_error($conn));
}

$role_counts = [];
$result = mysqli_query($conn, "SELECT role, COUNT(*) as total FROM users GROUP BY role");
while($row = mysqli_fetch_assoc($result)) {
    $role_counts[$row['role']] = $row['total'];
}

$status_counts = [];
$res_status = mysqli_query($conn, "SELECT status, COUNT(*) as total FROM users GROUP BY status");
while($row = mysqli_fetch_assoc($res_status)){
    $status_counts[$row['status']] = $row['total'];
}

$res_active = mysqli_query($conn, "SELECT COUNT(*) as total_active FROM users WHERE status='active'");
$total_active = mysqli_fetch_assoc($res_active)['total_active'];

$first_user = mysqli_fetch_assoc($users_result);

mysqli_data_seek($users_result, 0);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
?>

<link rel="icon" href="assets/images/stockbridge-logo.PNG">
<link rel="stylesheet" href="/contract_system/assets/css/manage_users.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const roleCounts = <?php echo json_encode($role_counts); ?>;
const statusCounts = <?php echo json_encode($status_counts); ?>;
const totalActive = <?php echo $total_active; ?>;
</script>
<div class="main-content">

    <div class="page-header">
        <h1>Manage Users</h1>
    </div>

    <div id="pageAlert"></div>

    <div class="charts-grid">
        <div class="chart-card"><canvas id="chart1"></canvas></div>
        <div class="chart-card"><canvas id="chart2"></canvas></div>
        <div class="chart-card"><canvas id="chart3"></canvas></div>
    </div>

    <div class="content-grid">
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                        <tr data-user-id="<?php echo $user['id']; ?>"
                            data-contact="<?php echo htmlspecialchars($user['contact']); ?>"
                            data-address="<?php echo htmlspecialchars($user['address']); ?>"
                            data-branch="<?php echo htmlspecialchars($user['branch']); ?>"
                            data-profile="<?php echo htmlspecialchars($user['profile_picture']); ?>"
                            data-signature="<?php echo htmlspecialchars($user['signature']); ?>"
                        >
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo ucfirst(str_replace("_"," ",$user['role'])); ?></td>
                            <td>
                                <?php if($user['status'] === 'active'): ?>
                                    <span class="status active">Active</span>
                                <?php else: ?>
                                    <span class="status inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date("M d, Y", strtotime($user['created_at'])); ?></td>
                            <td>
                                <button class="edit-btn" data-id="<?php echo $user['id']; ?>">Edit</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="user-info-card" id="user-info-panel">
            <img src="<?php echo $first_user['profile_picture'] ?: '/contract_system/assets/images/default-profile.png'; ?>" 
                alt="Profile" class="profile-img" id="profile-img">
            <h2 id="user-full-name"><?php echo htmlspecialchars($first_user['full_name']); ?></h2>

            <div class="info-section" id="user-email"><strong>Email:</strong> <?php echo htmlspecialchars($first_user['email']); ?></div>
            <div class="info-section" id="user-role"><strong>Role:</strong> <?php echo ucfirst(str_replace("_"," ",$first_user['role'])); ?></div>
            <div class="info-section" id="user-contact"><strong>Contact:</strong> <?php echo htmlspecialchars($first_user['contact']); ?></div>
            <div class="info-section" id="user-address"><strong>Address:</strong> <?php echo htmlspecialchars($first_user['address']); ?></div>
            <div class="info-section" id="user-branch"><strong>Branch:</strong> <?php echo htmlspecialchars($first_user['branch']); ?></div>
            <div class="info-section" id="user-signature"><strong>Signature:</strong><br>
                <?php if($first_user['signature']): ?>
                    <img src="<?php echo $first_user['signature']; ?>" alt="Signature" class="signature-img">
                <?php else: ?>
                    <span>Not uploaded</span>
                <?php endif; ?>
            </div>

            <div class="meta" id="user-created"><strong>Created:</strong> <?php echo date("M d, Y H:i", strtotime($first_user['created_at'])); ?></div>
        </div>
    </div>

</div>

<div id="editUserModal" class="modal">
    <div class="modal-content">
        <h2>Edit User</h2>

        <form id="editUserForm" enctype="multipart/form-data">
            <input type="hidden" name="user_id" id="edit_user_id">

            <!-- BASIC INFO -->
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" id="edit_full_name" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit_email" required>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" id="edit_role" required>
                    <option value="admin">Admin</option>
                    <option value="president">President</option>
                    <option value="operations_manager">Operations Manager</option>
                    <option value="operations_officer">Operations Officer</option>
                    <option value="purchasing_officer">Purchasing Officer</option>
                    <option value="client">Client</option>
                </select>
            </div>

            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact" id="edit_contact">
            </div>

            <div class="form-group">
                <label>Complete Address</label>
                <input type="text" name="address" id="edit_address">
            </div>

            <div class="form-group">
                <label>Branch / Location</label>
                <input type="text" name="branch" id="edit_branch">
            </div>

           <div class="password-section">
                <h3>Change Password</h3>
                <p class="password-note">Leave blank if you don’t want to change the password</p>

                <div class="form-group password-group">
                    <label>New Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="edit_password">
                        <span class="toggle-password" onclick="togglePassword('edit_password', this)">
                            <svg viewBox="0 0 24 24" width="18" height="18">
                                <path fill="currentColor"
                                    d="M12 4.5C7.305 4.5 3.273 7.61 1.5 12c1.773 4.39 5.805 7.5 10.5 7.5s8.727-3.11 10.5-7.5C20.727 7.61 16.695 4.5 12 4.5zm0 12.75A5.25 5.25 0 1 1 12 6.75a5.25 5.25 0 0 1 0 10.5zm0-8.25A3 3 0 1 0 12 15a3 3 0 0 0 0-6z"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="form-group password-group">
                    <label>Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_new_password" id="edit_confirm_password">
                        <span class="toggle-password" onclick="togglePassword('edit_confirm_password', this)">
                            <svg viewBox="0 0 24 24" width="18" height="18">
                                <path fill="currentColor"
                                    d="M12 4.5C7.305 4.5 3.273 7.61 1.5 12c1.773 4.39 5.805 7.5 10.5 7.5s8.727-3.11 10.5-7.5C20.727 7.61 16.695 4.5 12 4.5zm0 12.75A5.25 5.25 0 1 1 12 6.75a5.25 5.25 0 0 1 0 10.5zm0-8.25A3 3 0 1 0 12 15a3 3 0 0 0 0-6z"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <!-- FILES -->
            <div class="form-group">
                <label>Profile Picture</label>
                <input type="file" name="profile_picture">
            </div>

            <div class="form-group">
                <label>Signature</label>
                <input type="file" name="signature">
            </div>

            <button type="submit" class="primary-btn">Save Changes</button>

            <button type="button" id="deleteUserBtn" class="danger-btn">
                Delete User
            </button>
        </form>
    </div>
</div>

<div id="confirmModal" class="modal">
    <div class="modal-content">
        <span class="close-btn-confirm">&times;</span>
        <h2>Confirm Changes</h2>
        <p>Are you sure you want to save these changes?</p>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button id="cancelConfirm" class="primary-btn" style="background:#ccc; color:#000;">Cancel</button>
            <button id="submitConfirm" class="primary-btn">Yes, Save</button>
        </div>
    </div>
</div>

<div id="deleteConfirmModal" class="modal">
    <div class="modal-content">
        <span class="close-btn-delete">&times;</span>
        <h2>Delete User</h2>
        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button id="cancelDelete" class="primary-btn" style="background:#ccc; color:#000;">Cancel</button>
            <button id="submitDelete" class="primary-btn" style="background:#e74c3c;">Yes, Delete</button>
        </div>
    </div>
</div>

<?php require_once "../../layouts/footer.php"; ?>

<script>
function togglePassword(inputId, toggleElement){
    const input = document.getElementById(inputId);
    if(input.type === 'password'){
        input.type = 'text';
        toggleElement.style.color = '#2563eb';
    } else {
        input.type = 'password';
        toggleElement.style.color = '#64748b';
    }
}

function showAlert(message, type = "success") {
    const alertBox = document.getElementById("pageAlert");

    alertBox.innerHTML = `
        <div class="alert ${type}">
            ${message}
        </div>
    `;

    setTimeout(() => {
        alertBox.innerHTML = "";
    }, 10000);
}

document.addEventListener("DOMContentLoaded", function(){

    const editModal = document.getElementById("editUserModal");
    const closeBtn = document.querySelector(".close-btn");
    const confirmModal = document.getElementById("confirmModal");
    const closeConfirm = document.querySelector(".close-btn-confirm");
    const cancelConfirm = document.getElementById("cancelConfirm");
    const submitConfirm = document.getElementById("submitConfirm");

    const editForm = document.getElementById("editUserForm");

    document.querySelectorAll(".edit-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const row = btn.closest("tr");

            document.getElementById("edit_user_id").value = btn.dataset.id;
            document.getElementById("edit_full_name").value = row.cells[1].innerText;
            document.getElementById("edit_email").value = row.cells[2].innerText;
            document.getElementById("edit_role").value = row.cells[3].innerText.toLowerCase().replace(" ","_");
            document.getElementById("edit_contact").value = row.dataset.contact;
            document.getElementById("edit_address").value = row.dataset.address;
            document.getElementById("edit_branch").value = row.dataset.branch;

            editModal.style.display = "block";
        });
    });

    closeBtn.addEventListener("click", () => editModal.style.display = "none");
    closeConfirm.addEventListener("click", () => confirmModal.style.display = "none");
    cancelConfirm.addEventListener("click", () => confirmModal.style.display = "none");
    window.addEventListener("click", e => {
        if(e.target == editModal) editModal.style.display = "none";
        if(e.target == confirmModal) confirmModal.style.display = "none";
    });

    editForm.addEventListener("submit", function(e){
        e.preventDefault();
        confirmModal.style.display = "block"; 
    });

    submitConfirm.addEventListener("click", function(){
        confirmModal.style.display = "none";

        const formData = new FormData(editForm);

        fetch("/contract_system/modules/users/update_user.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                showAlert("User updated successfully!", "success");
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert("Error: " + data.message, "error");
            }
        })
        .catch(err => {
            console.error(err);
            alert("An error occurred while updating.");
        });
    });

    document.querySelectorAll(".table-card tbody tr").forEach(row => {
        row.addEventListener("click", () => {
            document.getElementById("profile-img").src = row.dataset.profile || '/contract_system/assets/images/default-profile.png';
            document.getElementById("user-full-name").innerText = row.cells[1].innerText;
            document.getElementById("user-email").innerHTML = "<strong>Email:</strong> " + row.cells[2].innerText;
            document.getElementById("user-role").innerHTML = "<strong>Role:</strong> " + row.cells[3].innerText;
            document.getElementById("user-contact").innerHTML = "<strong>Contact:</strong> " + (row.dataset.contact || "Not set");
            document.getElementById("user-address").innerHTML = "<strong>Address:</strong> " + (row.dataset.address || "Not set");
            document.getElementById("user-branch").innerHTML = "<strong>Branch:</strong> " + (row.dataset.branch || "Not set");
            document.getElementById("user-signature").innerHTML = "<strong>Signature:</strong><br>" +
                (row.dataset.signature ? `<img src="${row.dataset.signature}" class="signature-img">` : "Not uploaded");
            document.getElementById("user-created").innerHTML = "<strong>Created At:</strong> " + row.cells[5].innerText;
        });
    });
});

const deleteBtn = document.getElementById("deleteUserBtn");
const deleteModal = document.getElementById("deleteConfirmModal");
const closeDelete = document.querySelector(".close-btn-delete");
const cancelDelete = document.getElementById("cancelDelete");
const submitDelete = document.getElementById("submitDelete");

deleteBtn.addEventListener("click", () => {
    deleteModal.style.display = "block";
});

closeDelete.addEventListener("click", () => deleteModal.style.display = "none");
cancelDelete.addEventListener("click", () => deleteModal.style.display = "none");
window.addEventListener("click", e => {
    if(e.target == deleteModal) deleteModal.style.display = "none";
});

submitDelete.addEventListener("click", () => {
    deleteModal.style.display = "none";

    const userId = document.getElementById("edit_user_id").value;

    fetch("/contract_system/modules/users/delete_user.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            showAlert("User deleted successfully!", "success");
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert("Error: " + data.message, "error");
        }
    })
    .catch(err => {
        console.error(err);
        alert("An error occurred while deleting.");
    });
});

document.addEventListener("DOMContentLoaded", function() {

    const roleCounts = <?php echo json_encode($role_counts); ?>;
    const statusCounts = <?php 
        $status_counts = [];
        $result = mysqli_query($conn, "SELECT status, COUNT(*) as total FROM users GROUP BY status");
        while($row = mysqli_fetch_assoc($result)) $status_counts[$row['status']] = $row['total'];
        echo json_encode($status_counts);
    ?>;
    const totalActive = <?php 
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE status='active'");
        $row = mysqli_fetch_assoc($result);
        echo $row['total'];
    ?>;

    const chart1 = new Chart(document.getElementById('chart1'), {
        type: 'bar',
        data: {
            labels: Object.keys(roleCounts).map(r => r.replace("_"," ").toUpperCase()),
            datasets: [{
                label: 'Users',
                data: Object.values(roleCounts),
                backgroundColor: ctx => {
                    const gradient = ctx.chart.ctx.createLinearGradient(0,0,0,150);
                    gradient.addColorStop(0, '#60a5fa'); 
                    gradient.addColorStop(1, '#2563eb'); 
                    return gradient;
                },
                borderRadius: 8,
                barThickness: 25
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Users by Role', font: { size: 14, weight: '600' } },
                tooltip: { 
                    backgroundColor: '#1e293b',
                    titleColor: '#f8fafc',
                    bodyColor: '#f8fafc',
                    callbacks: { label: ctx => `${ctx.parsed.y} user(s)` } 
                }
            },
            scales: {
                x: { 
                    ticks: { color: '#1e293b', font: { size: 10 } }, 
                    grid: { display: false } 
                },
                y: { 
                    beginAtZero: true, 
                    ticks: { color: '#1e293b', stepSize: 1 }, 
                    grid: { color: 'rgba(0,0,0,0.05)' } 
                }
            }
        }
    });

    const chart2 = new Chart(document.getElementById('chart2'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(statusCounts).map(s => s.toUpperCase()),
            datasets: [{
                data: Object.values(statusCounts),
                backgroundColor: ['#22c55e', '#facc15'],
                borderColor: '#f1f5f9',
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: { 
                    position: 'bottom', 
                    labels: { font: { size: 11 }, color: '#1e293b', boxWidth: 14 } 
                },
                title: { display: true, text: 'Users by Status', font: { size: 14, weight: '600' } },
                tooltip: { 
                    backgroundColor: '#1e293b',
                    titleColor: '#f8fafc',
                    bodyColor: '#f8fafc',
                    callbacks: { label: ctx => `${ctx.label}: ${ctx.parsed} user(s)` } 
                }
            }
        }
    });

    const ctx3 = document.getElementById('chart3').getContext('2d');
    ctx3.clearRect(0,0,ctx3.canvas.width,ctx3.canvas.height);

    const gradient = ctx3.createLinearGradient(0,0,ctx3.canvas.width,ctx3.canvas.height);
    gradient.addColorStop(0, '#3b82f6');
    gradient.addColorStop(1, '#60a5fa');
    ctx3.fillStyle = gradient;
    ctx3.roundRect(0, 0, ctx3.canvas.width, ctx3.canvas.height, 16);
    ctx3.fill();

    ctx3.font = 'bold 18px Inter';
    ctx3.fillStyle = '#ffffff';
    ctx3.textAlign = 'center';
    ctx3.textBaseline = 'middle';
    ctx3.fillText('Active Users', ctx3.canvas.width/2, ctx3.canvas.height/2 - 15);

    ctx3.font = 'bold 32px Inter';
    ctx3.fillText(`${totalActive}`, ctx3.canvas.width/2, ctx3.canvas.height/2 + 20);

    CanvasRenderingContext2D.prototype.roundRect = function(x, y, w, h, r) {
        if (w<2*r) r=w/2; if(h<2*r) r=h/2;
        this.beginPath();
        this.moveTo(x+r, y);
        this.arcTo(x+w, y, x+w, y+h, r);
        this.arcTo(x+w, y+h, x, y+h, r);
        this.arcTo(x, y+h, x, y, r);
        this.arcTo(x, y, x+w, y, r);
        this.closePath();
        return this;
    };
});
</script>