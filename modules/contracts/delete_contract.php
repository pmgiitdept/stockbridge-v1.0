<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php"; 

authorize(['operations_officer']);

ob_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown error occurred.'];

if (empty($_POST['contract_id'])) {
    $response['message'] = "Contract ID is required.";
    echo json_encode($response);
    exit;
}

$contract_id = intval($_POST['contract_id']);

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("SELECT particulars FROM contracts WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Contract not found.");
    }

    $contract = $result->fetch_assoc();
    $stmt->close();

    $deleteStmt = $conn->prepare("DELETE FROM contracts WHERE id = ?");
    if (!$deleteStmt) {
        throw new Exception("Delete prepare failed: " . $conn->error);
    }

    $deleteStmt->bind_param("i", $contract_id);

    if (!$deleteStmt->execute()) {
        throw new Exception("Delete failed: " . $deleteStmt->error);
    }

    $deleteStmt->close();

    $conn->commit();

    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Deleted contract item '{$contract['particulars']}' 
        (Category: {$contract['category']}, Field: {$contract['field']}) 
        for client ID {$contract['user_id']}",
        "Contracts"
    );

    $response['success'] = true;
    $response['message'] = "Contract deleted successfully.";

} catch (Exception $e) {

    $conn->rollback();
    $response['message'] = $e->getMessage();
}

ob_clean();
echo json_encode($response);
exit;
?>