<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'operations_manager', 'president', 'purchasing_officer']);
header('Content-Type: application/json');

set_error_handler(function($errno, $errstr) {
    echo json_encode(['success' => false, 'message' => "PHP Error [$errno]: $errstr"]);
    exit;
});

$data = json_decode(file_get_contents('php://input'), true);
$prId = $data['pr_id'] ?? '';

if (!$prId) {
    echo json_encode(['success' => false, 'message' => 'PR ID is required.']);
    exit;
}

// Fetch PR with full names for all "by" fields
$stmt = $conn->prepare("
    SELECT 
        pf.*,
        u_req.full_name AS requested_by,
        u_app.full_name AS approved_by,
        u_rec.full_name AS received_by
    FROM pr_forms pf
    LEFT JOIN users u_req ON pf.created_by = u_req.id
    LEFT JOIN users u_app ON pf.approved_by = u_app.id
    LEFT JOIN users u_rec ON pf.received_by = u_rec.id
    WHERE pf.pr_id = ? 
    LIMIT 1
");
$stmt->bind_param('s', $prId);
$stmt->execute();
$prResult = $stmt->get_result();

if ($prResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'PR not found.']);
    exit;
}

$pr = $prResult->fetch_assoc();

// Fetch PR items
$stmt = $conn->prepare("
    SELECT quantity, unit, item_description, remarks, justification, source_smrf_id, source_reference_id
    FROM pr_items
    WHERE pr_id = ?
");
$stmt->bind_param('s', $prId);
$stmt->execute();
$itemsResult = $stmt->get_result();
$items = $itemsResult->fetch_all(MYSQLI_ASSOC);

// Return JSON
echo json_encode([
    'success' => true,
    'pr' => $pr,
    'items' => $items
]);
exit;