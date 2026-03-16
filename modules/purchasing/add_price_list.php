<?php
header('Content-Type: application/json');

require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['purchasing_officer']);

$response = ['success'=>false,'message'=>'Unknown error'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$item_code = $_POST['item_code'] ?? '';
$item_description = $_POST['item_description'] ?? '';
$unit = $_POST['unit'] ?? '';
$unit_price = $_POST['unit_price'] ?? '';
$pmgi_unit_price = $_POST['pmgi_unit_price'] ?? '';

if (!$item_code || !$item_description || !$unit || $unit_price === '' || $pmgi_unit_price === '') {
    $response['message'] = 'All fields are required.';
    echo json_encode($response);
    exit;
}

$unit_price = floatval(str_replace([',','₱',' '], '', $unit_price));
$pmgi_unit_price = floatval(str_replace([',','₱',' '], '', $pmgi_unit_price));

$reference_id = 'REF'.strtoupper(bin2hex(random_bytes(3)));

$stmt = $conn->prepare("INSERT INTO price_lists 
    (reference_id, item_code, item_description, unit, unit_price, pmgi_unit_price, uploaded_at) 
    VALUES (?, ?, ?, ?, ?, ?, NOW())");

if (!$stmt) {
    $response['message'] = 'Prepare failed: '.$conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("ssssdd", $reference_id, $item_code, $item_description, $unit, $unit_price, $pmgi_unit_price);

if (!$stmt->execute()) {
    $response['message'] = 'Insert failed: '.$stmt->error;
    echo json_encode($response);
    exit;
}

$stmt->close();

$audit_log_id = logAudit(
    $conn,
    $_SESSION['user_id'],
    "Added price list item: $item_code / $item_description / Unit Price: $unit_price / PMGI Price: $pmgi_unit_price",
    "Price List Management"
);

if (!$audit_log_id) {
    $audit_log_id = $conn->insert_id;
}

$old_value = null;

$fields = [
    'reference_id' => $reference_id,
    'item_code' => $item_code,
    'item_description' => $item_description,
    'unit' => $unit,
    'unit_price' => $unit_price,
    'pmgi_unit_price' => $pmgi_unit_price
];

$detail_stmt = $conn->prepare("
    INSERT INTO price_list_audit_details
    (audit_log_id, reference_id, item_code, field_name, old_value, new_value)
    VALUES (?, ?, ?, ?, ?, ?)
");

if ($detail_stmt) {
    foreach ($fields as $field => $value) {
        $value_str = (string)$value;
        $detail_stmt->bind_param("isssss",
            $audit_log_id,
            $reference_id,
            $item_code,
            $field,
            $old_value,
            $value_str
        );
        $detail_stmt->execute();
    }
    $detail_stmt->close();
}

$response['success'] = true;
$response['message'] = 'Price list item added successfully.';
echo json_encode($response);
