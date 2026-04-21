<?php

require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['operations_manager', 'purchasing_officer']);

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$smrf_id = $data['smrf_id'] ?? null;
$status = strtolower($data['status'] ?? '');

if(!$smrf_id || !$status){
    echo json_encode(["success"=>false,"message"=>"Invalid data"]);
    exit;
}

$userId = $_SESSION['user_id'];

$infoStmt = $conn->prepare("
    SELECT status, project, created_by
    FROM smrf_forms
    WHERE smrf_id = ?
    LIMIT 1
");

if(!$infoStmt){
    echo json_encode(["success"=>false,"message"=>"Prepare failed: ".$conn->error]);
    exit;
}

$infoStmt->bind_param("s", $smrf_id);
$infoStmt->execute();
$result = $infoStmt->get_result();
$row = $result->fetch_assoc();
$infoStmt->close();

$oldStatus = $row['status'] ?? 'unknown';

$stmt = $conn->prepare("
    UPDATE smrf_forms
    SET status = ?
    WHERE smrf_id = ?
");

if(!$stmt){
    echo json_encode(["success"=>false,"message"=>"Prepare failed: ".$conn->error]);
    exit;
}

$stmt->bind_param("ss",$status,$smrf_id);

if($stmt->execute()){

    $oldStatusLabel = ucfirst($oldStatus);
    $newStatusLabel = ucfirst($status);

    if($row){
        $message = "Updated SMRF $smrf_id from $oldStatusLabel to $newStatusLabel | Project: {$row['project']} | Created By: {$row['created_by']}";
    } else {
        $message = "Updated SMRF $smrf_id from $oldStatusLabel to $newStatusLabel";
    }

    logAudit(
        $conn,
        $userId,
        $message,
        "SMRF Status Update"
    );

    echo json_encode(["success"=>true]);

}else{
    echo json_encode(["success"=>false,"message"=>"Update failed"]);
}

$stmt->close();
$conn->close();