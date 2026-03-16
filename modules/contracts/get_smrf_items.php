<?php
require_once "../../config/database.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'admin', 'president', 'operations_manager']);

header('Content-Type: application/json');

$smrfId = $_GET['smrf_id'] ?? '';

if (!$smrfId) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        si.item_code,
        si.item_description,
        si.quantity,
        si.unit,
        si.unit_cost,
        si.amount,
        si.legend,
        CASE 
            WHEN c.id IS NOT NULL THEN 1 
            ELSE 0 
        END AS is_in_contract,
        COALESCE(c.quantity, 0) AS contract_quantity
    FROM smrf_items si
    LEFT JOIN contracts c 
        ON UPPER(TRIM(c.unit_no)) = UPPER(TRIM(si.item_code))
    WHERE si.smrf_id = ?
    ORDER BY si.id ASC
");

if (!$stmt) {
    echo json_encode([
        "error" => "Prepare failed",
        "details" => $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $smrfId); 
$stmt->execute();
$result = $stmt->get_result();

$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = [
        'item_code' => $row['item_code'] ?? '',
        'item_description' => $row['item_description'] ?? '',
        'quantity' => (float) ($row['quantity'] ?? 0),
        'unit' => $row['unit'] ?? '',
        'unit_cost' => (float) ($row['unit_cost'] ?? 0),
        'amount' => (float) ($row['amount'] ?? 0),
        'legend' => strtolower($row['legend'] ?? ''), 
        'is_in_contract' => (int) $row['is_in_contract'],
        'contract_quantity' => (float) ($row['contract_quantity'] ?? 0)
    ];
}

$stmt->close();

echo json_encode($items);
exit;