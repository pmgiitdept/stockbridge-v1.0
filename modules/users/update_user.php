<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['admin']);

header('Content-Type: application/json');

$user_id = $_POST['user_id'] ?? null;
$full_name = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$role = $_POST['role'] ?? '';
$contact = $_POST['contact'] ?? '';
$address = $_POST['address'] ?? '';
$branch = $_POST['branch'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_new_password'] ?? '';

$upload_dir = "../../uploads/"; 
$profile_path = '';
$signature_path = '';

if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['tmp_name']){
    $profile_name = time() . "_" . basename($_FILES['profile_picture']['name']);
    $target_file = $upload_dir . $profile_name;
    if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)){
        $profile_path = "/contract_system/uploads/" . $profile_name; 
    }
}

if(isset($_FILES['signature']) && $_FILES['signature']['tmp_name']){
    $signature_name = time() . "_" . basename($_FILES['signature']['name']);
    $target_file = $upload_dir . $signature_name;
    if(move_uploaded_file($_FILES['signature']['tmp_name'], $target_file)){
        $signature_path = "/contract_system/uploads/" . $signature_name;
    }
}

if(!$user_id){
    echo json_encode(['success'=>false, 'message'=>'User ID is required']);
    exit;
}

// --- VALIDATE PASSWORD ---
if(!empty($new_password)){
    if($new_password !== $confirm_password){
        echo json_encode(['success'=>false, 'message'=>'Passwords do not match']);
        exit;
    }
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_password_sql = ", password='".mysqli_real_escape_string($conn, $hashed_password)."'";
} else {
    $update_password_sql = "";
}

// --- UPDATE USERS TABLE ---
$update_user = mysqli_query($conn, "
    UPDATE users SET 
        full_name='".mysqli_real_escape_string($conn,$full_name)."',
        email='".mysqli_real_escape_string($conn,$email)."',
        role='".mysqli_real_escape_string($conn,$role)."' 
        $update_password_sql
    WHERE id='".intval($user_id)."'
");

// --- UPDATE OR INSERT PROFILE ---
$update_profile = mysqli_query($conn, "
    INSERT INTO user_profiles (user_id, contact, address, branch, profile_picture, signature)
    VALUES ('".intval($user_id)."', '".mysqli_real_escape_string($conn,$contact)."',
            '".mysqli_real_escape_string($conn,$address)."',
            '".mysqli_real_escape_string($conn,$branch)."',
            '".mysqli_real_escape_string($conn, $profile_path)."',
            '".mysqli_real_escape_string($conn, $signature_path)."')
    ON DUPLICATE KEY UPDATE
        contact=VALUES(contact),
        address=VALUES(address),
        branch=VALUES(branch),
        profile_picture=IF(VALUES(profile_picture)<>'', VALUES(profile_picture), profile_picture),
        signature=IF(VALUES(signature)<>'', VALUES(signature), signature)
");

if($update_user && $update_profile){

    $log_email = mysqli_real_escape_string($conn, $email);

    logAudit(
        $conn,
        $_SESSION['user_id'], 
        "Updated user: $log_email (ID: $user_id)",
        "User Management"
    );

    echo json_encode(['success'=>true, 'message'=>'User updated successfully']);
} else {
    echo json_encode(['success'=>false, 'message'=>mysqli_error($conn)]);
}
exit;