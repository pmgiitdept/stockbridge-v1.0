<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/contract_system/config/database.php';

$userId = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT u.full_name, u.email, u.role, u.status,
           p.contact, p.address, p.branch, p.profile_picture, p.signature
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
?>

<div class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-logo">
            <img src="/contract_system/assets/images/pmgi.png" alt="System Logo">
            <h2>Stockbridge CMS</h2>
        </div>

        <ul class="nav-links">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="../dashboard/index.php">Dashboard</a></li>

                <li class="menu-label">User Management</li>
                <li><a href="../users/manage_users.php">Manage Users</a></li>
                <li><a href="../users/create_user.php">Create User</a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'client'): ?>
                <li><a href="../client/dashboard.php">Dashboard</a></li>
                
                <li class="menu-label">Item Lists</li>
                <li><a href="../client/item_lists.php">Item Lists</a></li>

                <li class="menu-label">Forms</li>
                <li><a href="../client/create_forms.php">Create Form</a></li>
                <li><a href="../client/submitted_forms.php">Submitted Forms</a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'operations_officer'): ?>
                <li><a href="../operations_officer/dashboard.php">Dashboard</a></li>
                
                <li class="menu-label">Master Lists</li>
                <li><a href="../operations_officer/master_lists.php">Master Lists</a></li>

                <li class="menu-label">Forms Lists</li>
                <li><a href="../operations_officer/forms_lists.php">List of Submitted Forms</a></li>
                <li><a href="../operations_officer/smrf_lists.php">View SMRF Lists</a></li>

                <li class="menu-label">Purchase Requests</li>
                <li><a href="../forms/submitted_pr.php">Submitted PR</a></li>

                <li class="menu-label">Contracts</li>
                <li><a href="../contracts/listed_contracts.php">Listed Contracts</a></li>

                <li class="menu-label">Budget Monitoring</li>
                <li><a href="../budgets/overall_budget.php">Overall Budgets</a></li>
                <li><a href="../budgets/supplies_monitoring.php">Supplies Monitoring</a></li>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'], ['admin','president'])): ?>
                <li class="menu-label">Forms Management</li>
                <li><a href="../forms/forms_status.php">Forms Status</a></li>

                <li class="menu-label">Contract Management</li>
                <li><a href="../contracts/listed_contracts.php">Listed Contracts</a></li>
                
                <li class="menu-label">Reports & Logs</li>
                <li><a href="../reports/index.php">Reports</a></li>
                <li><a href="../audit_logs/view_logs.php">Audit Logs</a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'purchasing_officer'): ?>
                <li><a href="../purchasing/dashboard.php">Dashboard</a></li>

                <li class="menu-label">Purchasing</li>
                <li><a href="../purchasing/manage_price_lists.php">Manage Price Lists</a></li>
                <li><a href="../forms/purchase_requests.php">Purchase Requests Forms</a></li>
                
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['role'], ['president', 'purchasing_officer', 'operations_manager'])): ?>
                <li class="menu-label">Results and Status</li>
                <li><a href="../forms/forms_status.php">Forms Status</a></li>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'], ['president', 'purchasing_officer'])): ?>
                <li class="menu-label">Logs</li>
                <li><a href="../purchasing/price_lists_logs.php">Price List Logs</a></li>
            <?php endif; ?>

        </ul>
    </div>

    <div class="sidebar-bottom">
        <div class="sidebar-icons">
            <div class="icon" onclick="openModal('settingsModal')">
                <i class="fas fa-cog"></i>
            </div>

            <div class="icon profile-icon" onclick="openModal('profileModal')">
                <i class="fas fa-user-circle"></i>
            </div>

            <div class="icon" onclick="openModal('helpModal')">
                <i class="fas fa-question-circle"></i>
            </div>
        </div>
        <div class="logout-section">
            <a href="javascript:void(0)" class="logout-btn" onclick="openModal('logoutModal')">Logout</a>
        </div>
    </div>
</div>

<div id="profileModal" class="custom-modal">
    <div class="modal-content profile-modal">
        <span class="close-btn" onclick="closeModal('profileModal')">&times;</span>
        <div class="profile-container">

            <div class="profile-left">
                <div class="profile-avatar">
                    <?php if (!empty($userData['profile_picture'])): ?>
                        <img src="/contract_system/<?php echo $userData['profile_picture']; ?>" alt="Profile Picture">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>

                <span class="role-badge"><?php echo ucfirst(str_replace('_',' ',$userData['role'])); ?></span>
                <span class="status-badge <?php echo $userData['status']; ?>">
                    <?php echo ucfirst($userData['status']); ?>
                </span>
            </div>

            <div class="profile-right">
                <h3><?php echo htmlspecialchars($userData['full_name']); ?></h3>
                <p class="email"><?php echo htmlspecialchars($userData['email']); ?></p>

                <div class="info-grid">
                    <div><strong>Contact:</strong> <?php echo $userData['contact'] ?? 'N/A'; ?></div>
                    <div><strong>Branch:</strong> <?php echo $userData['branch'] ?? 'N/A'; ?></div>
                    <div><strong>Address:</strong> <?php echo $userData['address'] ?? 'N/A'; ?></div>
                </div>

                <?php if (!empty($userData['signature'])): ?>
                    <div class="profile-signature">
                        <h4>Signature</h4>
                        <img src="/contract_system/<?php echo $userData['signature']; ?>" alt="Signature">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="settingsModal" class="custom-modal">
    <div class="modal-content settings-modal">
        <span class="close-btn" onclick="closeModal('settingsModal')">&times;</span>
        <h3>System Settings</h3>

        <div class="tabs">
            <button class="tab-btn active" data-tab="tab-general">General</button>
            <button class="tab-btn" data-tab="tab-notifications">Notifications</button>
            <button class="tab-btn" data-tab="tab-logs">Audit & Logs</button>
        </div>

        <div class="tab-content active" id="tab-general">
            <form id="generalSettingsForm">
                <div class="form-group">
                    <label>System Name</label>
                    <input type="text" name="system_name" value="PMGI Contract System" required>
                </div>

                <div class="form-group">
                    <label>Default Timezone</label>
                    <select name="timezone" required>
                        <option value="Asia/Manila" selected>Asia / Manila</option>
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">America / New_York</option>
                        <option value="Europe/London">Europe / London</option>
                        <!-- Add more as needed -->
                    </select>
                </div>

                <div class="form-group">
                    <label>Default User Role</label>
                    <select name="default_role" required>
                        <option value="client">Client</option>
                        <option value="operations_officer">Operations Officer</option>
                        <option value="operations_manager">Operations Manager</option>
                        <option value="purchasing_officer">Purchasing Officer</option>
                        <option value="president">President</option>
                        <option value="admin" selected>Admin</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary">Save General Settings</button>
            </form>
        </div>

        <div class="tab-content" id="tab-notifications">
            <form id="notificationsSettingsForm">
                <div class="form-group">
                    <label>Email Notifications</label>
                    <select name="email_notifications">
                        <option value="enabled" selected>Enabled</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notification Email</label>
                    <input type="email" name="notification_email" value="notifications@pmgi.com" required>
                </div>

                <button type="submit" class="btn-primary">Save Notification Settings</button>
            </form>
        </div>

        <div class="tab-content" id="tab-logs">
            <form id="logsSettingsForm">
                <div class="form-group">
                    <label>Keep Audit Logs For (Days)</label>
                    <input type="number" name="audit_retention" min="7" max="365" value="90" required>
                </div>

                <div class="form-group">
                    <label>Enable Detailed Logging</label>
                    <select name="detailed_logging">
                        <option value="enabled" selected>Enabled</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary">Save Logging Settings</button>
            </form>
        </div>

    </div>
</div>

<div id="helpModal" class="custom-modal">
    <div class="modal-content help-modal">
        <span class="close-btn" onclick="closeModal('helpModal')">&times;</span>
        <h3>IT Support</h3>

        <div class="help-section">

            <div class="support-info card">
                <h4><i class="fas fa-headset"></i> Contact IT Support</h4>
                <p>If you experience system issues, reach out to our IT team:</p>
                <p><i class="fas fa-envelope text-blue"></i> 
                   <a href="mailto:support@pmgi.com">itdept.pmgi@gmail.com</a></p>
                <p><i class="fas fa-phone text-green"></i> 
                   <a href="tel:+639000000000">+63 919 066 6137</a></p>
            </div>

            <div class="troubleshooting card">
                <h4><i class="fas fa-lightbulb"></i> Troubleshooting Tips</h4>
                <ul>
                    <li>Clear your browser cache and refresh the page.</li>
                    <li>Ensure your internet connection is stable.</li>
                    <li>Check module access based on your user role.</li>
                    <li>If issues persist, contact IT with details.</li>
                </ul>
            </div>

            <div class="quick-contact card">
                <h4><i class="fas fa-envelope-open-text"></i> Submit a Support Request</h4>
                <form id="supportForm">
                    <div class="form-group">
                        <label for="issue">Describe the Issue</label>
                        <textarea name="issue" id="issue" placeholder="Enter the problem..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="email">Your Email</label>
                        <input type="email" name="email" id="email" 
                               placeholder="Your email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                    </div>
                    <button type="submit" class="btn-primary">Submit Request</button>
                </form>
                <div id="supportMessage"></div>
            </div>
        </div>
    </div>
</div>

<div id="logoutModal" class="custom-modal">
    <div class="modal-content logout-modal">
        <span class="close-btn" onclick="closeModal('logoutModal')">&times;</span>
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button class="btn-primary" style="background:#ccc; color:#000;" onclick="closeModal('logoutModal')">Cancel</button>
            <button id="confirmLogoutBtn" class="btn-primary" style="background:#e74c3c;">Logout</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script>
document.getElementById("supportForm").addEventListener("submit", function(e){
    e.preventDefault();
    const formData = new FormData(this);

    fetch("/contract_system/modules/help/submit_support.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const messageDiv = document.getElementById("supportMessage");
        if(data.success){
            messageDiv.style.color = "green";
            messageDiv.textContent = "Support request submitted successfully!";
            this.reset();
        } else {
            messageDiv.style.color = "red";
            messageDiv.textContent = "Error: " + data.message;
        }
    })
    .catch(err=>{
        const messageDiv = document.getElementById("supportMessage");
        messageDiv.style.color = "red";
        messageDiv.textContent = "Network error: Could not submit request.";
    });
});

function openModal(id) {
    const modals = document.querySelectorAll(".custom-modal");
    modals.forEach(modal => {
        modal.style.display = "none";
    });

    document.getElementById(id).style.display = "flex";
}

function closeModal(id) {
    document.getElementById(id).style.display = "none";
}

window.onclick = function(event) {
    const modals = document.querySelectorAll(".custom-modal");
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });
};

document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
        document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
        btn.classList.add("active");
        document.getElementById(btn.dataset.tab).classList.add("active");
    });
});

document.getElementById("generalSettingsForm").addEventListener("submit", function(e){
    e.preventDefault();
    const formData = new FormData(this);
    fetch("/contract_system/modules/settings/save_general.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) alert("General settings saved!");
        else alert("Error: " + data.message);
    });
});

document.getElementById("notificationsSettingsForm").addEventListener("submit", function(e){
    e.preventDefault();
    const formData = new FormData(this);
    fetch("/contract_system/modules/settings/save_notifications.php", {
        method: "POST",
        body: formData
    }).then(res => res.json()).then(data=>{
        if(data.success) alert("Notification settings saved!");
        else alert("Error: "+data.message);
    });
});

document.getElementById("logsSettingsForm").addEventListener("submit", function(e){
    e.preventDefault();
    const formData = new FormData(this);
    fetch("/contract_system/modules/settings/save_logs.php", {
        method: "POST",
        body: formData
    }).then(res => res.json()).then(data=>{
        if(data.success) alert("Logging settings saved!");
        else alert("Error: "+data.message);
    });
});

document.getElementById("confirmLogoutBtn").addEventListener("click", function() {
    window.location.href = "/contract_system/logout.php";
});
</script>