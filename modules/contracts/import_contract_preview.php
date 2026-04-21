<?php
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// 🔒 Check file exists
if (!isset($_FILES['excel_file']) || empty($_FILES['excel_file']['tmp_name'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded'
    ]);
    exit;
}

// 🔒 Check upload error
if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'message' => 'Upload error code: ' . $_FILES['excel_file']['error']
        ]);
        exit;
    }
}

$tmp = $_FILES['excel_file']['tmp_name'];

// 🔒 Ensure temp file exists
if (!file_exists($tmp)) {
    echo json_encode([
        'success' => false,
        'message' => 'Temporary file missing'
    ]);
    exit;
}

try {
    $spreadsheet = IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    if (count($rows) <= 1) {
        echo json_encode([
            'success' => false,
            'message' => 'Excel file is empty or missing data'
        ]);
        exit;
    }

    unset($rows[0]); // remove header row

    $result = [];

    foreach ($rows as $index => $r) {

        // Skip completely empty rows
        if (empty(array_filter($r))) continue;

        $result[] = [
        'unit_no' => trim($r[0] ?? ''),
        'particulars' => trim($r[1] ?? ''),
        'quantity' => (float)($r[2] ?? 0),
        'unit' => trim($r[3] ?? ''),
        'cost_per_unit' => is_numeric(str_replace(',', '', $r[4] ?? 0))
        ? (float) str_replace(',', '', $r[4])
        : 0,
        'frequency' => trim($r[5] ?? 'Monthly'),
        'category' => trim($r[6] ?? 'Supply'),
        'billing_type' => trim($r[7] ?? 'none'),
        'site' => trim($r[8] ?? '')
    ];
    }

    echo json_encode([
        'success' => true,
        'rows' => $result
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error reading Excel: ' . $e->getMessage()
    ]);
}