<?php
require_once "../../config/database.php";

$project = $_POST['project'] ?? '';
$field   = $_POST['field'] ?? '';

$status = isset($_POST['status']) && $_POST['status'] !== '' 
    ? intval($_POST['status']) 
    : null;

$remark = isset($_POST['remark']) && $_POST['remark'] !== '' 
    ? intval($_POST['remark']) 
    : null;

$query = "
INSERT INTO supplies_monitoring_notes (project, field, status_id, remark_id)
VALUES (?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
status_id = VALUES(status_id),
remark_id = VALUES(remark_id)
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssii", $project, $field, $status, $remark);

$success = $stmt->execute();

echo json_encode([
    'success' => $success,
    'error'   => $stmt->error
]);