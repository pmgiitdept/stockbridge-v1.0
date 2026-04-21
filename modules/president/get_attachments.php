<?php
require_once "../../config/database.php";

$ref_id   = $_GET['ref_id'] ?? '';
$month    = $_GET['month'] ?? '';
$item     = $_GET['item'] ?? '';
$category = $_GET['category'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = "";

/* =========================
   FILTER: SINGLE REFERENCE
========================= */
if ($ref_id !== '') {
    $where .= " AND im.reference_id = ?";
    $params[] = intval($ref_id);
    $types .= "i";
}

/* =========================
   FILTER: MONTH
========================= */
if ($month !== '') {
    $where .= " AND DATE_FORMAT(im.created_at, '%Y-%m') = ?";
    $params[] = $month;
    $types .= "s";
}

/* =========================
   FILTER: ITEM NAME
========================= */
if ($item !== '') {
    $where .= " AND im.item_name LIKE ?";
    $params[] = "%$item%";
    $types .= "s";
}

/* =========================
   FILTER: CATEGORY
========================= */
if ($category !== '') {
    $where .= " AND im.category = ?";
    $params[] = $category;
    $types .= "s";
}

/* =========================
   MAIN QUERY
========================= */
$sql = "
    SELECT 
        ia.file_name,
        ia.file_path,
        ia.uploaded_at,
        im.reference_id,
        im.item_name
    FROM inventory_attachments ia
    JOIN inventory_movements im ON ia.movement_id = im.id
    $where
    ORDER BY ia.uploaded_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => $conn->error
    ]);
    exit;
}

/* =========================
   BIND PARAMS (SAFE)
========================= */
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

/* =========================
   RESPONSE
========================= */
echo json_encode($data);