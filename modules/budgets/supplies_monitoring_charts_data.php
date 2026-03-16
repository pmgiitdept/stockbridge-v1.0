<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer']);

$project = $_GET['project'] ?? '';
$period = $_GET['period'] ?? '';
$field = $_GET['field'] ?? '';

// Prepare $projectsLabels, $scSavingsData, $teSavingsData, $scPercData, $tePercData, $months, $monthlySC, $monthlyTE
// Use similar logic from your main page to calculate totals per project or per month

// Example response:
echo json_encode([
    'projects' => $projectsLabels,
    'scSavings' => $scSavingsData,
    'teSavings' => $teSavingsData,
    'scPerc' => $scPercData,
    'tePerc' => $tePercData,
    'months' => $months,
    'monthlySC' => $monthlySC,
    'monthlyTE' => $monthlyTE
]);