<?php
header('Content-Type: application/json');

require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['purchasing_officer']);

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid request method.']);
    exit;
}

$id = $_POST['id'] ?? '';
if(!$id){
    echo json_encode(['success'=>false,'message'=>'ID is required.']);
    exit;
}

// Fetch the record first
$fetch_stmt = $conn->prepare("SELECT * FROM price_lists WHERE id=?");
$fetch_stmt->bind_param("i", $id);
$fetch_stmt->execute();
$result = $fetch_stmt->get_result();
$price = $result->fetch_assoc();
$fetch_stmt->close();

if(!$price){
    echo json_encode(['success'=>false,'message'=>'Price list not found.']);
    exit;
}

$item_code = $price['item_code'];
$item_description = $price['item_description'];
$reference_id = $price['reference_id'];
$unit = $price['unit'];
$unit_price = $price['unit_price'];
$pmgi_unit_price = $price['pmgi_unit_price'];

// Delete the record
$stmt = $conn->prepare("DELETE FROM price_lists WHERE id=?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Original audit log
    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Deleted price list ID $id: $item_code / $item_description",
        "Price List Management"
    );

    // Get last audit log ID
    $audit_log_id = $conn->insert_id; // <-- assumes logAudit uses same connection

    // Insert details into price_list_audit_details
    $fields = [
        'reference_id' => $reference_id,
        'item_code' => $item_code,
        'item_description' => $item_description,
        'unit' => $unit,
        'unit_price' => $unit_price,
        'pmgi_unit_price' => $pmgi_unit_price
    ];

    foreach($fields as $field => $value){
        $detail_stmt = $conn->prepare("
            INSERT INTO price_list_audit_details
            (audit_log_id, reference_id, item_code, field_name, old_value, new_value)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $null = null; 
        $detail_stmt->bind_param("isssss",
            $audit_log_id,
            $reference_id,
            $item_code,
            $field,
            $value,  
            $null    
        );
        $detail_stmt->execute();
        $detail_stmt->close();
    }

    echo json_encode(['success'=>true,'message'=>'Price list deleted successfully.']);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to delete: '.$stmt->error]);
}
