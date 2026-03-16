<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);
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

$stmt = $conn->prepare("SELECT * FROM pr_forms WHERE pr_id = ? LIMIT 1");
$stmt->bind_param('s', $prId);
$stmt->execute();
$prResult = $stmt->get_result();

if ($prResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'PR not found.']);
    exit;
}

$pr = $prResult->fetch_assoc();

$stmt = $conn->prepare("
    SELECT quantity, unit, item_description, remarks, justification, source_smrf_id, source_reference_id
    FROM pr_items
    WHERE pr_id = ?
");
$stmt->bind_param('s', $prId);
$stmt->execute();
$itemsResult = $stmt->get_result();
$items = $itemsResult->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'pr' => $pr,
    'items' => $items
]);
exit;