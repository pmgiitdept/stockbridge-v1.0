<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'admin', 'president', 'operations_manager']);

header('Content-Type: application/json');

$clientId = intval($_GET['client_id'] ?? 0);

if (!$clientId) {
    echo json_encode([]);
    exit;
}

// ✅ Fetch contracts exactly like listed_contracts.php logic
$stmt = $conn->prepare("
    SELECT 
        id,
        user_id,
        unit_no,
        particulars,
        quantity,
        unit,
        cost_per_unit,
        category,
        frequency,
        billing_type
    FROM contracts
    WHERE user_id = ?
    ORDER BY category ASC, created_at ASC
");

$stmt->bind_param("i", $clientId);
$stmt->execute();

$result = $stmt->get_result();
$contracts = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

// ✅ Return JSON
echo json_encode($contracts);