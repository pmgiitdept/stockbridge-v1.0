<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php"; 

authorize(['operations_officer', 'admin', 'president', 'operations_manager']);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown error occurred.'];

if (empty($_POST['user_id'])) {
    $response['message'] = "Client is required.";
    echo json_encode($response);
    exit;
}

$requiredArrays = ['unit_no','particulars','quantity','unit','cost_per_unit','frequency','category','billing_type'];

foreach ($requiredArrays as $field) {
    if (!isset($_POST[$field]) || !is_array($_POST[$field]) || count($_POST[$field]) === 0) {
        $response['message'] = "Invalid or missing $field data.";
        echo json_encode($response);
        exit;
    }
}

$field_selected = trim($_POST['field'] ?? '');
$allowed_fields = ['Housekeeping', 'Grounds & Landscape'];
if (!in_array($field_selected, $allowed_fields)) {
    $response['message'] = "Invalid field selected.";
    echo json_encode($response);
    exit;
}

$user_id = intval($_POST['user_id']);

$frequency_map = [
    "Monthly" => 1,
    "Every 2 months" => 0.5,
    "Every 4 months" => 1/4,
    "Quarterly" => 0.25,
    "Semi-Annually" => 0.1667,
    "Annually" => 1/12,
    "Every 1.5 years" => 1/18,
    "Every 2 years" => 1/24,
    "Every 3 years" => 1/36,
    "Every 4 years" => 1/48
];

$allowed_categories = ['Supply', 'Tool'];
$allowed_billing_types = ['none','free_of_charge','bill_to_actual'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO contracts 
        (user_id, unit_no, particulars, quantity, unit, cost_per_unit, frequency, category, field, site, cost_per_month, total_cost, billing_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if(!$stmt){
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $rowCount = count($_POST['unit_no']);
    $totalInserted = 0;

    for ($i = 0; $i < $rowCount; $i++) {

        $unit_no = trim($_POST['unit_no'][$i]);
        $particulars = trim($_POST['particulars'][$i]);
        $quantity = floatval($_POST['quantity'][$i]);
        $unit = trim($_POST['unit'][$i]);
        $cost_per_unit = floatval($_POST['cost_per_unit'][$i]);
        $frequency = trim($_POST['frequency'][$i]);
        $category = trim($_POST['category'][$i]);
        $billing_type = trim($_POST['billing_type'][$i]);

        $rawSites = isset($_POST['site'][$i]) ? $_POST['site'][$i] : '';
        $siteList = array_filter(array_map('trim', explode(',', $rawSites)));

        if (empty($siteList)) {
            $siteList = [null];
        }

        if (
            $unit_no === '' ||
            $particulars === '' ||
            $quantity <= 0 ||
            $unit === '' ||
            !in_array($category, $allowed_categories) ||
            !isset($frequency_map[$frequency]) ||
            !in_array($billing_type, $allowed_billing_types)
        ) {
            throw new Exception("Invalid data detected in one of the rows.");
        }

        if ($billing_type === 'free_of_charge' || $billing_type === 'bill_to_actual') {
            $cost_per_unit = 0;
            $total_cost = 0;
            $cost_per_month = 0;
        } else {
            $total_cost = $quantity * $cost_per_unit;
            $freq_multiplier = $frequency_map[$frequency];
            $cost_per_month = $total_cost * $freq_multiplier;
        }

        foreach ($siteList as $site) {

            $stmt->bind_param(
                "issisdssssdds",
                $user_id,
                $unit_no,
                $particulars,
                $quantity,
                $unit,
                $cost_per_unit,
                $frequency,
                $category,
                $field_selected,
                $site,
                $cost_per_month,
                $total_cost,
                $billing_type
            );

            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }

            $totalInserted++; 
        }
    }

    $stmt->close();
    $conn->commit();

    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Added $totalInserted contract(s) across $rowCount row(s) (multi-site enabled) for client ID $user_id",
        "Contracts"
    );

    $response['success'] = true;
    $response['message'] = "Contracts added successfully.";

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>