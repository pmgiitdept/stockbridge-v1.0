<?php
require "../../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['operations_officer','admin','president','operations_manager']);

header('Content-Type: application/json');

if(empty($_FILES['excel_file']['tmp_name'])){
    echo json_encode(['success'=>false,'message'=>'No file uploaded']);
    exit;
}

$user_id = intval($_POST['user_id']);
$field_selected = $_POST['field'];

$spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();

$frequency_map = [
    "Monthly" => 1,
    "Every 2 months" => 0.5,
    "Quarterly" => 0.25,
    "Semi-Annually" => 0.1667,
    "Annually" => 1/12,
    "Every 1.5 years" => 1/18,
    "Every 2 years" => 1/24,
    "Every 3 years" => 1/36,
    "Every 4 years" => 1/48
];

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        INSERT INTO contracts 
        (user_id, unit_no, particulars, quantity, unit, cost_per_unit, frequency, category, field, site, cost_per_month, total_cost, billing_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $totalInserted = 0;

    // skip header row
    for($i = 1; $i < count($rows); $i++){

        list(
            $unit_no,
            $particulars,
            $quantity,
            $unit,
            $cost_per_unit,
            $frequency,
            $category,
            $billing_type,
            $site
        ) = $rows[$i];

        $quantity = intval($quantity);
        $cost_per_unit = floatval($cost_per_unit);

        if($billing_type === 'free_of_charge' || $billing_type === 'bill_to_actual'){
            $cost_per_unit = 0;
            $total_cost = 0;
            $cost_per_month = 0;
        } else {
            $total_cost = $quantity * $cost_per_unit;
            $cost_per_month = $total_cost * ($frequency_map[$frequency] ?? 0);
        }

        $sites = array_filter(array_map('trim', explode(',', $site)));
        if(empty($sites)) $sites = [null];

        foreach($sites as $s){

            $stmt->bind_param(
                "issisdssssdds",
                $user_id,
                $unit_no,
                $particulars,
                $quantity,
                $unit,
                $cost_per_unit,
                $frequency,
                $category,
                $field_selected,
                $s,
                $cost_per_month,
                $total_cost,
                $billing_type
            );

            $stmt->execute();
            $totalInserted++;
        }
    }

    $conn->commit();

    logAudit(
        $conn,
        $_SESSION['user_id'],
        "Imported $totalInserted contracts via Excel",
        "Contracts"
    );

    echo json_encode(['success'=>true]);

} catch(Exception $e){
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}