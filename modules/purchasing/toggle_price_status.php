<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php"; // ✅ ADD THIS

authorize(['purchasing_officer', 'president', 'operations_manager', 'admin']);

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success'=>false,'message'=>'Invalid ID']);
    exit;
}

/**
 * ✅ Get current item details (NOT just status)
 */
$stmt = $conn->prepare("
    SELECT item_code, item_description, status 
    FROM price_lists 
    WHERE id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    echo json_encode(['success'=>false,'message'=>'Item not found']);
    exit;
}

$oldStatus = $result['status'];
$newStatus = $oldStatus === 'active' ? 'inactive' : 'active';

/**
 * ✅ Update status
 */
$stmt = $conn->prepare("UPDATE price_lists SET status=? WHERE id=?");
$stmt->bind_param("si", $newStatus, $id);

if ($stmt->execute()) {

    /**
     * ✅ MAIN AUDIT LOG
     */
    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Toggled price list ID $id | {$result['item_code']} / {$result['item_description']} | Status: $oldStatus → $newStatus",
        "Price List Management"
    );

    $audit_log_id = $conn->insert_id;

    /**
     * ✅ FIELD-LEVEL AUDIT (like your update file)
     */
    if ($oldStatus !== $newStatus) {

        $detail_stmt = $conn->prepare("
            INSERT INTO price_list_audit_details
            (audit_log_id, reference_id, item_code, field_name, old_value, new_value)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        // reference_id not fetched → set as NULL or empty
        $reference_id = ''; 

        $field_name = 'status';

        $detail_stmt->bind_param(
            "isssss",
            $audit_log_id,
            $reference_id,
            $result['item_code'],
            $field_name,
            $oldStatus,
            $newStatus
        );

        $detail_stmt->execute();
        $detail_stmt->close();
    }

    echo json_encode(['success'=>true]);

} else {
    echo json_encode(['success'=>false,'message'=>'Update failed']);
}

$stmt->close();
?>