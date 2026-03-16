<?php
session_start();
require_once "../../config/database.php";

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
$issue = trim($_POST['issue'] ?? '');
$email = trim($_POST['email'] ?? ''); // optional if you want to log email

if (!$userId || empty($issue)) {
    echo json_encode(['success' => false, 'message' => 'Issue description is required.']);
    exit;
}

// Insert into user_reports as type 'issue'
$stmt = $conn->prepare("INSERT INTO user_reports (user_id, type, description) VALUES (?, 'issue', ?)");
$stmt->bind_param("is", $userId, $issue);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
