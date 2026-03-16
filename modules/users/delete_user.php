<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['admin']);
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$user_id = intval($input['user_id'] ?? 0);

if(!$user_id){
    echo json_encode(['success'=>false, 'message'=>'User ID is required']);
    exit;
}

// Get user info before deleting
$getUser = mysqli_query($conn, "SELECT email FROM users WHERE id = $user_id");
$userData = mysqli_fetch_assoc($getUser);
$deletedEmail = $userData['email'] ?? 'Unknown';

// Delete profile first
mysqli_query($conn, "DELETE FROM user_profiles WHERE user_id = $user_id");

if(mysqli_query($conn, "DELETE FROM users WHERE id = $user_id")){

    logAudit(
        $conn,
        $_SESSION['user_id'], // Admin performing delete
        "Deleted user: $deletedEmail (ID: $user_id)",
        "User Management"
    );

    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'message'=>mysqli_error($conn)]);
}
exit;
?>
