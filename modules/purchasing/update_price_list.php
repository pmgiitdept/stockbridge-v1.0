<?php
header('Content-Type: application/json');

require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['purchasing_officer']);

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid request method.']);
    exit;
}

$id = $_POST['id'] ?? '';
$reference_id = $_POST['reference_id'] ?? '';
$item_code = $_POST['item_code'] ?? '';
$item_description = $_POST['item_description'] ?? '';
$unit = $_POST['unit'] ?? '';
$unit_price = $_POST['unit_price'] ?? '';
$margin_percent = $_POST['margin_percent'] ?? '';

if(!$id || !$reference_id || !$item_code || !$item_description || !$unit || $unit_price === '' || $margin_percent === ''){
    echo json_encode(['success'=>false,'message'=>'All fields are required.']);
    exit;
}

/**
 * Sanitize values
 */
$unit_price = floatval(str_replace([',','₱',' '], '', $unit_price));
$margin_percent = floatval($margin_percent);

/**
 * ✅ ALWAYS COMPUTE PMGI (DO NOT TRUST FRONTEND)
 */
$pmgi_unit_price = $unit_price + ($unit_price * ($margin_percent / 100));

/**
 * Get OLD values (including margin)
 */
$old_stmt = $conn->prepare("
    SELECT reference_id, item_code, item_description, unit, unit_price, margin_percent, pmgi_unit_price 
    FROM price_lists 
    WHERE id=?
");
$old_stmt->bind_param("i", $id);
$old_stmt->execute();
$old_result = $old_stmt->get_result();
$old = $old_result->fetch_assoc();
$old_stmt->close();

/**
 * UPDATE query (now includes margin)
 */
$stmt = $conn->prepare("
    UPDATE price_lists 
    SET reference_id=?, item_code=?, item_description=?, unit=?, unit_price=?, margin_percent=?, pmgi_unit_price=? 
    WHERE id=?
");

$stmt->bind_param(
    "ssssdddi",
    $reference_id,
    $item_code,
    $item_description,
    $unit,
    $unit_price,
    $margin_percent,
    $pmgi_unit_price,
    $id
);

if ($stmt->execute()) {

    /**
     * ✅ MAIN AUDIT LOG
     */
    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Updated price list ID $id | $item_code / $item_description | Unit Price: $unit_price | Margin: $margin_percent% | PMGI: $pmgi_unit_price",
        "Price List Management"
    );

    $audit_log_id = $conn->insert_id;

    /**
     * ✅ FIELD-BY-FIELD AUDIT
     */
    $fields = [
        'reference_id' => $reference_id,
        'item_code' => $item_code,
        'item_description' => $item_description,
        'unit' => $unit,
        'unit_price' => $unit_price,
        'margin_percent' => $margin_percent,
        'pmgi_unit_price' => $pmgi_unit_price
    ];

    foreach($fields as $field => $new_value) {
        $old_value = $old[$field] ?? '';

        // Normalize numeric comparison
        if(is_numeric($old_value) && is_numeric($new_value)){
            $old_value = floatval($old_value);
            $new_value = floatval($new_value);
        }

        if($old_value != $new_value) {
            $detail_stmt = $conn->prepare("
                INSERT INTO price_list_audit_details
                (audit_log_id, reference_id, item_code, field_name, old_value, new_value)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $detail_stmt->bind_param(
                "isssss",
                $audit_log_id,
                $reference_id,
                $item_code,
                $field,
                $old_value,
                $new_value
            );

            $detail_stmt->execute();
            $detail_stmt->close();
        }
    }

    echo json_encode(['success'=>true,'message'=>'Price list updated successfully.']);

} else {
    echo json_encode(['success'=>false,'message'=>'Failed to update: '.$stmt->error]);
}