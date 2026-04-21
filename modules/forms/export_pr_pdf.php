<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../vendor/autoload.php";

authorize(['operations_officer', 'operations_manager', 'president', 'purchasing_officer']);

$pr_id = $_GET['pr_id'] ?? null;
if (!$pr_id) die("Invalid PR ID.");

// Fetch PR
$stmt = $conn->prepare("SELECT * FROM pr_forms WHERE pr_id = ?");
$stmt->bind_param("s", $pr_id);
$stmt->execute();
$result = $stmt->get_result();
$pr = $result->fetch_assoc();

if (!$pr) die("PR not found.");

// Fetch approved_by and received_by names
function getUserName($userId, $conn) {
    if (!$userId) return '-';
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['full_name'] ?? '-';
}

$approvedByName = getUserName($pr['approved_by'], $conn);
$receivedByName = getUserName($pr['received_by'], $conn);

// Fetch PR items
$itemStmt = $conn->prepare("
    SELECT quantity, unit, item_description, remarks
    FROM pr_items
    WHERE pr_id = ?
");
$itemStmt->bind_param("s", $pr_id);
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
body { font-family: "leelawadeeui", sans-serif; font-size:10px; margin:0; padding:0; }
.main-container { display:flex; flex-direction:column; height:100%; }
.main-header { text-align:center; margin-bottom:8px; }
.main-header img { width:60px; display:block; margin:0 auto 8px auto; }
.main-header div { font-size: 9pt; }
.main-header div:first-child { font-size:12pt; font-weight:bold; }
.main-header div:last-child { font-size:11pt; font-weight:bold; margin-top:4px; }
.details-table, .items-table, .signature-table { width:100%; border-collapse:collapse; }
.details-table td { border:1px solid #000; padding:4px; font-size:9px; vertical-align:top; }
.items-table th, .items-table td { border:1px solid #000; padding:4px; font-size:9px; text-align:center; }
.items-table th { font-weight:bold; border-bottom:1px solid #000; }
td.description { text-align:center; }
.justification-box {
    border: 1px solid #000;
    padding: 10px;
    height: 60px;  
    font-size: 9px;
    text-align: left;
}
    
.signature-table td {
    border: 1px solid #000;
    padding: 6px;
    font-size: 12px;
    height: 80px; /* taller signature box */
    display: flex;
    flex-direction: column;
}

.signature-label {
    font-weight: bold;
    text-align: center;
}

.signature-name-wrapper {
    flex-grow: 1; /* take the remaining vertical space */
    display: flex;
    align-items: center; /* vertically center the name */
    justify-content: center; /* optional: center horizontally */
}

.signature-name {
    text-align: center;
}

.signature-note {
    font-size: 10px;
    text-align: center;
}
.content-wrapper { flex-grow:1; display:flex; flex-direction:column; justify-content:space-between; height: 100%; }
.items-table { flex-grow: 1; }
</style>

<div class="main-container">
    <div class="main-header">
        <img src="../../assets/images/pmgi.png">
        <div style="font-weight:bold; font-size:12pt;">PROFESSIONAL MAINTENANCE GROUP, INC.</div>
        <div style="font-size:9pt;">#37 Bayani Rd., AFPOVAI Western Bicutan Taguig City, Metro Manila, Philippines</div>
        <div style="font-size:9pt;">Tel. Nos. 856-3553, 808-9425, 808-9282</div>
        <div style="font-size:9pt;">VAT Reg. TIN: 004-641-129-00000</div>
        <div style="font-weight:bold; font-size:11pt; margin-top:5px;">PURCHASE REQUISITION</div>
    </div>

    <div class="content-wrapper">
        <table class="details-table">
        <tr>
            <td style="width:24%; text-align:left;"><strong>DATE</strong></td>
            <td style="width:76%;">'.date("F d, Y", strtotime($pr['created_at'])).'</td>
        </tr>
        <tr><td style="text-align:left;"><strong>REQUESTING DEPARTMENT</strong></td><td>'.htmlspecialchars($pr['requesting_department']).'</td></tr>
        <tr><td style="text-align:left;"><strong>PROJECT</strong></td><td>'.htmlspecialchars($pr['project'] ?: '-').'</td></tr>
        <tr><td style="text-align:left;"><strong>PURPOSE OF REQUISITION</strong></td><td>'.htmlspecialchars($pr['purpose_of_requisition'] ?: '-').'</td></tr>
        </table>

        <table class="items-table">
        <thead>
        <tr>
            <th style="width:12%;">QTY</th>
            <th style="width:12%;">UNIT</th>
            <th style="width:56%;">DESCRIPTION / SPECIFICATION</th>
            <th style="width:20%;">REMARKS</th>
        </tr>
        </thead>
        <tbody>';

$totalRowsPerPage = 30; 
$currentRowCount = !empty($items) ? count($items) : 1;
$emptyRows = $totalRowsPerPage - $currentRowCount;
if ($emptyRows < 0) $emptyRows = 0;

if (!empty($items)) {
    foreach ($items as $item) {
        $qty = is_numeric($item['quantity']) ? intval($item['quantity']) : $item['quantity'];
        $html .= '
        <tr>
            <td>'.$qty.'</td>
            <td>'.htmlspecialchars($item['unit']).'</td>
            <td class="description">'.htmlspecialchars($item['item_description']).'</td>
            <td>'.htmlspecialchars($item['remarks']).'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="4" style="text-align:center;">No items found.</td></tr>';
}

for ($i = 0; $i < $emptyRows; $i++) {
    $html .= '
    <tr>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td class="description">&nbsp;</td>
        <td>&nbsp;</td>
    </tr>';
}

$html .= '</tbody></table>';

$html .= '
<!-- JUSTIFICATION -->
<div class="justification-box">
'.(!empty($pr['purpose_of_requisition']) ? htmlspecialchars($pr['purpose_of_requisition']) : 'JUSTIFICATION (if applicable)').'
</div>

<!-- SIGNATURES -->
<table class="signature-table">
<tr>
    <td>
        <span class="signature-label">Requested By</span>
        <div class="signature-name">'.htmlspecialchars($pr['requested_by'] ?: '-').'</div>
        <div class="signature-note">Signature over Printed Name / Date</div>
    </td>
    <td>
        <span class="signature-label">Reviewed By</span>
        <div class="signature-name">'.htmlspecialchars($pr['reviewed_by'] ?: '-').'</div>
        <div class="signature-note">Signature over Printed Name / Date</div>
    </td>
    <td>
        <span class="signature-label">Approved By</span>
        <div class="signature-name">'.htmlspecialchars($approvedByName).'</div>
        <div class="signature-note">Signature over Printed Name / Date</div>
    </td>
    <td>
        <span class="signature-label">Received By</span>
        <div class="signature-name">'.htmlspecialchars($receivedByName).'</div>
        <div class="signature-note">Signature over Printed Name / Date</div>
    </td>
</tr>
</table>
</div>
</div>';

$mpdf->WriteHTML($html);
$mpdf->Output("PR_$pr_id.pdf","I");
?>