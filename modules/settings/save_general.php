<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'].'/contract_system/config/database.php';
header('Content-Type: application/json');

// Only admin/president
if(!in_array($_SESSION['role'], ['admin','president'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$system_name = $_POST['system_name'] ?? '';
$timezone    = $_POST['timezone'] ?? '';
$default_role = $_POST['default_role'] ?? 'admin';

$errors = [];
if(empty($system_name)) $errors[] = "System name is required.";
if(empty($timezone)) $errors[] = "Timezone is required.";
if(empty($default_role)) $errors[] = "Default role is required.";

if($errors) {
    echo json_encode(['success'=>false,'message'=>implode(', ', $errors)]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

foreach(['system_name'=>$system_name,'timezone'=>$timezone,'default_role'=>$default_role] as $key=>$value){
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

echo json_encode(['success'=>true]);
