<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['client']);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if(empty($data) || !is_array($data)) {
    echo json_encode(['success'=>false,'message'=>'Invalid data']);
    exit;
}

$referenceId = 'FORM-' . strtoupper(uniqid());

$userId = $_SESSION['user_id'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO client_forms (reference_id, user_id, item_code, item_description, unit, quantity) VALUES (?, ?, ?, ?, ?, ?)");

    foreach($data as $row) {
        $stmt->bind_param(
            "sisssi",
            $referenceId,
            $userId,
            $row['item_code'],
            $row['item_description'],
            $row['unit'],
            $row['quantity']
        );
        $stmt->execute();

        logAudit(
            $conn,
            $userId,
            "Submitted form $referenceId: Item Code {$row['item_code']} / {$row['item_description']} / Unit: {$row['unit']} / Quantity: {$row['quantity']}",
            "Client Form Submission"
        );
    }

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'Form submitted successfully!']);
} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
?>
