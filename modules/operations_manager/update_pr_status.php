<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['operations_manager']);

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['pr_id']) || !isset($data['status'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request data."
    ]);
    exit;
}

$pr_id = trim($data['pr_id']);
$status = strtolower(trim($data['status']));
$userId = $_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? '';

// ✅ Allowed statuses
$allowedStatuses = ['pending', 'reviewed', 'approved', 'rejected', 'in_progress', 'completed'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid status value."
    ]);
    exit;
}

// ✅ Fetch previous status + project info
$infoStmt = $conn->prepare("
    SELECT status, project, requested_by, reviewed_by, approved_by
    FROM pr_forms
    WHERE pr_id = ?
    LIMIT 1
");
$infoStmt->bind_param("s", $pr_id);
$infoStmt->execute();
$row = $infoStmt->get_result()->fetch_assoc();
$infoStmt->close();

$oldStatus = $row['status'] ?? 'unknown';

$reviewedBy = $row['reviewed_by'];
$approvedBy = $row['approved_by'];

if ($status === 'pending') {
    $reviewedBy = '';
    $approvedBy = '';
} elseif ($status === 'reviewed') {
    $reviewedBy = $userName;  
} elseif ($status === 'approved') {
    $approvedBy = $userName;
}

$stmt = $conn->prepare("
    UPDATE pr_forms
    SET status = ?, updated_at = NOW(), reviewed_by = ?, approved_by = ?
    WHERE pr_id = ?
");
$stmt->bind_param("ssss", $status, $reviewedBy, $approvedBy, $pr_id);

if ($stmt->execute()) {
    $oldStatusLabel = ucfirst($oldStatus);
    $newStatusLabel = ucfirst($status);

    $message = "Updated PR $pr_id from [$oldStatusLabel] to [$newStatusLabel] | Project: {$row['project']} | Requested By: {$row['requested_by']}";
    logAudit($conn, $userId, $message, "PR Status Update");

    echo json_encode([
        "success" => true,
        "message" => "PR status updated successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Update failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();