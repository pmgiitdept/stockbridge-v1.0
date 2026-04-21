<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['president']);

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$pr_id = $data['pr_id'] ?? null;
$status = $data['status'] ?? null;

$allowedStatuses = ['approved', 'verified', 'rejected'];

if(!$pr_id || !in_array($status, $allowedStatuses)){
    echo json_encode(['success'=>false,'message'=>'Invalid request']);
    exit;
}

$userId = $_SESSION['user_id'];

$conn->begin_transaction();

try {

    $check = $conn->prepare("
        SELECT pr_id, requesting_department, project, status 
        FROM pr_forms 
        WHERE pr_id=?
    ");
    $check->bind_param("s", $pr_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    $check->close();

    if(!$result || $result['status'] !== 'reviewed'){
        throw new Exception('PR not eligible for approval');
    }

    $stmt = $conn->prepare("
        UPDATE pr_forms 
        SET status=?, approved_by=?, approved_at=NOW()
        WHERE pr_id=?
    ");
    $stmt->bind_param("sis", $status, $userId, $pr_id);
    $stmt->execute();
    $stmt->close();

    $project = $result['project'] ?: 'N/A';

    logAudit(
        $conn,
        $userId,
        "PR {$pr_id} updated to status: {$status} | Department: {$result['requesting_department']} | Project: {$project}",
        "PR Approval (President)"
    );

    $conn->commit();

    echo json_encode(['success'=>true]);

} catch(Exception $e){

    $conn->rollback();

    echo json_encode([
        'success'=>false,
        'message'=>$e->getMessage()
    ]);
}