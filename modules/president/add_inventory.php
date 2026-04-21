<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['president','operations_manager','operations_officer']);

header('Content-Type: application/json');

$item_name = trim($_POST['item_name'] ?? '');
$unit = trim($_POST['unit'] ?? '');
$quantity = floatval($_POST['quantity'] ?? 0);
$reference_id = trim($_POST['reference_id'] ?? '');
$date = $_POST['movement_date'] ?? date('Y-m-d');

if ($item_name === '' || $unit === '' || $quantity <= 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid input']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO inventory_movements
    (item_name, unit, quantity, movement_type, reference_type, reference_id, movement_date)
    VALUES (?, ?, ?, 'IN', 'INVOICE', ?, ?)
");

$stmt->bind_param("ssdss", $item_name, $unit, $quantity, $reference_id, $date);

if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>$stmt->error]);
}