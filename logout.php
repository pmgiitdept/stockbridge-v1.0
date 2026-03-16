<?php
session_start();
require_once "config/database.php";
require_once "core/audit.php";

if (isset($_SESSION['user_id'])) {

    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

$_SESSION = [];
session_unset();
session_destroy();

if (isset($_COOKIE['remember_token'])) {
    setcookie("remember_token", "", time() - 3600, "/");
}

header("Location: /contract_system/login.php");
exit();