<?php

function logAudit($conn, $user_id, $action, $module) {

    if (!$conn) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'];

    $sql = "INSERT INTO audit_logs 
            (user_id, action, module, ip_address) 
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt === false) {
        die("Audit Prepare Failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "isss", $user_id, $action, $module, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
?>
