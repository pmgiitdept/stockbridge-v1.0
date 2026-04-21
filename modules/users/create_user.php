<?php
require_once "../../config/database.php";
require_once "../../core/session.php";  
require_once "../../core/auth.php";

authorize(['admin']);

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {

        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = "Email already exists.";
        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (full_name, email, password, role) 
                 VALUES (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $full_name,
                $email,
                $hashedPassword,
                $role
            );

            if (mysqli_stmt_execute($stmt)) {

                logAudit(
                    $conn,
                    $_SESSION['user_id'],
                    "Created new user: $email",
                    "User Management"
                );

                $message = "User created successfully.";
            } else {
                $error = "Something went wrong.";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($check);
    }
}

$users_result = mysqli_query($conn, "SELECT id, full_name, email, role, created_at FROM users ORDER BY id ASC");

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
?>

<link rel="stylesheet" href="/contract_system/assets/css/users.css">

<div class="main-content">

    <div class="page-header">
        <h1>Create User</h1>
        <a href="manage_users.php" class="back-btn">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert success"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="content-grid">

        <div class="form-card">
            <form method="POST">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group password-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                            <!-- EYE (VISIBLE) -->
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 
                                0 8.268 2.943 9.542 7-1.274 4.057-5.065 
                                7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>

                            <!-- EYE OFF (HIDDEN) -->
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 
                                0-8.27-2.943-9.544-7a9.956 9.956 0 
                                012.293-3.95M6.7 6.7A9.953 9.953 0 
                                0112 5c4.478 0 8.27 2.943 9.544 
                                7a9.97 9.97 0 01-4.043 5.28M6.7 
                                6.7L3 3m3.7 3.7l10.6 10.6"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group password-group">
                    <label>Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">
                            <!-- EYE (VISIBLE) -->
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 
                                0 8.268 2.943 9.542 7-1.274 4.057-5.065 
                                7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>

                            <!-- EYE OFF (HIDDEN) -->
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 
                                0-8.27-2.943-9.544-7a9.956 9.956 0 
                                012.293-3.95M6.7 6.7A9.953 9.953 0 
                                0112 5c4.478 0 8.27 2.943 9.544 
                                7a9.97 9.97 0 01-4.043 5.28M6.7 
                                6.7L3 3m3.7 3.7l10.6 10.6"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>User Role</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="president">President</option>
                        <option value="operations_manager">Operations Manager</option>
                        <option value="operations_officer">Operations Officer</option>
                        <option value="purchasing_officer">Purchasing Officer</option>
                        <option value="client">Client</option>
                    </select>
                </div>

                <button type="submit" class="primary-btn">Create User</button>

            </form>
        </div>

        <!-- USERS TABLE -->
        <div class="users-section">
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo ucfirst(str_replace("_"," ",$user['role'])); ?></td>
                                <td><?php echo date("M d, Y H:i", strtotime($user['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId, button) {
    const input = document.getElementById(fieldId);

    if (input.type === "password") {
        input.type = "text";
        button.classList.add("active");
    } else {
        input.type = "password";
        button.classList.remove("active");
    }
}
</script>

<?php require_once "../../layouts/footer.php"; ?>