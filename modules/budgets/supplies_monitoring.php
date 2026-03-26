<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'operations_manager', 'president']);

$selectedPeriod = $_GET['period'] ?? '';
$selectedProjectChart = $_GET['project_chart'] ?? '';

$sql = "
    SELECT 
        u.full_name AS project,
        c.field
    FROM contracts c
    JOIN users u ON c.user_id = u.id
    GROUP BY c.user_id, c.field
    ORDER BY u.full_name ASC
";

$projects = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$groundsProjects = [];
$housekeepingProjects = [];

foreach($projects as $proj){

    if($proj['field'] === 'Grounds & Landscape'){
        $groundsProjects[] = $proj;
    }

    if($proj['field'] === 'Housekeeping'){
        $housekeepingProjects[] = $proj;
    }

}

$totals = [];
$statusData = [];

$months_per_frequency = [
    "Monthly"=>1,
    "Every 2 months"=>2,
    "Quarterly"=>3,
    "Semi-Annually"=>6,
    "Annually"=>12,
    "Every 1.5 years"=>18,
    "Every 2 years"=>24,
    "Every 3 years"=>36,
    "Every 4 years"=>48
];

foreach($projects as $proj){

$query = "
    SELECT 
    SUM(CASE WHEN legend='SC' THEN amount ELSE 0 END) AS sc_total,
    SUM(CASE WHEN legend='TE' THEN amount ELSE 0 END) AS te_total
    FROM smrf_items si
    JOIN smrf_forms sf ON si.smrf_id = sf.smrf_id
    WHERE sf.project = ?
";

$params = [$proj['project']];
$types = "s";

if(!empty($selectedPeriod)){
    $query .= " AND DATE_FORMAT(sf.period,'%Y-%m')=?";
    $params[] = $selectedPeriod;
    $types .= "s";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types,...$params);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$scVatIn = floatval($res['sc_total'] ?? 0);
$teVatIn = floatval($res['te_total'] ?? 0);

$contractQuery = "
    SELECT category, quantity, cost_per_unit, frequency
    FROM contracts c
    JOIN users u ON c.user_id = u.id
    WHERE u.full_name = ?
";

$stmt2 = $conn->prepare($contractQuery);
$stmt2->bind_param("s",$proj['project']);
$stmt2->execute();
$contractRes = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$scContract = 0;
$teContract = 0;

if($proj['field'] === 'Grounds & Landscape'){
    foreach($contractRes as $c){
        $qty = (float)$c['quantity'];
        $cost = (float)$c['cost_per_unit'];
        $freq = $c['frequency'] ?? 'Monthly';

        $months = $months_per_frequency[$freq] ?? 1;
        $costPerMonth = ($qty * $cost) / $months;

        if($c['category']==='Supply') $scContract += $costPerMonth;
        if($c['category']==='Tool') $teContract += $costPerMonth;

    }
    $scContract *= 1.12;
    $teContract *= 1.12;
}

if($proj['field'] === 'Housekeeping'){

    foreach($contractRes as $c){

        $qty = (float)$c['quantity'];
        $cost = (float)$c['cost_per_unit'];
        $freq = $c['frequency'] ?? 'Monthly';

        $months = $months_per_frequency[$freq] ?? 1;
        $costPerMonth = ($qty * $cost) / $months;

        if($c['category'] === 'Supply' && $freq === 'Monthly'){
            $scContract += $costPerMonth;
        }

        if($c['category'] === 'Tool'){
            $teContract += $costPerMonth;
        }
    }
}

$scSavings = $scContract - $scVatIn;
$teSavings = $teContract - $teVatIn;

$totals[$proj['project']] = [
    'sc' => $scVatIn,
    'te' => $teVatIn,

    'sc_contract' => $scContract,
    'te_contract' => $teContract,

    'sc_savings' => $scSavings,
    'te_savings' => $teSavings
];

$statusQuery = "
    SELECT status_id, remark_id 
    FROM supplies_monitoring_notes 
    WHERE project=? AND field=?
";

$stmtStatus = $conn->prepare($statusQuery);
$stmtStatus->bind_param("ss",$proj['project'],$proj['field']);
$stmtStatus->execute();

$statusRes = $stmtStatus->get_result()->fetch_assoc();
$stmtStatus->close();

$statusData[$proj['project']][$proj['field']] = [

'status' => $statusRes['status_id'] ?? '',
'remark' => $statusRes['remark_id'] ?? ''

];

}

$statusOptions = $conn->query("
    SELECT * FROM monitoring_status_options
    ORDER BY status_name ASC
    ")->fetch_all(MYSQLI_ASSOC);

$remarkOptions = $conn->query("
    SELECT * FROM monitoring_remarks_options
    ORDER BY remark_name ASC
    ")->fetch_all(MYSQLI_ASSOC);

$projectsLabels = array_keys($totals);

$scSavingsData = array_map(fn($t)=>$t['sc_savings'],$totals);
$teSavingsData = array_map(fn($t)=>$t['te_savings'],$totals);

$scPercData = array_map(fn($t)=>$t['sc_contract']>0 ? round(($t['sc_savings']/$t['sc_contract'])*100,1):0,$totals);
$tePercData = array_map(fn($t)=>$t['te_contract']>0 ? round(($t['te_savings']/$t['te_contract'])*100,1):0,$totals);

$chartPeriod = $_GET['chart_period'] ?? $selectedPeriod;

$monthlyQuery = "
    SELECT 
    DATE_FORMAT(sf.period,'%Y-%m') as month,
    SUM(CASE WHEN si.legend='SC' THEN si.amount ELSE 0 END) AS sc_total,
    SUM(CASE WHEN si.legend='TE' THEN si.amount ELSE 0 END) AS te_total
    FROM smrf_items si
    JOIN smrf_forms sf ON si.smrf_id = sf.smrf_id
";

$params = [];
$types = "";

if(!empty($chartPeriod)){
    $monthlyQuery .= " WHERE DATE_FORMAT(sf.period,'%Y-%m')=?";
    $params[] = $chartPeriod;
    $types .= "s";
}

$monthlyQuery .= " GROUP BY month ORDER BY month ASC";

$stmt = $conn->prepare($monthlyQuery);

if(!empty($params)){
    $stmt->bind_param($types,...$params);
}

$stmt->execute();
$monthlyResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$months=[];
$monthlySC=[];
$monthlyTE=[];

foreach($monthlyResult as $m){
    $months[] = $m['month'];
    $monthlySC[] = floatval($m['sc_total']);
    $monthlyTE[] = floatval($m['te_total']);
}

$summaryCards = [];

foreach($projects as $proj){

    $tot = $totals[$proj['project']];

    $scPercent = $tot['sc_contract']>0 ? ($tot['sc_savings']/$tot['sc_contract'])*100 : 0;
    $tePercent = $tot['te_contract']>0 ? ($tot['te_savings']/$tot['te_contract'])*100 : 0;

    $needsReview = (
        $tot['sc_savings'] < 0 ||
        $tot['te_savings'] < 0 ||
        $scPercent < 20 ||
        $tePercent < 20
    );

    $statusText = $needsReview ? 'Needs Review' : 'Savings Performing Well';

    $summaryCards[] = [

    'project'=>$proj['project'],
    'field'=>$proj['field'],

    'sc_savings'=>$tot['sc_savings'],
    'te_savings'=>$tot['te_savings'],

    'sc_percent'=>round($scPercent),
    'te_percent'=>round($tePercent),

    'statusText'=>$statusText,
    'needsReview'=>$needsReview

    ];
}

$customColumns = $conn->query("
    SELECT id, column_name, formula
    FROM monitoring_custom_columns
    ORDER BY id ASC
")->fetch_all(MYSQLI_ASSOC);

function computeFormula($formula, $data){
    foreach($data as $key => $value){
        $formula = str_replace($key, $value, $formula);
    }

    try{
        return eval("return $formula;");
    }catch(Throwable $e){
        return 0;
    }
}
?>

<link rel="stylesheet" href="../../assets/css/supplies_monitoring.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="main-content supplies-monitoring-page">
    <div class="page-header">
        <h1>Supplies Monitoring</h1>
        <p class="page-subtitle">Monitor savings and expenditures across all project contracts.</p>
    </div>

    <div class="table-filters">
        <form method="GET" class="filter-form">
            <label>Filter Period:</label>
            <input type="month" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
            <button type="submit" class="btn-filter">Apply</button>
            <a href="supplies_monitoring.php" class="btn-reset">Reset</a>
        </form>
    </div>

    <?php
        $displayMonth = "All Months";
        if(!empty($selectedPeriod)){
            $dateObj = DateTime::createFromFormat('Y-m', $selectedPeriod);
            if($dateObj) {
                $displayMonth = $dateObj->format('F Y'); 
            }
        }
    ?>

        <div class="section-divider">
            <span>Grounds & Landscape Supplies and Tools <small class="section-period"><?= htmlspecialchars($displayMonth) ?></small></span>
        </div>

        <div class="custom-column-toolbar">
            <button id="addCustomColumnBtnGrounds" class="btn-add-column">
                <span class="btn-icon">＋</span>
                <span>Add Custom Column</span>
            </button>
        </div>
        <div class="table-scroll-container">
            <table class="monitoring-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>SC VAT-In<br><span class="table-subtitle">Actual Cost</span></th>
                        <th>SC Contract Amount</th>
                        <th>SC Savings</th>
                        <th>Savings %</th>

                        <th>TE VAT-In<br><span class="table-subtitle">Actual Cost</span></th>
                        <th>TE Contract Amount</th>
                        <th>TE Savings</th>
                        <th>Savings %</th>

                        <th>Status</th>
                        <th>Remarks</th>

                        <?php foreach($customColumns as $col): ?>
                        <th class="custom-col-header">
                            <div class="column-title">
                                <?= htmlspecialchars($col['column_name']) ?>
                                <button class="remove-column" data-id="<?= $col['id'] ?>" title="Remove column">
                                    ✕
                                </button>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php if(empty($groundsProjects)): ?>
                    <tr>
                        <td colspan="11" style="text-align:center;">No Grounds & Landscape data.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($groundsProjects as $row): ?>
                    <?php
                        $currentStatus = $statusData[$row['project']][$row['field']]['status'] ?? '';
                        $currentRemark = $statusData[$row['project']][$row['field']]['remark'] ?? '';

                        $scSavings = $totals[$row['project']]['sc_savings'];
                        $teSavings = $totals[$row['project']]['te_savings'];

                        $scPerc = $totals[$row['project']]['sc_contract']>0 ?
                        round(($scSavings/$totals[$row['project']]['sc_contract'])*100) : 0;

                        $tePerc = $totals[$row['project']]['te_contract']>0 ?
                        round(($teSavings/$totals[$row['project']]['te_contract'])*100) : 0;

                        $scBg = $scSavings < 0 ? '#d88c93' : '#7bc984';
                        $teBg = $teSavings < 0 ? '#d88c93' : '#7bc984';
                    ?>

                    <tr>
                        <td style="text-align:left; font-weight:600;"><?= htmlspecialchars($row['project']) ?></td>
                        <td>₱ <?= number_format($totals[$row['project']]['sc'],2) ?></td>
                        <td>₱ <?= number_format($totals[$row['project']]['sc_contract'],2) ?></td>

                        <td style="background:<?= $scBg ?>;font-weight:600;">
                            ₱ <?= number_format($scSavings,2) ?>
                        </td>

                        <td style="background:<?= $scBg ?>;text-align:center;">
                            <?= $scPerc ?>%
                        </td>

                        <td>₱ <?= number_format($totals[$row['project']]['te'],2) ?></td>
                        <td>₱ <?= number_format($totals[$row['project']]['te_contract'],2) ?></td>

                        <td style="background:<?= $teBg ?>;font-weight:600;">
                            ₱ <?= number_format($teSavings,2) ?>
                        </td>

                        <td style="background:<?= $teBg ?>;text-align:center;">
                            <?= $tePerc ?>%
                        </td>

                        <td>
                            <select class="status-dropdown"
                                data-project="<?= htmlspecialchars($row['project']) ?>"
                                data-field="<?= htmlspecialchars($row['field']) ?>">
                            <option value="">Select</option>
                            <?php foreach($statusOptions as $opt): ?>
                            <option
                                value="<?= $opt['id'] ?>"
                                data-color="<?= $opt['color'] ?>"
                            <?= $currentStatus==$opt['id']?'selected':'' ?>>
                            <?= htmlspecialchars($opt['status_name']) ?>
                            </option>
                            <?php endforeach; ?>

                            </select>
                        </td>

                        <td>
                            <select class="remarks-dropdown"
                                data-project="<?= htmlspecialchars($row['project']) ?>"
                                data-field="<?= htmlspecialchars($row['field']) ?>">
                                <option value="">Select</option>
                                <?php foreach($remarkOptions as $opt): ?>
                            <option
                                value="<?= $opt['id'] ?>"
                                data-color="<?= $opt['color'] ?>"
                                <?= $currentRemark==$opt['id']?'selected':'' ?>>
                                <?= htmlspecialchars($opt['remark_name']) ?>
                            </option>
                            <?php endforeach; ?>
                            </select>
                        </td>
                        <?php
                        $data = [
                            'sc' => $totals[$row['project']]['sc'],
                            'te' => $totals[$row['project']]['te'],
                            'sc_contract' => $totals[$row['project']]['sc_contract'],
                            'te_contract' => $totals[$row['project']]['te_contract'],
                            'sc_savings' => $totals[$row['project']]['sc_savings'],
                            'te_savings' => $totals[$row['project']]['te_savings']
                        ];
                        ?>

                        <?php foreach($customColumns as $col): ?>

                        <?php
                        $value = computeFormula($col['formula'], $data);
                        ?>

                        <td>
                            ₱ <?= number_format($value,2) ?>
                        </td>

                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php
                        $scTotal=0;$teTotal=0;
                        $scContractTotal=0;$teContractTotal=0;
                        $scSavingsTotal=0;$teSavingsTotal=0;

                        foreach($groundsProjects as $p){

                        $scTotal += $totals[$p['project']]['sc'];
                        $teTotal += $totals[$p['project']]['te'];

                        $scContractTotal += $totals[$p['project']]['sc_contract'];
                        $teContractTotal += $totals[$p['project']]['te_contract'];

                        $scSavingsTotal += $totals[$p['project']]['sc_savings'];
                        $teSavingsTotal += $totals[$p['project']]['te_savings'];

                        }

                        $scPercTotal = $scContractTotal>0 ? round(($scSavingsTotal/$scContractTotal)*100) : 0;
                        $tePercTotal = $teContractTotal>0 ? round(($teSavingsTotal/$teContractTotal)*100) : 0;

                        $scBgTotal = $scSavingsTotal < 0 ? '#d88c93' : '#e5e936';
                        $teBgTotal = $teSavingsTotal < 0 ? '#d88c93' : '#e5e936';
                    ?>

                    <tr style="font-weight:bold;background:#f5f5f5;">
                        <td style="text-align:left;">TOTAL</td>
                        <td>₱ <?= number_format($scTotal,2) ?></td>
                        <td>₱ <?= number_format($scContractTotal,2) ?></td>
                        <td style="background:<?= $scBgTotal ?>;">
                        ₱ <?= number_format($scSavingsTotal,2) ?>
                        </td>
                        <td style="background:<?= $scBgTotal ?>;text-align:center;">
                        <?= $scPercTotal ?>%
                        </td>
                        <td>₱ <?= number_format($teTotal,2) ?></td>
                        <td>₱ <?= number_format($teContractTotal,2) ?></td>
                        <td style="background:<?= $teBgTotal ?>;">
                        ₱ <?= number_format($teSavingsTotal,2) ?>
                        </td>
                        <td style="background:<?= $teBgTotal ?>;text-align:center;">
                        <?= $tePercTotal ?>%
                        </td>
                        <td>-</td>
                        <td>-</td>
                        <?php foreach($customColumns as $col): ?>

                        <?php
                        $totalValue = 0;

                        foreach($groundsProjects as $p){

                        $data = [
                            'sc' => $totals[$p['project']]['sc'],
                            'te' => $totals[$p['project']]['te'],
                            'sc_contract' => $totals[$p['project']]['sc_contract'],
                            'te_contract' => $totals[$p['project']]['te_contract'],
                            'sc_savings' => $totals[$p['project']]['sc_savings'],
                            'te_savings' => $totals[$p['project']]['te_savings']
                        ];

                        $totalValue += computeFormula($col['formula'],$data);

                        }
                        ?>

                        <td>₱ <?= number_format($totalValue,2) ?></td>

                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="section-divider">
            <span>Housekeeping Supplies and Tools<small class="section-period"><?= htmlspecialchars($displayMonth) ?></small></span>
        </div>

        <div class="custom-column-toolbar">
            <button id="addCustomColumnBtnHousekeeping" class="btn-add-column">
                <span class="btn-icon">＋</span>
                <span>Add Custom Column</span>
            </button>
        </div>
        <div class="table-scroll-container">
            <table class="monitoring-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>SC VAT-In<br><span class="table-subtitle">Actual Cost</span></th>
                        <th>SC Contract Amount<span class="table-subtitle">VAT-Ex</span></th>
                        <th>SC Savings</th>
                        <th>Savings %</th>
                        <th>TE VAT-In<br><span class="table-subtitle">Actual Cost</span></th>
                        <th>TE Contract Amount<span class="table-subtitle">VAT-Ex</span></th>
                        <th>TE Savings</th>
                        <th>Savings %</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <?php foreach($customColumns as $col): ?>
                        <th class="custom-col-header">
                            <div class="column-title">
                                <?= htmlspecialchars($col['column_name']) ?>
                                <button class="remove-column" data-id="<?= $col['id'] ?>" title="Remove column">
                                    ✕
                                </button>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>

                <?php if(empty($housekeepingProjects)): ?>
                <tr>
                    <td colspan="11" style="text-align:center;">No Housekeeping data.</td>
                </tr>

                <?php else: ?>
                <?php foreach($housekeepingProjects as $row): ?>
                <?php
                    $currentStatus = $statusData[$row['project']][$row['field']]['status'] ?? '';
                    $currentRemark = $statusData[$row['project']][$row['field']]['remark'] ?? '';

                    $scSavings = $totals[$row['project']]['sc_savings'];
                    $teSavings = $totals[$row['project']]['te_savings'];

                    $scPerc = $totals[$row['project']]['sc_contract']>0 ?
                    round(($scSavings/$totals[$row['project']]['sc_contract'])*100) : 0;

                    $tePerc = $totals[$row['project']]['te_contract']>0 ?
                    round(($teSavings/$totals[$row['project']]['te_contract'])*100) : 0;

                    $scBg = $scSavings < 0 ? '#d88c93' : '#7bc984';
                    $teBg = $teSavings < 0 ? '#d88c93' : '#7bc984';
                ?>

                <tr>
                    <td style="text-align:left; font-weight:600;"><?= htmlspecialchars($row['project']) ?></td>
                    <td>₱ <?= number_format($totals[$row['project']]['sc'],2) ?></td>
                    <td>₱ <?= number_format($totals[$row['project']]['sc_contract'],2) ?></td>

                    <td style="background:<?= $scBg ?>;font-weight:600;">
                        ₱ <?= number_format($scSavings,2) ?>
                    </td>

                    <td style="background:<?= $scBg ?>;text-align:center;">
                        <?= $scPerc ?>%
                    </td>

                    <td>₱ <?= number_format($totals[$row['project']]['te'],2) ?></td>
                    <td>₱ <?= number_format($totals[$row['project']]['te_contract'],2) ?></td>

                    <td style="background:<?= $teBg ?>;font-weight:600;">
                        ₱ <?= number_format($teSavings,2) ?>
                    </td>

                    <td style="background:<?= $teBg ?>;text-align:center;">
                        <?= $tePerc ?>%
                    </td>

                    <td>
                        <select class="status-dropdown"
                            data-project="<?= htmlspecialchars($row['project']) ?>"
                            data-field="<?= htmlspecialchars($row['field']) ?>">
                                <option value="">Select</option>
                                    <?php foreach($statusOptions as $opt): ?>
                                <option
                                    value="<?= $opt['id'] ?>"
                                    data-color="<?= $opt['color'] ?>"
                                    <?= $currentStatus==$opt['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($opt['status_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select class="remarks-dropdown"
                            data-project="<?= htmlspecialchars($row['project']) ?>"
                            data-field="<?= htmlspecialchars($row['field']) ?>">
                            <option value="">Select</option>
                            <?php foreach($remarkOptions as $opt): ?>
                            <option
                                value="<?= $opt['id'] ?>"
                                data-color="<?= $opt['color'] ?>"
                                <?= $currentRemark==$opt['id']?'selected':'' ?>>
                                <?= htmlspecialchars($opt['remark_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <?php
                        $data = [
                            'sc' => $totals[$row['project']]['sc'],
                            'te' => $totals[$row['project']]['te'],
                            'sc_contract' => $totals[$row['project']]['sc_contract'],
                            'te_contract' => $totals[$row['project']]['te_contract'],
                            'sc_savings' => $totals[$row['project']]['sc_savings'],
                            'te_savings' => $totals[$row['project']]['te_savings']
                        ];
                        ?>

                        <?php foreach($customColumns as $col): ?>

                        <?php
                        $value = computeFormula($col['formula'], $data);
                        ?>

                        <td>
                            ₱ <?= number_format($value,2) ?>
                        </td>

                        <?php endforeach; ?>
                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <?php
                    $scTotal=0;$teTotal=0;
                    $scContractTotal=0;$teContractTotal=0;
                    $scSavingsTotal=0;$teSavingsTotal=0;

                    foreach($housekeepingProjects as $p){

                    $scTotal += $totals[$p['project']]['sc'];
                    $teTotal += $totals[$p['project']]['te'];

                    $scContractTotal += $totals[$p['project']]['sc_contract'];
                    $teContractTotal += $totals[$p['project']]['te_contract'];

                    $scSavingsTotal += $totals[$p['project']]['sc_savings'];
                    $teSavingsTotal += $totals[$p['project']]['te_savings'];

                    }

                    $scPercTotal = $scContractTotal>0 ? round(($scSavingsTotal/$scContractTotal)*100) : 0;
                    $tePercTotal = $teContractTotal>0 ? round(($teSavingsTotal/$teContractTotal)*100) : 0;

                    $scBgTotal = $scSavingsTotal < 0 ? '#d88c93' : '#e5e936';
                    $teBgTotal = $teSavingsTotal < 0 ? '#d88c93' : '#e5e936';
                ?>

                <tr style="font-weight:bold;background:#f5f5f5;">
                    <td style="text-align:left;">TOTAL</td>
                    <td>₱ <?= number_format($scTotal,2) ?></td>
                    <td>₱ <?= number_format($scContractTotal,2) ?></td>
                    <td style="background:<?= $scBgTotal ?>;">
                    ₱ <?= number_format($scSavingsTotal,2) ?>
                    </td>
                    <td style="background:<?= $scBgTotal ?>;text-align:center;">
                    <?= $scPercTotal ?>%
                    </td>
                    <td>₱ <?= number_format($teTotal,2) ?></td>
                    <td>₱ <?= number_format($teContractTotal,2) ?></td>
                    <td style="background:<?= $teBgTotal ?>;">
                    ₱ <?= number_format($teSavingsTotal,2) ?>
                    </td>
                    <td style="background:<?= $teBgTotal ?>;text-align:center;">
                    <?= $tePercTotal ?>%
                    </td>
                    <td>-</td>
                    <td>-</td>
                    <?php foreach($customColumns as $col): ?>

                        <?php
                        $totalValue = 0;

                        foreach($groundsProjects as $p){

                        $data = [
                            'sc' => $totals[$p['project']]['sc'],
                            'te' => $totals[$p['project']]['te'],
                            'sc_contract' => $totals[$p['project']]['sc_contract'],
                            'te_contract' => $totals[$p['project']]['te_contract'],
                            'sc_savings' => $totals[$p['project']]['sc_savings'],
                            'te_savings' => $totals[$p['project']]['te_savings']
                        ];

                        $totalValue += computeFormula($col['formula'],$data);

                        }
                        ?>

                        <td>₱ <?= number_format($totalValue,2) ?></td>

                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="monitoring-charts-container">
        <div class="monitoring-charts">
            <h2>Analytics & Insights</h2>
            <p class="charts-description">
                Explore savings trends and percentages for SC and TE across projects. 
                Use the filters below to focus on specific periods or projects.
            </p>
            
            <div class="charts-filters">
                <form method="GET" class="filter-form">
                    <label>Filter by Period:</label>
                    <input type="month" name="chart_period" value="<?= htmlspecialchars($selectedPeriod) ?>" onchange="this.form.submit()">
                </form>
            </div>

            <div class="charts-grid">
                <div class="chart-card"><canvas id="savingsComparisonChart"></canvas></div>
                <div class="chart-card"><canvas id="savingsPercentageChart"></canvas></div>
                <div class="chart-card"><canvas id="monthlySavingsChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="monitoring-summary-cards">
        <h2>Project Savings Summary</h2>
        <p class="summary-description">
            This section provides a quick overview of each project’s SC and TE savings. 
            Cards highlighted in red indicate projects that need review due to low or negative savings. 
            Green cards indicate projects performing well and within expected savings targets.
        </p>
        <div class="cards-scroll-container">
            <div class="cards-grid">
                <?php foreach($summaryCards as $card): 
                    $badgeColor = $card['needsReview'] ? '#dc3545' : '#198754'; 
                    $scBg = $card['sc_savings'] < 0 ? '#f8d7da' : '#d1e7dd';
                    $teBg = $card['te_savings'] < 0 ? '#f8d7da' : '#d1e7dd';
                ?>
                <div class="summary-card">
                    <div class="card-header">
                        <h3><?= htmlspecialchars($card['project']) ?></h3>
                        <small><?= htmlspecialchars($card['field']) ?></small>
                        <span class="review-badge" style="background-color: <?= $badgeColor ?>;"><?= $card['statusText'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="card-body-spacer"></div>
                        <div class="savings-row" style="background: <?= $scBg ?>">
                            <span>SC Savings:</span> <strong>₱ <?= number_format($card['sc_savings'],2) ?> (<?= $card['sc_percent'] ?>%)</strong>
                        </div>
                        <div class="savings-row" style="background: <?= $teBg ?>">
                            <span>TE Savings:</span> <strong>₱ <?= number_format($card['te_savings'],2) ?> (<?= $card['te_percent'] ?>%)</strong>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div id="customColumnModal" class="modal-overlay">
    <div class="column-modal">
        <div class="modal-header">
            <h3>Add Custom Monitoring Column</h3>
            <button class="modal-close" onclick="closeColumnModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Column Name</label>
                <input type="text" id="columnName" placeholder="Example: Total VAT In">
            </div>
            <div class="form-group">
                <label>Formula</label>
                <input type="text" id="columnFormula" placeholder="Example: sc + te">
            </div>
            <div class="formula-helper">
                <strong>Available Variables</strong>
                <div class="variables-grid">
                    <span>sc</span>
                    <span>te</span>
                    <span>sc_contract</span>
                    <span>te_contract</span>
                    <span>sc_savings</span>
                    <span>te_savings</span>
                </div>
                <small>Use mathematical operators like + - * /</small>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeColumnModal()">Cancel</button>
            <button id="saveCustomColumn" class="btn-save">Save Column</button>
        </div>
    </div>
</div>

<div id="fixedAddModal" class="inline-add-modal" style="display:none;">
    <span id="modalTitle" style="font-weight:600; color:#333; min-width:80px;"></span>
    <input type="text" id="newOptionInput" placeholder="Enter new option" />
    <button id="saveNewOptionBtn">Add</button>
    <button id="cancelNewOptionBtn">Cancel</button>
</div>

<script>

const modal = document.getElementById("customColumnModal");
const saveBtn = document.getElementById("saveCustomColumn");
const nameInput = document.getElementById("columnName");
const formulaInput = document.getElementById("columnFormula");

document.querySelectorAll(".btn-add-column").forEach(btn => {
    btn.addEventListener("click", () => {
        modal.classList.add("active");
        nameInput.value = "";
        formulaInput.value = "";
        nameInput.focus();
    });
});

function closeColumnModal() {
    modal.classList.remove("active");
}

saveBtn.addEventListener("click", async () => {

    const name = nameInput.value.trim();
    const formula = formulaInput.value.trim();

    if(!name || !formula){
        alert("Please enter both Column Name and Formula.");
        return;
    }

    saveBtn.disabled = true;
    saveBtn.innerText = "Saving...";

    try{
        const response = await fetch("save_custom_column.php",{
            method:"POST",
            headers:{
                "Content-Type":"application/json"
            },
            body:JSON.stringify({
                name:name,
                formula:formula
            })
        });

        const data = await response.json();

        if(data.success){
            location.reload();
        }else{
            alert("Failed to save column.");
        }

    }catch(error){
        console.error(error);
        alert("An error occurred while saving.");
    }
    saveBtn.disabled = false;
    saveBtn.innerText = "Save Column";
});

document.querySelectorAll(".remove-column").forEach(btn => {
    btn.addEventListener("click", async function(){
        
        const columnId = this.dataset.id;
        if(!confirm("Remove this custom column?")) return;

        try{
            await fetch("delete_custom_column.php",{
                method:"POST",
                headers:{
                    "Content-Type":"application/x-www-form-urlencoded"
                },
                body:`id=${columnId}`
            });

            location.reload();

        }catch(error){
            console.error(error);
            alert("Failed to remove column.");
        }
    });
});

window.addEventListener("click",(e)=>{
    if(e.target === modal){
        closeColumnModal();
    }
});

const periodInput = document.querySelector('input[name="period"]');
if(periodInput){
    periodInput.addEventListener('change', function(){
        this.form.submit();
    });
}

document.querySelectorAll('.status-dropdown, .remarks-dropdown').forEach(select => {

    select.addEventListener('change', function(){

        updateDropdownColor(this);

        const row = this.closest('tr');
        const project = this.dataset.project;
        const field = this.dataset.field;

        const status = row.querySelector('.status-dropdown').value;
        const remark = row.querySelector('.remarks-dropdown').value;

        fetch('save_monitoring_status.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`project=${encodeURIComponent(project)}&field=${encodeURIComponent(field)}&status=${status}&remark=${remark}`
        })
        .then(res => res.json())
        .then(data => {
            if(!data.success){
                console.error("Save failed");
            }
        })
        .catch(err => console.error(err));

    });

});

function updateDropdownColor(select){
    const selectedOption = select.options[select.selectedIndex];
    const color = selectedOption?.dataset?.color;

    if(color){
        select.style.backgroundColor = color;
        select.style.color = "#fff";
        select.style.fontWeight = "600";
    }else{
        select.style.backgroundColor = "";
        select.style.color = "";
        select.style.fontWeight = "";
    }

}

document.querySelectorAll('.status-dropdown, .remarks-dropdown')
.forEach(select => updateDropdownColor(select));

let currentType = null;
let activeDropdown = null;

document.querySelectorAll('.status-dropdown, .remarks-dropdown').forEach(select => {

    const addOption = document.createElement('option');
    addOption.value = '__add_new__';
    addOption.text = '+ Add New';
    select.add(addOption);

    select.addEventListener('change', function(){

        if(this.value === '__add_new__'){

            activeDropdown = this;
            currentType = this.classList.contains('status-dropdown') ? 'Status' : 'Remark';

            document.getElementById('modalTitle').textContent = 'Add ' + currentType;
            document.getElementById('newOptionInput').value = '';
            document.getElementById('fixedAddModal').style.display = 'flex';
            document.getElementById('newOptionInput').focus();

        }
    });
});

document.getElementById('saveNewOptionBtn').addEventListener('click', function(){
    const newValue = document.getElementById('newOptionInput').value.trim();
    if(!newValue) return;

    const type = currentType.toLowerCase();

    fetch('add_monitoring_option.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({type, name: newValue})
    })
    .then(res => res.json())
    .then(data => {

        if(data.success){
            const option = document.createElement('option');
            option.value = data.id;
            option.text = newValue;
            option.dataset.color = data.color || '#3b82f6';

            activeDropdown.add(option);
            activeDropdown.value = data.id;

            updateDropdownColor(activeDropdown);
        }else{
            alert("Failed to add new option");
        }

        document.getElementById('fixedAddModal').style.display = 'none';
        activeDropdown = null;
        currentType = null;
    });
});

document.getElementById('cancelNewOptionBtn').addEventListener('click', function(){
    document.getElementById('fixedAddModal').style.display = 'none';
    if(activeDropdown) activeDropdown.value = '';

    activeDropdown = null;
    currentType = null;
});

const projects = <?= json_encode($projectsLabels) ?>;
const scSavings = <?= json_encode($scSavingsData) ?>;
const teSavings = <?= json_encode($teSavingsData) ?>;

const scPerc = <?= json_encode($scPercData) ?>;
const tePerc = <?= json_encode($tePercData) ?>;

const months = <?= json_encode($months) ?>;
const monthlySC = <?= json_encode($monthlySC) ?>;
const monthlyTE = <?= json_encode($monthlyTE) ?>;

const chartOptions = {

    responsive: true,

    plugins: {
        legend: { position: 'top' },

        tooltip:{
            mode:'index',
            intersect:false,
            padding:10,
            backgroundColor:'#333',
            titleColor:'#fff',
            bodyColor:'#fff',
            cornerRadius:6
        }
    },

    scales:{
        y:{
            beginAtZero:true,
            grid:{color:'#eaeaea'},
            ticks:{color:'#555'}
        },
        x:{
            ticks:{color:'#555'},
            grid:{color:'#f5f5f5'}
        }
    }
};

new Chart(document.getElementById('savingsComparisonChart'), {

    type:'bar',

    data:{
        labels:projects,
        datasets:[
            {
                label:'SC Savings',
                data:scSavings,
                backgroundColor:'#0d6efd',
                borderRadius:6
            },
            {
                label:'TE Savings',
                data:teSavings,
                backgroundColor:'#198754',
                borderRadius:6
            }
        ]
    },

    options:{
        ...chartOptions,
        plugins:{
            ...chartOptions.plugins,
            title:{display:true,text:'Savings Comparison'}
        }
    }

});

new Chart(document.getElementById('savingsPercentageChart'), {

    type:'bar',

    data:{
        labels:projects,
        datasets:[
            {
                label:'SC Savings %',
                data:scPerc,
                backgroundColor:'#0d6efd',
                borderRadius:6
            },
            {
                label:'TE Savings %',
                data:tePerc,
                backgroundColor:'#198754',
                borderRadius:6
            }
        ]
    },

    options:{
        ...chartOptions,
        plugins:{
            ...chartOptions.plugins,
            title:{display:true,text:'Savings Percentage'}
        },
        scales:{
            y:{
                ...chartOptions.scales.y,
                max:100,
                ticks:{callback:v=>v+'%'}
            }
        }
    }

});

new Chart(document.getElementById('monthlySavingsChart'), {

    type:'line',

    data:{
        labels:months,
        datasets:[
            {
                label:'SC Total',
                data:monthlySC,
                borderColor:'#0d6efd',
                backgroundColor:'#0d6efd33',
                fill:true,
                tension:0.4
            },
            {
                label:'TE Total',
                data:monthlyTE,
                borderColor:'#198754',
                backgroundColor:'#19875433',
                fill:true,
                tension:0.4
            }
        ]
    },

    options:{
        ...chartOptions,
        plugins:{
            ...chartOptions.plugins,
            title:{display:true,text:'Monthly Savings Trend'}
        }
    }

});
</script>

<?php require_once "../../layouts/footer.php"; ?>