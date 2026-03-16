<?php
session_start();
require_once "config/database.php";
require_once "core/audit.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email     = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    // Basic validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm)) {
        $error = "All fields are required.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    }
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    }
    elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    }
    else {

        $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");

        if (mysqli_num_rows($check) > 0) {
            $error = "Email already exists.";
        } else {

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            mysqli_query($conn, "
                INSERT INTO users (full_name, email, password, role, status, failed_attempts)
                VALUES ('$full_name', '$email', '$hashed', 'user', 'active', 0)
            ");

            $user_id = mysqli_insert_id($conn);

            logAudit($conn, $user_id, "New user registered: $email", "Registration");

            header("Location: login.php?registered=1");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="assets/images/stockbridge-logo-title.PNG">
    <title>Register | Stockbridge Contract System</title>
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>

<div class="top-right-buttons">
    <button class="secondary-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10
                     10-4.48 10-10S17.52 2 12 2zm0 17h-1v-1h1v1zm1.07-7.75l-.9.92
                     C11.45 13.9 11 14.5 11 16h-2v-.5c0-1 .45-1.5 1.07-2.08l1.2-1.2
                     c.37-.36.73-.75.73-1.42 0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5
                     1.5H7c0-1.66 1.34-3 3-3s3 1.34 3 3c0 .88-.36 1.34-.93 1.75z"/>
        </svg>
        Help
        <span class="tooltip">Submit a request, concern, or suggestion</span>
    </button>

    <button class="secondary-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10
                     10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm1.07-7.75l-.9.92
                     C11.45 13.9 11 14.5 11 16h-2v-.5c0-1 .45-1.5 1.07-2.08l1.2-1.2
                     c.37-.36.73-.75.73-1.42 0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5
                     1.5H7c0-1.66 1.34-3 3-3s3 1.34 3 3c0 .88-.36 1.34-.93 1.75z"/>
        </svg>
        About
        <span class="tooltip2">Information about this website</span>
    </button>
</div>

<div class="login-wrapper">
    <div class="login-container">

        <h2>Create Account</h2>
        <p>Register for Secure Access to the Contract Lifecycle System</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required>
            </div>

            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button type="submit" class="login-btn">
                Create Account
            </button>

            <div class="divider">
                <span>or</span>
            </div>

            <div class="register-section">
                <p>
                    Already have an account?
                    <a href="login.php">Login Here</a>
                </p>
            </div>

        </form>
    </div>

    <div class="login-brand">
        <div class="brand-content">
            <img src="assets/images/stockbridge-logo.PNG" class="brand-logo">

            <h1 class="brand-title">
                Contract Management and Lifecycle System
            </h1>

            <p class="brand-subtitle">
                Secure • Reliable • Automate • Approve • Archive
            </p>
        </div>
    </div>
</div>
<div id="helpModal" class="modal">
    <div class="modal-content modal-glass">
        <span class="close-btn">&times;</span>
        <h2 class="modal-title">Help & Suggestions</h2>
        <p class="modal-subtitle">
            Submit a request, report a concern, or provide a suggestion. Our IT team will review it and respond accordingly.
        </p>

        <form id="helpForm">
            <div class="form-group">
                <label for="help_name">Full Name</label>
                <input type="text" id="help_name" name="name" required placeholder="Your full name">
            </div>

            <div class="form-group">
                <label for="help_email">Email Address</label>
                <input type="email" id="help_email" name="email" required placeholder="Your email">
            </div>

            <div class="form-group">
                <label for="help_message">Message / Details</label>
                <textarea id="help_message" name="message" rows="4" required placeholder="Describe your issue or suggestion..."></textarea>
            </div>

            <button type="submit" class="primary-btn">Submit</button>
        </form>
        <div id="helpAlert" style="margin-top:15px;"></div>
    </div>
</div>

<div id="aboutModal" class="modal">
    <div class="modal-content modal-glass">
        <span class="close-btn">&times;</span>
        <h2 class="modal-title">About PMGI Contract System</h2>
        <p class="modal-subtitle">
            This Contract Management System is designed to provide secure, reliable, and enterprise-ready tools for managing all contract operations efficiently. 
        </p>
        <ul class="about-list">
            <li><strong>Version:</strong> 1.0.0</li>
            <li><strong>Developed by:</strong> IT Department</li>
            <li><strong>Contact:</strong> support@pmgi.com</li>
            <li><strong>Last Updated:</strong> February 2026</li>
        </ul>
        <button class="primary-btn" onclick="document.getElementById('aboutModal').style.display='none'">Close</button>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function(){

    const helpBtn = document.querySelector(".top-right-buttons button:nth-child(1)");
    const aboutBtn = document.querySelector(".top-right-buttons button:nth-child(2)");

    const helpModal = document.getElementById("helpModal");
    const aboutModal = document.getElementById("aboutModal");

    const closeBtns = document.querySelectorAll(".modal .close-btn");

    helpBtn.addEventListener("click", () => helpModal.style.display = "block");
    aboutBtn.addEventListener("click", () => aboutModal.style.display = "block");

    closeBtns.forEach(btn => {
        btn.addEventListener("click", () => btn.parentElement.parentElement.style.display = "none");
    });

    window.addEventListener("click", e => {
        if (e.target === helpModal) helpModal.style.display = "none";
        if (e.target === aboutModal) aboutModal.style.display = "none";
    });

    const helpForm = document.getElementById("helpForm");
    const helpAlert = document.getElementById("helpAlert");

    helpForm.addEventListener("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch("modules/reports/help_request.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                helpAlert.innerHTML = `<div class="alert success">Your request was submitted successfully!</div>`;
                helpForm.reset();
                setTimeout(() => helpModal.style.display = "none", 2000);
            } else {
                helpAlert.innerHTML = `<div class="alert error">${data.message}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            helpAlert.innerHTML = `<div class="alert error">Network error. Try again later.</div>`;
        });
    });
});
</script>
</body>
</html>