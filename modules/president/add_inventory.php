<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['president','operations_manager','operations_officer']);

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;

$month = date('Y-m');

$item_codes = $_POST['item_code'] ?? [];
$item_names = $_POST['item_name'] ?? [];
$categories = $_POST['category'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$reference_id = trim($_POST['reference_id'] ?? '');

if (empty($item_codes) || empty($reference_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input: missing required fields'
    ]);
    exit;
}

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        INSERT INTO inventory_movements
        (item_code, item_name, category, quantity, movement_type, reference_type, reference_id, created_at)
        VALUES (?, ?, ?, ?, 'IN', 'INVOICE', ?, NOW())
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $movement_ids = [];

    for ($i = 0; $i < count($item_codes); $i++) {

        $code = trim($item_codes[$i] ?? '');
        $name = trim($item_names[$i] ?? '');
        $cat  = trim($categories[$i] ?? '');
        $qty  = floatval($quantities[$i] ?? 0);

        if ($code === '' || $name === '' || $cat === '' || $qty <= 0) {
            throw new Exception("Invalid item data detected.");
        }

        $stmt->bind_param("sssds", $code, $name, $cat, $qty, $reference_id);
        $stmt->execute();

        $movement_ids[] = $stmt->insert_id;

        $stmtMonthly = $conn->prepare("
            INSERT INTO inventory_monthly_summary
            (item_code, item_name, category, month, total_in, total_out, ending_balance)
            VALUES (?, ?, ?, ?, ?, 0, ?)
            ON DUPLICATE KEY UPDATE
                total_in = total_in + VALUES(total_in),
                ending_balance = (total_in + VALUES(total_in)) - total_out
        ");

        if (!$stmtMonthly) {
            throw new Exception("Monthly summary error: " . $conn->error);
        }

        $ending_balance = $qty; 

        $stmtMonthly->bind_param(
            "ssssdd",
            $code,
            $name,
            $cat,
            $month,
            $qty,
            $ending_balance
        );

        $stmtMonthly->execute();
        $stmtMonthly->close();
    }

    $stmt->close();

    if (!empty($_FILES['attachment']['name'])) {

        $uploadDir = "../../uploads/invoices/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $uploadedFileName = time() . "_" . basename($_FILES["attachment"]["name"]);
        $targetFile = $uploadDir . $uploadedFileName;

        if (!move_uploaded_file($_FILES["attachment"]["tmp_name"], $targetFile)) {
            throw new Exception("File upload failed");
        }

        $filePath = "uploads/invoices/" . $uploadedFileName;

        $firstMovementId = $movement_ids[0];

        $stmt2 = $conn->prepare("
            INSERT INTO inventory_attachments
            (movement_id, file_name, file_path, uploaded_at)
            VALUES (?, ?, ?, NOW())
        ");

        if (!$stmt2) {
            throw new Exception($conn->error);
        }

        $stmt2->bind_param("iss", $firstMovementId, $uploadedFileName, $filePath);
        $stmt2->execute();
        $stmt2->close();
    }

    logAudit(
        $conn,
        $userId,
        "Added supplier invoice {$reference_id} with " . count($item_codes) . " item(s)",
        "Inventory - Supplier Invoice"
    );

    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}