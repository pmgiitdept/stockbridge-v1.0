<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

// Only allow admins or president
authorize(['admin','president']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid request method']);
    exit;
}

$report_id   = $_POST['report_id'] ?? 0;
$action_taken = trim($_POST['action_taken'] ?? '');
$status       = $_POST['status'] ?? '';

if (!$report_id || !$action_taken || !$status) {
    echo json_encode(['success'=>false,'message'=>'All fields are required']);
    exit;
}

// Fetch report info
$stmt = $conn->prepare("SELECT user_id, type FROM user_reports WHERE id=?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    echo json_encode(['success'=>false,'message'=>'Report not found']);
    exit;
}

// Optional: perform actual actions (like password reset)
if (stripos($action_taken, 'password reset') !== false && $report['type'] === 'password_change') {
    $user_id = $report['user_id'];
    $temp_pass = bin2hex(random_bytes(4)); // temporary password
    $hash = password_hash($temp_pass, PASSWORD_DEFAULT);

    $conn->query("UPDATE users SET password='$hash' WHERE id=$user_id");
    $action_taken .= " (Temporary password: $temp_pass)";
}

// Update report
$stmt_update = $conn->prepare("UPDATE user_reports SET action_taken=?, status=?, updated_at=NOW() WHERE id=?");
$stmt_update->bind_param("ssi", $action_taken, $status, $report_id);

if ($stmt_update->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>$stmt_update->error]);
}
