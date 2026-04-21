<?php
session_start();
require_once "config/database.php";
require_once "core/audit.php";

$error = "";

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {

    $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);

    $query = "SELECT * FROM users WHERE remember_token = '$token' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        if ($user['role'] === 'admin') {
            header("Location: modules/dashboard/index.php");
            exit();
        } elseif ($user['role'] === 'client') {
            header("Location: modules/client/dashboard.php");
            exit();
        } elseif ($user['role'] === 'purchasing_officer') {
            header("Location: modules/purchasing/dashboard.php");
            exit();
        } elseif ($user['role'] === 'operations_officer') {
            header("Location: modules/operations_officer/dashboard.php");
            exit();
        } elseif ($user['role'] === 'operations_manager') {
            header("Location: modules/operations_manager/dashboard.php");
            exit();
        } elseif ($user['role'] === 'president') {
            header("Location: modules/president/dashboard.php");
            exit();
        } else {
            header("Location: modules/client/dashboard.php");
            exit();
        }
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email = '$email' AND status = 'active' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) === 1) {

        $user = mysqli_fetch_assoc($result);

        if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
            $error = "Account locked. Try again later.";
        } else {

            if (password_verify($password, $user['password'])) {

                mysqli_query($conn, "
                    UPDATE users 
                    SET failed_attempts = 0, lock_until = NULL 
                    WHERE id = {$user['id']}
                ");

                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                if (!empty($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));

                    mysqli_query($conn, "
                        UPDATE users 
                        SET remember_token = '$token'
                        WHERE id = {$user['id']}
                    ");

                    setcookie(
                        "remember_token",
                        $token,
                        time() + (86400 * 30), 
                        "/",
                        "",
                        false,
                        true
                    );
                }

                logAudit($conn, $user['id'], "User logged in", "Login");

                if ($user['role'] === 'admin') {
                    header("Location: modules/dashboard/index.php");
                    exit();
                } elseif ($user['role'] === 'client') {
                    header("Location: modules/client/dashboard.php");
                    exit();
                } elseif ($user['role'] === 'purchasing_officer') {
                    header("Location: modules/purchasing/dashboard.php");
                    exit();
                } elseif ($user['role'] === 'operations_manager') {
                    header("Location: modules/operations_manager/dashboard.php");
                    exit();
                } elseif ($user['role'] === 'operations_officer') {
                    header("Location: modules/operations_officer/dashboard.php");
                    exit();
                } elseif ($user['role'] === 'president') {
                    header("Location: modules/president/dashboard.php");
                    exit();

                } else {
                    header("Location: modules/client/dashboard.php");
                    exit();
                }

            } else {

                $attempts = $user['failed_attempts'] + 1;

                if ($attempts >= 5) {
                    $lock_time = date("Y-m-d H:i:s", strtotime("+15 minutes"));

                    mysqli_query($conn, "
                        UPDATE users 
                        SET failed_attempts = $attempts, lock_until = '$lock_time'
                        WHERE id = {$user['id']}
                    ");

                    $error = "Too many failed attempts. Account locked for 15 minutes.";
                } else {

                    mysqli_query($conn, "
                        UPDATE users 
                        SET failed_attempts = $attempts
                        WHERE id = {$user['id']}
                    ");

                    $error = "Invalid credentials. Attempt $attempts of 5.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/images/stockbridge-logo-title.PNG">
    <title>Login | Stockbridge Contract System</title>
    <link rel="stylesheet" href="assets/css/login.css">
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

<div class="login-page">

    <?php if (isset($_GET['registered'])): ?>
        <div class="success-banner">
            ✅ Account created successfully! You may now login.
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="error-banner">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="login-wrapper">
        <div class="login-container">
            <h2>Stockbridge Login</h2>
            <p>Secure Access to Contract Management and Lifecycle System</p>

            <form method="POST" autocomplete="off">

                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>

                    <a href="javascript:void(0);" class="forgot-link" id="forgotLink">
                        Forgot Password?
                    </a>

                </div>

                <button type="submit" class="login-btn">
                    Login
                </button>

                <div class="divider">
                    <span>or</span>
                </div>

                <div class="register-section">
                    <p>
                        Don’t have an account?
                        <a href="register.php">Create Account</a>
                    </p>
                </div>
            </form>
        </div>

        <div class="login-brand">
            <div class="brand-content">
                <img src="assets/images/stockbridge-logo.PNG" alt="Stockbridge Logo" class="brand-logo">

                <h1 class="brand-title">
                    Contract Management and Supply System
                </h1>

                <p class="brand-subtitle">
                    Secure • Reliable • Automate • Approve • Archive
                </p>
            </div>
        </div>
    </div>
</div>

<div id="forgotModal" class="modal">
    <div class="modal-content modal-glass">
        <span class="close-btn">&times;</span>
        <h2 class="modal-title">Request Password Reset</h2>

        <p class="modal-subtitle">
            Submit a request to IT for password assistance. Provide any details that may help.
        </p>

        <form id="forgotForm">
            <div class="form-group">
                <label for="forgot_email">Email Address</label>
                <input type="email" id="forgot_email" name="email" required placeholder="Your registered email">
            </div>

            <div class="form-group">
                <label for="forgot_description">Description / Details</label>
                <textarea id="forgot_description" name="description" rows="4" required placeholder="Describe the issue..."></textarea>
            </div>

            <button type="submit" class="primary-btn">Submit Request</button>
        </form>

        <div id="forgotAlert" style="margin-top:15px;"></div>
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

    const forgotModal = document.getElementById("forgotModal");
    const forgotLink = document.getElementById("forgotLink");
    const closeBtn = forgotModal.querySelector(".close-btn");
    const forgotForm = document.getElementById("forgotForm");
    const forgotAlert = document.getElementById("forgotAlert");

    forgotLink.addEventListener("click", () => forgotModal.style.display = "block");
    closeBtn.addEventListener("click", () => forgotModal.style.display = "none");
    window.addEventListener("click", e => { if(e.target == forgotModal) forgotModal.style.display = "none"; });

    forgotForm.addEventListener("submit", function(e){
        e.preventDefault();

        const formData = new FormData(this);

        fetch("modules/reports/forgot_password_request.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                forgotAlert.innerHTML = `<div class="alert success">Request submitted successfully! IT will contact you shortly.</div>`;
                forgotForm.reset();
                setTimeout(() => forgotModal.style.display = "none", 2000);
            } else {
                forgotAlert.innerHTML = `<div class="alert error">${data.message}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            forgotAlert.innerHTML = `<div class="alert error">Network error. Try again later.</div>`;
        });
    });

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

    const successBanner = document.querySelector(".success-banner");
    if(successBanner){
        setTimeout(() => {
            successBanner.style.opacity = "0";
            setTimeout(() => successBanner.remove(), 400);
        }, 4000);
    }

    const errorBanner = document.querySelector(".error-banner");
    if(errorBanner){
        setTimeout(() => {
            errorBanner.style.opacity = "0";
            setTimeout(() => errorBanner.remove(), 400);
        }, 4000);
    }
});
</script>
</body>
</html>
