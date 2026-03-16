<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);

$data = json_decode(file_get_contents('php://input'), true);

$type = $data['type'] ?? '';
$name = $data['name'] ?? '';

$response = ['success'=>false];

if($type && $name){
    if($type === 'status'){
        $stmt = $conn->prepare("INSERT INTO monitoring_status_options (status_name, color) VALUES (?, ?)");
        $color = '#3b82f6'; 
    } elseif($type === 'remark'){
        $stmt = $conn->prepare("INSERT INTO monitoring_remarks_options (remark_name, color) VALUES (?, ?)");
        $color = '#6b7280'; 
    }

    if(isset($stmt)){
        $stmt->bind_param("ss", $name, $color);
        if($stmt->execute()){
            $response['success'] = true;
            $response['id'] = $stmt->insert_id;
            $response['color'] = $color;
        }
        $stmt->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response);