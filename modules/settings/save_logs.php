<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'].'/contract_system/config/database.php';
header('Content-Type: application/json');

if(!in_array($_SESSION['role'], ['admin','president'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$audit_retention = (int)($_POST['audit_retention'] ?? 90);
$detailed_logging = $_POST['detailed_logging'] ?? 'disabled';

if($audit_retention < 7 || $audit_retention > 365) {
    echo json_encode(['success'=>false,'message'=>'Audit retention must be between 7 and 365 days']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

foreach(['audit_retention'=>$audit_retention,'detailed_logging'=>$detailed_logging] as $key=>$value){
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

echo json_encode(['success'=>true]);
