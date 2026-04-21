<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'operations_manager', 'purchasing_officer']);
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['period'])) {
    echo json_encode(["success"=>false, "message"=>"Period is required."]);
    exit;
}

$period = date('Y-m', strtotime($data['period']));

try {
    $stmt = $conn->prepare("
        SELECT smrf_id, project, status, period
        FROM smrf_forms
        WHERE DATE_FORMAT(period,'%Y-%m') = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $period);
    $stmt->execute();
    $result = $stmt->get_result();
    $smrfs = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["success"=>true, "smrfs"=>$smrfs]);

} catch(Exception $e) {
    echo json_encode(["success"=>false, "message"=>"Failed to fetch SMRF list."]);
}