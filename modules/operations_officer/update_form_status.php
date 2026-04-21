<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['operations_officer', 'purchasing_officer']);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if(empty($data['reference_id']) || empty($data['status'])){
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$referenceId = $data['reference_id'];
$newStatus = strtolower($data['status']);
$allowedStatuses = ['pending', 'verified', 'rejected'];

if(!in_array($newStatus, $allowedStatuses)){
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

$rejectionReason = isset($data['rejection_reason']) ? trim($data['rejection_reason']) : null;
$userId = $_SESSION['user_id'];
$approvedBy = $newStatus === 'verified' ? $userId : null;

$conn->begin_transaction();

try {
    $infoStmt = $conn->prepare("
        SELECT item_code, item_description, unit, quantity 
        FROM client_forms 
        WHERE reference_id = ?
        LIMIT 1
    ");
    $infoStmt->bind_param("s", $referenceId);
    $infoStmt->execute();
    $result = $infoStmt->get_result();
    $row = $result->fetch_assoc();
    $infoStmt->close();

    $stmt = $conn->prepare("
        UPDATE client_forms 
        SET status = ?, approved_by = ?, rejection_reason = ?
        WHERE reference_id = ?
    ");
    $stmt->bind_param(
        "siss",
        $newStatus,
        $approvedBy,
        $rejectionReason,
        $referenceId
    );
    $stmt->execute();
    $stmt->close();

    if($row){
        $reasonLog = $rejectionReason ? " | Rejection Reason: $rejectionReason" : "";
        logAudit(
            $conn,
            $userId,
            "Updated form $referenceId to status: $newStatus$reasonLog | Item Code {$row['item_code']} / {$row['item_description']} / Unit: {$row['unit']} / Quantity: {$row['quantity']}",
            "Client Form Status Update"
        );
    } else {
        $reasonLog = $rejectionReason ? " | Rejection Reason: $rejectionReason" : "";
        logAudit(
            $conn,
            $userId,
            "Updated form $referenceId to status: $newStatus$reasonLog",
            "Client Form Status Update"
        );
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: '.$e->getMessage()]);
}
?>