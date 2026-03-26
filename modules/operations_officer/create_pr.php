<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['operations_officer']);
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['period']) || empty($data['date']) || empty($data['requesting_department']) || empty($data['items'])) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields."
    ]);
    exit;
}

$period = $conn->real_escape_string($data['period']);
$date = $conn->real_escape_string($data['date']);
$requesting_department = $conn->real_escape_string($data['requesting_department']);
$project = $conn->real_escape_string($data['project'] ?? '');
$purpose = $conn->real_escape_string($data['purpose_of_requisition'] ?? '');

$requested_by = $_SESSION['full_name'] ?? '';
$reviewed_by = $conn->real_escape_string($data['reviewed_by'] ?? '');
$approved_by = $conn->real_escape_string($data['approved_by'] ?? '');
$received_by = $conn->real_escape_string($data['received_by'] ?? '');
$created_by = $_SESSION['user_id'];

$items = $data['items'];

$conn->begin_transaction();

try {

    $yearMonth = date("Ym", strtotime($period));

    $countQuery = "
        SELECT COUNT(*) as total 
        FROM pr_forms 
        WHERE DATE_FORMAT(period, '%Y%m') = '$yearMonth'
    ";
    $countResult = $conn->query($countQuery);
    $countRow = $countResult->fetch_assoc();
    $nextNumber = intval($countRow['total']) + 1;

    $pr_id = "PR-" . $yearMonth . "-" . str_pad($nextNumber, 4, "0", STR_PAD_LEFT);

    $insertForm = "
        INSERT INTO pr_forms 
        (pr_id, period, date, requesting_department, project, purpose_of_requisition, 
         requested_by, reviewed_by, approved_by, received_by, created_by)
        VALUES 
        ('$pr_id', '$period', '$date', '$requesting_department', '$project', '$purpose',
         '$requested_by', '$reviewed_by', '$approved_by', '$received_by', '$created_by')
    ";
    $conn->query($insertForm);

    $stmt = $conn->prepare("
        INSERT INTO pr_items 
        (pr_id, quantity, unit, item_description, remarks, justification)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $quantity = floatval($item['quantity']);
        $unit = $item['unit'];
        $description = $item['item_description'];
        $remarks = $item['remarks'] ?? '';
        $justification = $item['justification'] ?? '';

        $stmt->bind_param(
            "sdssss",
            $pr_id,
            $quantity,
            $unit,
            $description,
            $remarks,
            $justification
        );
        $stmt->execute();
    }

    $itemSummary = [];
    foreach ($items as $item) {
        $itemSummary[] = $item['item_description'] . " x" . $item['quantity'] . " " . $item['unit'];
    }

    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Created PR $pr_id | Period: $period | Project: $project | Items: " . implode(" | ", $itemSummary) . " | Requested By: $requested_by",
        "PR Creation"
    );

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "PR created successfully.",
        "pr_id" => $pr_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "Failed to create PR: " . $e->getMessage()
    ]);
}