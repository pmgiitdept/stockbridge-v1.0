<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../vendor/autoload.php";

authorize(['operations_officer']);

$smrf_id = $_GET['smrf_id'] ?? null;
$reference_id = $_GET['reference_id'] ?? null;

if (!$smrf_id) {
    die("Invalid SMRF ID.");
}

$stmt = $conn->prepare("SELECT * FROM smrf_forms WHERE smrf_id = ? AND reference_id = ?");
$stmt->bind_param("ss", $smrf_id, $reference_id);
$stmt->execute();
$result = $stmt->get_result();
$smrf = $result->fetch_assoc();

if (!$smrf) {
    die("SMRF not found.");
}

$itemStmt = $conn->prepare("
    SELECT item_code, item_description, quantity, unit
    FROM smrf_items
    WHERE smrf_id = ?
");
if (!$itemStmt) {
    die("Prepare failed: " . $conn->error);
}
$itemStmt->bind_param("s", $smrf_id);
$itemStmt->execute();
$itemResult = $itemStmt->get_result();
$items = $itemResult->fetch_all(MYSQLI_ASSOC);

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_left' => 10,
    'margin_right' => 10,
    'default_font' => 'leelawadeeui'
]);

$html = '

<style>
body { font-family: "leelawadeeui", sans-serif; font-size:12px; }

.main-header, .secondary-header { width:100%; border:1.5px solid #000; border-collapse:collapse; }
.logo-cell { width:100px; text-align:center; vertical-align:middle; padding:0; border-right:1.5px solid #000; }
.logo { width:100px; display:block; }
.company-title, .form-info { border:1px solid #000; text-align:center; padding:4px 0; margin:0; font-weight:bold; }
.company-title { font-size:18px; }
.form-info { font-size:13px; }
.form-code-table { width:100%; border-collapse:collapse; }
.form-code-table td { border:1px solid #000; text-align:center; padding:4px; font-size:11px; }
.first-col { width:90%; }
.second-col { width:10%; }
.secondary-header td { border:1px solid #000; padding:4px; vertical-align:middle; }
.secondary-header .label-col { width:17%; font-weight:bold; text-align:right; padding-left:6px; }
.secondary-header .value-col { width:83%; text-align:left; }

.items-table { width:100%; border-collapse:collapse; margin-top:5px; }
.items-table th, .items-table td { border:1px solid #000; padding:4px; font-size:11px; }
.items-table th { 
    text-align:center; 
    font-weight:bold; 
    border-bottom:1px solid #000; 
    background-color:#f0f0f0; 
}
.items-table td { text-align:center; }
.items-table td.description { text-align:left; }
</style>

<table class="main-header">
<tr>
    <td class="logo-cell" rowspan="3">
        <img src="../../assets/images/pmgi.png" class="logo">
    </td>
    <td class="company-title">PROFESSIONAL MAINTENANCE GROUP, INC.</td>
</tr>
<tr>
    <td class="form-info">SUPPLIES AND MATERIALS REQUISITION FORM</td>
</tr>
<tr>
    <td class="form-info" style="padding:0; height:20px;">
        <table style="width:100%; border-collapse:collapse; height:100%;">
            <tr style="height:100%;">
                <td class="first-col" style="border-right:1px solid #000; text-align:center; vertical-align:middle; padding:0;">
                    OPS-FRM-12-00-092623
                </td>
                <td class="second-col" style="text-align:center; vertical-align:middle; padding:0;">
                    Edition | 2
                </td>
            </tr>
        </table>
    </td>
</tr>
</table>

<br>

<table class="secondary-header">
<tr>
    <td class="label-col">PROJECT:</td>
    <td class="value-col">'.strtoupper(htmlspecialchars($smrf['project'])).'</td>
</tr>
<tr>
    <td class="label-col">PROJECT CODE:</td>
    <td class="value-col">'.strtoupper(htmlspecialchars($smrf['project_code'] ?: '-')).'</td>
</tr>
<tr>
    <td class="label-col">PERIOD:</td>
    <td class="value-col">FOR THE MONTH OF '.strtoupper(date("M Y", strtotime($smrf['period']))).'</td>
</tr>
</table>

<br>

<table class="items-table">
<thead>
<tr>
    <th>Item Code</th>
    <th>Items</th>
    <th>Quantity</th>
    <th>UoM</th>
</tr>
</thead>
<tbody>
';

if (!empty($items)) {
    foreach ($items as $item) {
        $html .= '
        <tr>
            <td style="text-align:left;">'.htmlspecialchars($item['item_code']).'</td>
            <td class="description" style="text-align:left;">'.htmlspecialchars($item['item_description']).'</td>
            <td>'.intval($item['quantity']).'</td>
            <td>'.htmlspecialchars($item['unit']).'</td>
        </tr>
        ';
    }
} else {
    $html .= '
    <tr>
        <td colspan="4" style="text-align:center;">NO ITEMS FOUND</td>
    </tr>
    ';
}

$totalRowsPerPage = 30;
$currentRowCount = !empty($items) ? count($items) : 1;
if ($currentRowCount < $totalRowsPerPage) {
    $emptyRows = $totalRowsPerPage - $currentRowCount;
    for ($i = 0; $i < $emptyRows; $i++) {
        $html .= '
        <tr>
            <td>&nbsp;</td>
            <td class="description">&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
        ';
    }
}

$html .= '
</tbody>
</table>
';

$mpdf->WriteHTML($html);
$mpdf->Output("$smrf_id.pdf","I");