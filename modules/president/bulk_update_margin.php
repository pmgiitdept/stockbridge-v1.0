<?php
header('Content-Type: application/json');

require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['purchasing_officer']);

$data = json_decode(file_get_contents("php://input"), true);

$margin = $data['margin_percent'] ?? null;
$ids = $data['ids'] ?? [];
$category = $data['category'] ?? null;
$applyAll = $data['apply_all'] ?? false;

if($margin === null){
    echo json_encode(['success'=>false,'message'=>'Margin required']);
    exit;
}

$margin = floatval($margin);

$conn->begin_transaction();

try {

    if(!empty($ids)){
        // ✅ CASE 1: Selected rows
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = $conn->prepare("
            SELECT id, unit_price FROM price_lists WHERE id IN ($placeholders)
        ");
        $stmt->bind_param($types, ...$ids);

    } elseif($category){
        // ✅ CASE 2: Category filter (SC / TE)
        $stmt = $conn->prepare("
            SELECT id, unit_price FROM price_lists WHERE legend = ?
        ");
        $stmt->bind_param("s", $category);

    } else {
        // ✅ CASE 3: Apply to ALL
        $stmt = $conn->prepare("
            SELECT id, unit_price FROM price_lists
        ");
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while($row = $result->fetch_assoc()){
        $newPmgi = $row['unit_price'] + ($row['unit_price'] * ($margin / 100));

        $update = $conn->prepare("
            UPDATE price_lists 
            SET margin_percent = ?, pmgi_unit_price = ?
            WHERE id = ?
        ");
        $update->bind_param("ddi", $margin, $newPmgi, $row['id']);
        $update->execute();
        $update->close();
    }

    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Bulk margin update to $margin% (Mode: " . 
        (!empty($ids) ? "Selected Items" : ($category ? "Category $category" : "All Items")) . ")",
        "Price List Management"
    );

    $conn->commit();

    echo json_encode(['success'=>true]);

} catch(Exception $e){
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}