<?php
require_once "../../config/database.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'admin', 'president', 'operations_manager']);

header('Content-Type: application/json');

$clientId = intval($_GET['client_id'] ?? 0);

if (!$clientId) {
    echo json_encode([]);
    exit;
}

// Fetch all SMRFs for this client based on client_forms.reference_id
$stmt = $conn->prepare("
    SELECT 
        s.smrf_id AS id,
        s.reference_id,
        s.project,
        s.project_code,
        s.status,
        s.period,
        s.created_at,
        u.full_name AS created_by_name
    FROM smrf_forms s
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.reference_id IN (
        SELECT DISTINCT reference_id 
        FROM client_forms 
        WHERE user_id = ?
    )
    ORDER BY s.created_at DESC
");

if (!$stmt) {
    echo json_encode(["error" => "Prepare failed (smrf_forms): " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $clientId);
$stmt->execute();

$result = $stmt->get_result();
$smrfs = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

echo json_encode($smrfs);
exit;