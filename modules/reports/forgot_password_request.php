<?php
require_once "../../config/database.php";

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');
$description = trim($_POST['description'] ?? '');

if(empty($email) || empty($description)){
    echo json_encode(['success'=>false, 'message'=>'All fields are required.']);
    exit;
}

$userQuery = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' LIMIT 1");
if(!$userQuery || mysqli_num_rows($userQuery) !== 1){
    echo json_encode(['success'=>false, 'message'=>'Email not found.']);
    exit;
}

$user = mysqli_fetch_assoc($userQuery);

$insert = mysqli_query($conn, "
    INSERT INTO user_reports (user_id, type, description, status)
    VALUES ({$user['id']}, 'password_change', '".mysqli_real_escape_string($conn, $description)."', 'pending')
");

if($insert){
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Failed to submit request.']);
}
