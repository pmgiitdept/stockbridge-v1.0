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
    $role = $_POST['role'];

    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $error = "All fields are required.";
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
<link rel="icon" href="assets/images/stockbridge-logo.PNG">

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

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
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

        <div class="table-card">
            <h2>Existing Users</h2>
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

<?php require_once "../../layouts/footer.php"; ?>
