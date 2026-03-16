<?php
require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"),true);

$name = $data['name'];
$formula = $data['formula'];

$stmt = $conn->prepare("
INSERT INTO monitoring_custom_columns(column_name,formula)
VALUES(?,?)
");

$stmt->bind_param("ss",$name,$formula);
$stmt->execute();

echo json_encode(["success"=>true]);