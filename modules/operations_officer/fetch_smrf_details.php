<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'operations_manager', 'purchasing_officer', 'purchasing_manager', 'president']);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if(empty($data['smrf_id'])) {
    echo json_encode(['success' => false, 'message' => 'SMRF ID missing']);
    exit;
}

$smrfId = $data['smrf_id'];

$stmt = $conn->prepare("
    SELECT sf.smrf_id, sf.reference_id, sf.project, sf.project_code, DATE_FORMAT(sf.period,'%b %Y') as period, sf.status,
           DATE_FORMAT(sf.created_at,'%b %d, %Y %H:%i') as created_at, u.full_name as created_by
    FROM smrf_forms sf
    JOIN users u ON sf.created_by = u.id
    WHERE sf.smrf_id = ?
");
$stmt->bind_param("s", $smrfId);
$stmt->execute();
$smrf = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$smrf){
    echo json_encode(['success' => false, 'message' => 'SMRF not found']);
    exit;
}

$stmtItems = $conn->prepare("SELECT * FROM smrf_items WHERE smrf_id = ?");
$stmtItems->bind_param("s", $smrfId);
$stmtItems->execute();
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItems->close();

echo json_encode(['success' => true, 'smrf' => $smrf, 'items' => $items]);