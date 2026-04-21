<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php"; 

authorize(['operations_officer', 'admin', 'president', 'operations_manager']);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown error occurred.'];

if (
    !isset($_POST['contract_id']) ||
    !isset($_POST['user_id']) ||
    !isset($_POST['unit_no']) ||
    !isset($_POST['particulars']) ||
    !isset($_POST['quantity']) ||
    !isset($_POST['unit']) ||
    !isset($_POST['cost_per_unit']) ||
    !isset($_POST['frequency']) ||
    !isset($_POST['category']) ||
    !isset($_POST['billing_type'])  
) {
    $response['message'] = "All fields are required.";
    echo json_encode($response);
    exit;
}

$contract_id   = intval($_POST['contract_id']);
$user_id       = intval($_POST['user_id']);
$unit_no       = trim($_POST['unit_no']);
$particulars   = trim($_POST['particulars']);
$quantity      = intval($_POST['quantity'] ?? 0);
$unit          = trim($_POST['unit']); 
$cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);
$frequency     = trim($_POST['frequency']);
$category      = trim($_POST['category']);
$billing_type  = trim($_POST['billing_type']);
$site          = isset($_POST['site']) && trim($_POST['site']) !== '' ? trim($_POST['site']) : null;

$allowed_billing = ['none', 'free_of_charge', 'bill_to_actual'];

$frequency_map = [
    "Monthly" => 1,
    "Every 2 months" => 0.5,
    "Quarterly" => 0.25,
    "Every 4 months" => 1/4,
    "Semi-Annually" => 0.1667,
    "Annually" => 1/12,
    "Every 1.5 years" => 1/18,
    "Every 2 years" => 1/24,
    "Every 3 years" => 1/36,
    "Every 4 years" => 1/48
];

if (
    $contract_id <= 0 ||
    $user_id <= 0 ||
    $unit_no === '' ||
    $particulars === '' ||
    $unit === '' ||
    !isset($frequency_map[$frequency]) ||
    ($category !== 'Supply' && $category !== 'Tool') ||
    !in_array($billing_type, $allowed_billing)
) {
    $response['message'] = "Invalid data provided.";
    echo json_encode($response);
    exit;
}

if ($billing_type === 'none') {
    if ($quantity <= 0 || $cost_per_unit <= 0) {
        $response['message'] = "Quantity and Cost per Unit must be greater than 0.";
        echo json_encode($response);
        exit;
    }
}

$conn->begin_transaction();

try {

    $old_stmt = $conn->prepare("SELECT * FROM contracts WHERE id = ?");
    if (!$old_stmt) throw new Exception("Prepare failed (fetch old): " . $conn->error);

    $old_stmt->bind_param("i", $contract_id);
    $old_stmt->execute();
    $old_result = $old_stmt->get_result();

    if ($old_result->num_rows === 0) throw new Exception("Contract not found.");

    $old_data = $old_result->fetch_assoc();
    $old_stmt->close();

    if ($billing_type === 'free_of_charge' || $billing_type === 'bill_to_actual') {
        $total_cost = 0;
        $cost_per_month = 0;
    } else {
        $freq_multiplier = $frequency_map[$frequency];
        $total_cost = $quantity * $cost_per_unit;
        $cost_per_month = $total_cost * $freq_multiplier;
    }

    $stmt = $conn->prepare("
        UPDATE contracts 
        SET 
            user_id = ?, 
            unit_no = ?, 
            particulars = ?, 
            quantity = ?, 
            unit = ?, 
            cost_per_unit = ?, 
            frequency = ?, 
            category = ?, 
            billing_type = ?,
            site = ?,
            cost_per_month = ?, 
            total_cost = ?
        WHERE id = ?
    ");
    if (!$stmt) throw new Exception("Prepare failed (update): " . $conn->error);

    $stmt->bind_param(
        "issisdssssddi",
        $user_id,
        $unit_no,
        $particulars,
        $quantity,
        $unit,
        $cost_per_unit,
        $frequency,
        $category,
        $billing_type,
        $site,         
        $cost_per_month,
        $total_cost,
        $contract_id
    );

    if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);

    $changes = [];

    if ($old_data['user_id'] != $user_id) $changes[] = "Client ID: {$old_data['user_id']} → {$user_id}";
    if ($old_data['unit_no'] !== $unit_no) $changes[] = "Unit No: {$old_data['unit_no']} → {$unit_no}";
    if ($old_data['particulars'] !== $particulars) $changes[] = "Particulars updated";
    if ($old_data['quantity'] != $quantity) $changes[] = "Quantity: {$old_data['quantity']} → {$quantity}";
    if ($old_data['unit'] !== $unit) $changes[] = "Unit: {$old_data['unit']} → {$unit}";
    if ($old_data['cost_per_unit'] != $cost_per_unit) $changes[] = "Cost/Unit: {$old_data['cost_per_unit']} → {$cost_per_unit}";
    if ($old_data['frequency'] !== $frequency) $changes[] = "Frequency: {$old_data['frequency']} → {$frequency}";
    if ($old_data['category'] !== $category) $changes[] = "Category: {$old_data['category']} → {$category}";
    if (($old_data['billing_type'] ?? 'none') !== $billing_type) $changes[] = "Billing Type: {$old_data['billing_type']} → {$billing_type}";
    if (($old_data['site'] ?? null) !== $site) $changes[] = "Site updated";

    if (empty($changes)) {
        $stmt->close();
        $conn->commit();

        $response['success'] = true;
        $response['message'] = "No changes detected. Contract remains the same.";
        echo json_encode($response);
        exit;
    }

    $stmt->close();

    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Updated Contract ID {$contract_id}: " . implode(", ", $changes),
        "Contracts"
    );

    $conn->commit();

    $response['success'] = true;
    $response['message'] = "Contract updated successfully.";

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>