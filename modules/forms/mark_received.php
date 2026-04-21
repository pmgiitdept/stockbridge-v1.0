<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php"; // for logAudit()

authorize(['purchasing_officer']);
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$prId = $data['pr_id'] ?? '';

if (!$prId) {
    echo json_encode(['success' => false, 'message' => 'PR ID is required.']);
    exit;
}

$purchasingOfficerId = $_SESSION['user_id'] ?? null;
if (!$purchasingOfficerId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Check PR exists
    $checkStmt = $conn->prepare("SELECT pr_id, requesting_department, project, received_by FROM pr_forms WHERE pr_id = ?");
    $checkStmt->bind_param('s', $prId);
    $checkStmt->execute();
    $pr = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$pr) {
        throw new Exception('PR not found.');
    }

    if (!empty($pr['received_by'])) {
        throw new Exception('PR already marked as received.');
    }

    // Update received_by
    $updateStmt = $conn->prepare("UPDATE pr_forms SET received_by = ? WHERE pr_id = ?");
    $updateStmt->bind_param('is', $purchasingOfficerId, $prId);
    $updateStmt->execute();
    $updateStmt->close();

    // Get full name for display
    $stmt2 = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt2->bind_param('i', $purchasingOfficerId);
    $stmt2->execute();
    $fullName = $stmt2->get_result()->fetch_assoc()['full_name'] ?? '';
    $stmt2->close();

    // Log audit using same style as PR Approval
    $project = $pr['project'] ?: 'N/A';
    logAudit(
        $conn,
        $purchasingOfficerId,
        "PR {$prId} marked as received | Department: {$pr['requesting_department']} | Project: {$project}",
        "PR Received"
    );

    $conn->commit();

    echo json_encode(['success' => true, 'full_name' => $fullName]);

} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}