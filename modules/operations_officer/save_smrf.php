<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['operations_officer']);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['smrf_id']) || empty($data['project']) || empty($data['period']) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$smrfId = $data['smrf_id'];
$referenceId = $data['reference_id'] ?? null; 
$project = $data['project'];
$projectCode = $data['project_code'] ?? null;
$period = $data['period'];
$periodDate = $period . '-01';
$items = $data['items'];
$status = 'pending';
$userId = $_SESSION['user_id'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO smrf_forms (smrf_id, reference_id, project, project_code, period, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssssi", $smrfId, $referenceId, $project, $projectCode, $periodDate, $status, $userId);
    $stmt->execute();
    $stmt->close();

    $itemStmt = $conn->prepare("
        INSERT INTO smrf_items
        (smrf_id, item_code, item_description, quantity, unit, remarks, legend, unit_cost, amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $itemStmt->bind_param(
            "sssisssdd",
            $smrfId,
            $item['item_code'],
            $item['item_description'],
            $item['quantity'],
            $item['unit'],
            $item['remarks'],
            $item['legend'],
            $item['unit_cost'],
            $item['amount']
        );
        $itemStmt->execute();
    }
    $itemStmt->close();

    $itemSummary = array_map(function($i) {
        return "{$i['item_code']} / {$i['item_description']} / Unit: {$i['unit']} / Qty: {$i['quantity']} / Amount: {$i['amount']}";
    }, $items);

    logAudit(
        $conn,
        $userId,
        "Created SMRF $smrfId | Reference ID: " . ($referenceId ?? 'Manual') . " | Project: $project | Items: " . implode(" | ", $itemSummary),
        "SMRF Creation"
    );

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'SMRF created successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: '.$e->getMessage()]);
}
?>