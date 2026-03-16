<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['period'])) {
    echo json_encode(["success"=>false, "message"=>"Period is required."]);
    exit;
}

$period = date('Y-m', strtotime($data['period']));
$selectedSmrfs = $data['smrf_ids'] ?? [];

try {
    if (empty($selectedSmrfs)) {
        $stmt = $conn->prepare("
            SELECT si.quantity, si.unit, si.item_description, si.remarks, sf.smrf_id
            FROM smrf_items si
            INNER JOIN smrf_forms sf ON si.smrf_id = sf.smrf_id
            WHERE DATE_FORMAT(sf.period,'%Y-%m') = ?
            ORDER BY sf.smrf_id ASC, si.id ASC
        ");
        $stmt->bind_param("s", $period);
    } else {
        $placeholders = implode(',', array_fill(0, count($selectedSmrfs), '?'));
        $types = str_repeat('s', count($selectedSmrfs)) . 's';
        $params = [...$selectedSmrfs, $period];

        $query = "
            SELECT si.quantity, si.unit, si.item_description, si.remarks, sf.smrf_id
            FROM smrf_items si
            INNER JOIN smrf_forms sf ON si.smrf_id = sf.smrf_id
            WHERE sf.smrf_id IN ($placeholders) AND DATE_FORMAT(sf.period,'%Y-%m') = ?
            ORDER BY sf.smrf_id ASC, si.id ASC
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["success"=>true, "items"=>$items]);

} catch(Exception $e) {
    echo json_encode(["success"=>false, "message"=>"Failed to fetch SMRF items."]);
}