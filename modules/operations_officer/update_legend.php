<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$legend = $data['legend'] ?? null;

if(!$id || !in_array($legend, ['SC','TE'])){
    echo json_encode(['success'=>false,'message'=>'Invalid data']);
    exit;
}

$stmt = $conn->prepare("UPDATE price_lists SET legend=? WHERE id=?");
$stmt->bind_param("si",$legend,$id);

if($stmt->execute()){
    echo json_encode(['success'=>true]);
}else{
    echo json_encode(['success'=>false,'message'=>'Update failed']);
}