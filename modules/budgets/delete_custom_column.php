<?php
require_once "../../config/database.php";

$id = $_POST['id'];

$stmt = $conn->prepare("
DELETE FROM monitoring_custom_columns
WHERE id=?
");

$stmt->bind_param("i",$id);
$stmt->execute();

echo json_encode(["success"=>true]);