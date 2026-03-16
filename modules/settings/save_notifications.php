<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'].'/contract_system/config/database.php';
header('Content-Type: application/json');

if(!in_array($_SESSION['role'], ['admin','president'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$email_notifications = $_POST['email_notifications'] ?? 'disabled';
$notification_email  = $_POST['notification_email'] ?? '';

if(!filter_var($notification_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'message'=>'Invalid email address']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

foreach(['email_notifications'=>$email_notifications,'notification_email'=>$notification_email] as $key=>$value){
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

echo json_encode(['success'=>true]);
