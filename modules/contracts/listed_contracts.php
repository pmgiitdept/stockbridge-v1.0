<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['operations_officer', 'admin', 'president', 'operations_manager']);

$clientsStmt = $conn->prepare("
    SELECT id, full_name 
    FROM users 
    WHERE role = 'client' 
    ORDER BY full_name ASC
");
$clientsStmt->execute();
$clients = $clientsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$clientsStmt->close();

$defaultClientId = intval($_GET['client_id'] ?? ($clients[0]['id'] ?? 0));
$defaultClient = null;
foreach ($clients as $c) {
    if ($c['id'] == $defaultClientId) {
        $defaultClient = $c;
        break;
    }
}

$contractsStmt = $conn->prepare("
    SELECT * FROM contracts ORDER BY category ASC, created_at ASC
");
$contractsStmt->execute();
$allContracts = $contractsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$contractsStmt->close();

$contracts = array_filter($allContracts, fn($c) => $c['user_id'] == $defaultClientId);

$availableSites = array_unique(array_filter(array_map(function($c){
    return trim($c['site'] ?? '');
}, $contracts)));

sort($availableSites);

$supplies = array_filter($contracts, fn($c) => $c['category'] === 'Supply');
$tools = array_filter($contracts, fn($c) => $c['category'] === 'Tool');

$months_per_frequency = [
    "Monthly" => 1,
    "Every 2 months" => 2,
    "Quarterly" => 3,
    "Semi-Annually" => 6,
    "Annually" => 12,
    "Every 1.5 years" => 18,
    "Every 2 years" => 24,
    "Every 3 years" => 36,
    "Every 4 years" => 48
];

$defaultField = $_GET['field'] ?? null;

$allFields = array_unique(array_map(fn($c) => $c['field'], $allContracts));
sort($allFields);

if($defaultField) {
    $clientsInField = array_filter($clients, function($client) use ($allContracts, $defaultField) {
        foreach($allContracts as $c) {
            if($c['user_id'] == $client['id'] && $c['field'] == $defaultField) return true;
        }
        return false;
    });
} else {
    $clientsInField = $clients;
}

$totalSupplies = 0;
foreach ($supplies as $c) {
    $quantity = (float)$c['quantity'];
    $cost_per_unit = (float)$c['cost_per_unit'];
    $months = $months_per_frequency[$c['frequency']] ?? 1;
    $cost_per_month = ($quantity * $cost_per_unit) / $months;
    $totalSupplies += $cost_per_month;
}

$vatSupplies = $totalSupplies * 0.12;
$grandTotalSupplies = $totalSupplies + $vatSupplies;

$totalTools = 0;
foreach ($tools as $c) {
    $quantity = (float)$c['quantity'];
    $cost_per_unit = (float)$c['cost_per_unit'];
    $months = $months_per_frequency[$c['frequency']] ?? 1;
    $cost_per_month = ($quantity * $cost_per_unit) / $months;
    $totalTools += $cost_per_month;
}

$vatTools = $totalTools * 0.12;
$grandTotalTools = $totalTools + $vatTools;

$monthlyBudgetTotal = 0;

foreach ($contracts as $c) {
    if (in_array($c['billing_type'] ?? 'none', ['free_of_charge','bill_to_actual'])) continue;

    $freq = $c['frequency'] ?? 'Monthly';
    if (!in_array($freq, ['Monthly','Every 2 months','Quarterly'])) continue;

    $quantity = (float)$c['quantity'];
    $unitCost = (float)$c['cost_per_unit'];
    $months = $months_per_frequency[$freq] ?? 1;

    $monthlyBudgetTotal += ($quantity * $unitCost) / $months;
}

$contractsForSummary = $contracts;

$selectedBudgetSite = $_GET['budget_site'] ?? '';

if ($selectedBudgetSite) {
    $contractsForSummary = array_filter($contracts, function($c) use ($selectedBudgetSite){
        return ($c['site'] ?? '') === $selectedBudgetSite;
    });
}

$frequenciesInContract = [];

foreach ($contractsForSummary as $c) {
    $freq = $c['frequency'];
    if (!isset($months_per_frequency[$freq])) continue;

    $frequenciesInContract[$freq] = $months_per_frequency[$freq];
}

asort($frequenciesInContract);

$summary = [];

foreach ($frequenciesInContract as $freqName => $freqMonths) {

    $summary[$freqName] = [
        'Supply' => 0,
        'Tool' => 0
    ];

    foreach ($contracts as $c) {

        if (in_array($c['billing_type'] ?? 'none', ['free_of_charge','bill_to_actual']))
            continue;

        if ($c['frequency'] !== $freqName) continue;

        $quantity = (float)$c['quantity'];
        $cost = (float)$c['cost_per_unit'];

        $total = $quantity * $cost;

        if ($c['category'] === 'Supply') {
            $summary[$freqName]['Supply'] += $total;
        }

        if ($c['category'] === 'Tool') {
            $summary[$freqName]['Tool'] += $total;
        }
    }
}

$vatTotals = [];

$freqKeys = array_keys($frequenciesInContract);

foreach ($freqKeys as $i => $freq) {
    $runningTotal = 0;

    for ($j = 0; $j <= $i; $j++) {
        $f = $freqKeys[$j];
        $runningTotal +=
            $summary[$f]['Supply'] +
            $summary[$f]['Tool'];
    }
    $vatTotals[$freq] = $runningTotal * 1.12;
}

$selectedSite = $_GET['site'] ?? '';
?>

<link rel="stylesheet" href="/contract_system/assets/css/price_lists.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<div class="main-content">
    <div class="page-header">
        <h1>Contract Management</h1>
        <p class="page-subtitle">
            View, filter, and manage client service contracts.
            These contracts serve as the financial basis for SMRF, PR, and client billing forms.
        </p>
    </div>

    <div class="card">
        <div class="table-header-left">
            <div class="filter-add-group">
                <div class="filter-field">
                    <label for="filterField">Filter by Field:</label>
                    <select id="filterField">
                        <option value="">All Fields</option>
                        <?php foreach($allFields as $field): ?>
                            <option value="<?= htmlspecialchars($field) ?>" 
                                <?= $field == $defaultField ? 'selected' : '' ?>>
                                <?= htmlspecialchars($field) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-client">
                    <label for="filterClient">Filter by Project:</label>
                    <select id="filterClient">
                        <?php foreach($clientsInField as $client): ?>
                            <option value="<?= htmlspecialchars($client['id']) ?>"
                                <?= $client['id'] == $defaultClientId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-site">
                    <label for="filterSite">Filter by Site / Station:</label>
                    <select id="filterSite">
                        <option value="">All Sites</option>
                        <?php 
                        $allSites = array_unique(array_map(fn($c)=>$c['site'] ?: '-', $contracts));
                        foreach($allSites as $site): 
                        ?>
                            <option value="<?= htmlspecialchars($site) ?>"><?= htmlspecialchars($site) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button id="addContractBtn" class="btn-primary">Add Contract</button>
            </div>

            <?php if($defaultClient): 
                $clientFields = array_unique(array_map(fn($c)=>$c['field'], $contracts));
            ?>
            <div class="client-info-card">
                <div><strong>Project:</strong> <?= htmlspecialchars($defaultClient['full_name']) ?></div>
                <div><strong>Field:</strong> <?= htmlspecialchars(implode(', ', $clientFields)) ?></div>

                <button class="btn-view-smrf" data-client-id="<?= $defaultClient['id'] ?? 0 ?>">View SMRFs</button>
            </div>
            <?php endif; ?>

            <div class="budget-summary-card collapsed" id="budgetSummaryCard">
                <div class="budget-header" onclick="toggleBudgetCard()">
                    📊 Contract Budget Summary
                    <span class="toggle">▾</span>
                </div>

                <div class="budget-body">
                    <div class="budget-site-filter">
                        <label for="budgetSiteFilter">Site / Station</label>

                        <div class="budget-select-wrapper">
                            <select id="budgetSiteFilter" class="budget-select">
                                <?php if(count($availableSites) > 0): ?>
                                    <option value="">All Sites</option>

                                    <?php foreach($availableSites as $site): ?>
                                        <option value="<?= htmlspecialchars($site) ?>"
                                            <?= $selectedBudgetSite === $site ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($site) ?>
                                        </option>
                                    <?php endforeach; ?>

                                <?php else: ?>
                                    <option value="">No available sites/stations</option>
                                <?php endif; ?>
                            </select>

                            <span class="select-icon">▾</span>
                        </div>
                    </div>

                    <table class="budget-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <?php foreach($frequenciesInContract as $freq => $m): ?>
                                    <th><?= htmlspecialchars($freq) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Supplies & Consumables</strong></td>
                                <?php foreach($frequenciesInContract as $freq => $m): ?>
                                    <td>₱ <?= number_format($summary[$freq]['Supply'],2) ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Tools & Equipment</strong></td>
                                <?php foreach($frequenciesInContract as $freq => $m): ?>
                                    <td>₱ <?= number_format($summary[$freq]['Tool'],2) ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr class="vat-row">
                                <td><strong>Total Cost (with VAT)</strong></td>
                                <?php foreach($frequenciesInContract as $freq => $m): ?>
                                    <td><strong>₱ <?= number_format($vatTotals[$freq],2) ?></strong></td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php
        function renderContractsByCategory($contracts, $months_per_frequency, $selectedSite = '', $prefix = '') {
            $grouped = [];
            foreach ($contracts as $c) {
                $site = $c['site'] ?: '-';
                if ($selectedSite && $site !== $selectedSite) continue; 
                if (!isset($grouped[$site])) $grouped[$site] = [];
                $grouped[$site][] = $c;
            }

            foreach ($grouped as $siteName => $siteContracts):
                $totalCostPerMonth = 0;
                $grandTotal = 0;
                
        $collapseId = 'collapse_' . md5($siteName . uniqid());
        ?>
            <div class="site-info-card collapsible-header" data-target="<?= $prefix ?>-site-<?= md5($siteName) ?>">
                <div class="site-info-left">
                    <span class="toggle-icon">▾</span>
                    <span class="site-icon">📍</span>
                    <span class="site-text">
                        Site / Station: <strong><?= htmlspecialchars($siteName) ?></strong>
                    </span>
                </div>
            </div>
            
            <div id="<?= $prefix ?>-site-<?= md5($siteName) ?>" class="collapsible-content">
                <div class="contracts-table-wrapper">
                    <table class="contractsTable">
                        <thead>
                            <tr>
                                <th>Unit Code</th>
                                <th>Particulars</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Cost per Unit</th>
                                <th>Frequency</th>
                                <th>Cost per Month</th>
                                <th>Total Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siteContracts as $c):
                                $billing = $c['billing_type'] ?? 'none';

                                if ($billing === 'free_of_charge') {
                                    $dispCostPerUnit = 'Free of Charge';
                                    $dispFrequency = 'Free of Charge';
                                    $dispCostPerMonth = 'Free of Charge';
                                    $dispTotalCost = 'Free of Charge';
                                } elseif ($billing === 'bill_to_actual') {
                                    $dispCostPerUnit = 'Billed to Actual';
                                    $dispFrequency = 'Billed to Actual';
                                    $dispCostPerMonth = 'Billed to Actual';
                                    $dispTotalCost = 'Billed to Actual';
                                } else {
                                    $quantity = (float) $c['quantity'];
                                    $cost_per_unit = (float) $c['cost_per_unit'];
                                    $months = $months_per_frequency[$c['frequency']] ?? 1;

                                    $total_cost = $quantity * $cost_per_unit;
                                    $cost_per_month = $total_cost / $months;

                                    $dispCostPerUnit = '₱ ' . number_format($cost_per_unit, 2);
                                    $dispFrequency = htmlspecialchars($c['frequency']);
                                    $dispCostPerMonth = '₱ ' . number_format($cost_per_month, 2);
                                    $dispTotalCost = '₱ ' . number_format($total_cost, 2);

                                    $totalCostPerMonth += $cost_per_month;
                                    $vatAmount = $totalCostPerMonth * 0.12;
                                    $grandTotal = $totalCostPerMonth + $vatAmount;
                                }

                                $rowClass = '';
                                if ($billing === 'free_of_charge') $rowClass = 'billing-special free-of-charge';
                                elseif ($billing === 'bill_to_actual') $rowClass = 'billing-special bill-to-actual';
                            ?>
                                <tr data-client-id="<?= $c['user_id'] ?>" class="<?= $rowClass ?>">
                                    <td><?= htmlspecialchars($c['unit_no']) ?></td>
                                    <td><?= htmlspecialchars($c['particulars']) ?></td>
                                    <td style="text-align:center;"><?= isset($quantity) ? number_format($quantity,0) : '-' ?></td>
                                    <td style="text-align:center;"><?= htmlspecialchars($c['unit']) ?></td>
                                    <td style="text-align:right;"><?= $dispCostPerUnit ?></td>
                                    <td style="text-align:center;"><?= $dispFrequency ?></td>
                                    <td style="text-align:right; font-weight:600;"><?= $dispCostPerMonth ?></td>
                                    <td style="text-align:right; font-weight:600;"><?= $dispTotalCost ?></td>
                                    <td style="text-align:center;">
                                        <button class="btn-edit" data-contract='<?= htmlspecialchars(json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'>Edit</button>
                                        <button class="btn-delete" data-contract='<?= htmlspecialchars(json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'>Remove</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <div class="table-summary-container" style="margin-bottom:20px;">
                    <table class="table-summary">
                        <tr>
                            <td>Total (Cost per Month)</td>
                            <td>₱ <?= number_format($totalCostPerMonth, 2) ?></td>
                        </tr>
                        <tr>
                            <td>12% VAT</td>
                            <td>₱ <?= number_format($vatAmount, 2) ?></td>
                        </tr>
                        <tr class="grand-total-row">
                            <td>Grand Total</td>
                            <td>₱ <?= number_format($grandTotal, 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php
            endforeach;
        }
        ?>

        <div class="section-divider">
            <span>Supplies & Consumables</span>
        </div>
        <?php renderContractsByCategory($supplies, $months_per_frequency, $selectedSite, 'supplies'); ?>

        <div class="section-divider">
            <span>Tools & Equipment</span>
        </div>
        <?php renderContractsByCategory($tools, $months_per_frequency, $selectedSite, 'tools'); ?>
    </div>

    <div style="margin-bottom:15px; font-size:14px; color:#374151;">
        <strong>Total Items:</strong> <?= count($contracts); ?>
    </div>
    <div id="toastContainer" class="toast-container"></div>
</div>

<div id="addContractModal" class="custom-modal">
    <div class="modal-content modal-lg">
        <span class="close-btn" onclick="closeModal('addContractModal')">&times;</span>
        <h3>Add Contract Items</h3>

        <form id="addContractForm" class="contract-form">
            <div class="form-row" style="gap:20px;">
                <div class="form-group" style="max-width: 200px;">
                    <label>Project</label>
                    <select name="user_id" required>
                        <option value="">Select Project</option>
                        <?php foreach($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="max-width: 200px;">
                    <label>Field</label>
                    <select name="field" required>
                        <option value="">Select Field</option>
                        <option value="Housekeeping">Housekeeping</option>
                        <option value="Grounds & Landscape">Grounds & Landscape</option>
                    </select>
                </div>
            </div>

            <div class="contracts-modal-table">
                <table id="addContractsTable">
                    <thead>
                        <tr>
                            <th style="width:12%;">Unit Code</th>
                            <th style="width:22%;">Particulars</th>
                            <th style="width:10%;">Qty</th>
                            <th style="width:10%;">Unit</th>
                            <th style="width:16%;">Cost/Unit</th>
                            <th style="width:18%;">Frequency</th>
                            <th style="width:12%;">Category</th>
                            <th style="width:14%;">Billing Type</th>
                            <th style="width:14%;">Site / Station</th>
                            <th style="width:12%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" name="unit_no[]" required></td>
                            <td><input type="text" name="particulars[]" required></td>
                            <td><input type="number" step="1" min="1" name="quantity[]" required
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"></td>
                            <td><input type="text" name="unit[]" required></td>
                            <td><input type="number" step="0.01" name="cost_per_unit[]" required></td>
                            <td>
                                <select name="frequency[]" required>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Every 2 months">Every 2 months</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Semi-Annually">Semi-Annually</option>
                                    <option value="Annually">Annually</option>
                                    <option value="Every 1.5 years">Every 1.5 years</option>
                                    <option value="Every 2 years">Every 2 years</option>
                                    <option value="Every 3 years">Every 3 years</option>
                                    <option value="Every 4 years">Every 4 years</option>
                                </select>
                            </td>
                            <td>
                                <select name="category[]" required>
                                    <option value="Supply">Supply</option>
                                    <option value="Tool">Tool</option>
                                </select>
                            </td>
                            <td>
                                <select name="billing_type[]" class="billing-type-select" required>
                                    <option value="none">None</option>
                                    <option value="free_of_charge">Free of Charge</option>
                                    <option value="bill_to_actual">Bill to Actual</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="site[]" placeholder="Optional">
                            </td>
                            <td>
                                <button type="button" class="btn-remove-row">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="modal-table-actions">
                <button type="button" id="addRowBtn" class="btn-secondary">+ Add Row</button>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addContractModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Contracts</button>
            </div>
        </form>
    </div>
</div>

<div id="editContractModal" class="custom-modal">
    <div class="modal-content modal-lg edit-modal">

        <div class="modal-header">
            <h3>✏️ Edit Contract Item</h3>
        </div>

        <form id="editContractForm" class="contract-form">
            <input type="hidden" name="contract_id">

            <div class="edit-body">

                <div class="edit-info-box">
                    Modify the contract details below. Changes will affect financial computations,
                    billing basis, and monthly cost calculations.
                </div>

                <div class="edit-summary-card">
                    <div class="summary-row">
                        <span class="label">Project</span>
                        <select name="user_id" class="form-control" required>
                            <?php foreach($clients as $client): ?>
                                <option value="<?= $client['id'] ?>">
                                    <?= htmlspecialchars($client['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="summary-row">
                        <span class="label">Unit Code</span>
                        <input type="text" name="unit_no" class="form-control" required>
                    </div>

                    <div class="summary-row">
                        <span class="label">Particulars</span>
                        <input type="text" name="particulars" class="form-control" required>
                    </div>

                    <div class="summary-row">
                        <span class="label">Quantity</span>
                        <input type="number" step="1" min="1" name="quantity"
                               class="form-control" required
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>

                    <div class="summary-row">
                        <span class="label">Unit</span>
                        <input type="text" name="unit" class="form-control" required>
                    </div>

                    <div class="summary-row">
                        <span class="label">Cost per Unit</span>
                        <input type="number" step="0.01" name="cost_per_unit"
                               class="form-control" required>
                    </div>
                    <div class="summary-row">
                        <span class="label">Frequency</span>
                        <select name="frequency" class="form-control" required>
                            <option value="Monthly">Monthly</option>
                            <option value="Every 2 months">Every 2 months</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Semi-Annually">Semi-Annually</option>
                            <option value="Annually">Annually</option>
                            <option value="Every 1.5 years">Every 1.5 years</option>
                            <option value="Every 2 years">Every 2 years</option>
                            <option value="Every 3 years">Every 3 years</option>
                            <option value="Every 4 years">Every 4 years</option>
                        </select>
                    </div>
                    <div class="summary-row">
                        <span class="label">Category</span>
                        <select name="category" class="form-control" required>
                            <option value="Supply">Supply</option>
                            <option value="Tool">Tool</option>
                        </select>
                    </div>
                    <div class="summary-row">
                        <span class="label">Billing Type</span>
                        <select name="billing_type" class="form-control" required>
                            <option value="none">None</option>
                            <option value="free_of_charge">Free of Charge</option>
                            <option value="bill_to_actual">Bill to Actual</option>
                        </select>
                    </div>
                    <div class="summary-row">
                        <span class="label">Site / Station</span>
                        <input type="text" name="site" class="form-control">
                    </div>
                    <div class="summary-row highlight-row">
                        <span class="label">Cost per Month</span>
                        <input type="text" name="cost_per_month"
                               class="form-control readonly"
                               readonly>
                    </div>
                </div>
            </div>

            <div class="edit-footer">
                <button type="button" class="btn-cancel"
                        onclick="closeModal('editContractModal')">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    💾 Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<div id="deleteContractModal" class="custom-modal">
    <div class="modal-content modal-md delete-modal">

        <div class="delete-header">
            <h3>⚠️ Confirm Contract Removal</h3>
            <button class="modal-close" onclick="closeModal('deleteContractModal')">&times;</button>
        </div>

        <div class="delete-body">

            <div class="delete-warning-box">
                You are about to permanently remove this contract item.
                <br>
                This action cannot be undone.
            </div>

            <div class="delete-summary-card">

                <div class="summary-row">
                    <span class="label">Unit Code</span>
                    <span id="delete_unit_no"></span>
                </div>

                <div class="summary-row">
                    <span class="label">Particulars</span>
                    <span id="delete_particulars"></span>
                </div>

                <div class="summary-row">
                    <span class="label">Quantity</span>
                    <span id="delete_quantity"></span>
                </div>

                <div class="summary-row">
                    <span class="label">Unit</span>
                    <span id="delete_unit"></span>
                </div>

                <div class="summary-row">
                    <span class="label">Cost per Unit</span>
                    <span id="delete_cost"></span>
                </div>

                <div class="summary-row">
                    <span class="label">Frequency</span>
                    <span id="delete_frequency"></span>
                </div>

                <div class="summary-row">
                    <span class="label">Category</span>
                    <span id="delete_category"></span>
                </div>

            </div>

        </div>

        <div class="delete-footer">
            <button class="btn-cancel" onclick="closeModal('deleteContractModal')">
                Cancel
            </button>
            <button id="confirmDeleteBtn" class="btn-danger">
                Yes, Remove Contract Item
            </button>
        </div>

    </div>
</div>

<div id="viewSmrfModal" class="custom-modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>
                📄 Submitted SMRF Files
                <span class="modal-subtitle">Select an SMRF to view its detailed item breakdown</span>
            </h3>
        </div>
        <div class="modal-body">
            <div class="smrf-table-container">
                <table id="smrfTable">
                    <thead>
                        <tr>
                            <th>SMRF ID</th>
                            <th>Reference ID</th>
                            <th>Date Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('viewSmrfModal')">Close</button>
        </div>
    </div>
</div>

<div id="smrfItemsModal" class="custom-modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <div class="modal-header-top">
                <h3>
                    📦 SMRF Items
                    <span class="modal-subtitle">Detailed list of submitted materials and costs</span>
                </h3>
            </div>

            <div class="modal-header-info">
                <div>
                    <strong>SMRF ID:</strong>
                    <span id="modalSmrfId">-</span>
                </div>
                <div>
                    <strong>Reference ID:</strong>
                    <span id="modalReferenceId">-</span>
                </div>
                <div>
                    <strong>Project:</strong>
                    <span id="modalProject">-</span>
                </div>
                <div>
                    <strong>Project Code:</strong>
                    <span id="modalProjectCode">-</span>
                </div>
                <div>
                    <strong>Period:</strong>
                    <span id="modalPeriod">-</span>
                </div>
                <div>
                    <strong>Status:</strong>
                    <span id="modalStatus">-</span>
                </div>
                <div>
                    <strong>Created By:</strong>
                    <span id="modalCreatedBy">-</span>
                </div>
                <div>
                    <strong>Created At:</strong>
                    <span id="modalCreatedAt">-</span>
                </div>
            </div>
        </div>
        <div class="modal-body">
            <div class="smrf-summary-card">
                <div class="summary-item">
                    <span class="summary-label">Total Supplies (SC)</span>
                    <span class="summary-value" id="totalSmrfSupplies">₱ 0.00</span>
                </div>

                <div class="summary-item">
                    <span class="summary-label">Total Tools (TE)</span>
                    <span class="summary-value" id="totalSmrfTools">₱ 0.00</span>
                </div>

                <div class="summary-divider"></div>
                
                <div class="summary-item total-budget">
                    <span class="summary-label">Total Monthly Budget (from Contracts)</span>
                    <span class="summary-value" id="totalMonthlyBudget">
                        ₱ <?= number_format($monthlyBudgetTotal, 2) ?>
                    </span>
                </div>

                <div class="summary-item total-amount">
                    <span class="summary-label">Total Amount (No VAT)</span>
                    <span class="summary-value" id="totalSmrfNoVat">₱ 0.00</span>
                </div>

                <div class="summary-item vat">
                    <span class="summary-label">Total Amount with 12% VAT</span>
                    <span class="summary-value" id="totalSmrfWithVat">₱ 0.00</span>
                </div>
            </div>
            <div class="smrf-items-table-container">
                <table id="smrfItemsTable">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Description</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Unit Cost</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('smrfItemsModal')">Close</button>
        </div>
    </div>
</div>

<script>
document.getElementById('filterSite').addEventListener('change', function() {
    const site = this.value;
    const params = new URLSearchParams(window.location.search);

    if (site) {
        params.set('site', site);
    } else {
        params.delete('site');
    }

    window.location.search = params.toString();
});

const projectFields = <?= json_encode(array_reduce($clients, function($carry, $client) use ($allContracts) {
    $fields = [];
    foreach($allContracts as $c) {
        if($c['user_id'] == $client['id']) $fields[] = $c['field'];
    }
    $carry[$client['id']] = array_values(array_unique($fields));
    return $carry;
}, [])) ?>;
</script>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.classList.add('toast');

    if (type === 'success') toast.classList.add('toast-success');
    else if (type === 'error') toast.classList.add('toast-error');
    else if (type === 'warning') toast.classList.add('toast-warning');

    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3600);
}

const addContractBtn = document.getElementById('addContractBtn');
const addContractModal = document.getElementById('addContractModal');
const addContractForm = document.getElementById('addContractForm');

addContractBtn.addEventListener('click', () => addContractModal.style.display = 'flex');
function closeModal(id) { document.getElementById(id).style.display='none'; }

const addRowBtn = document.getElementById('addRowBtn');
const addContractsTable = document.getElementById('addContractsTable').querySelector('tbody');

addRowBtn.addEventListener('click', function() {
    const firstRow = addContractsTable.querySelector('tr');
    const newRow = firstRow.cloneNode(true);

    newRow.querySelectorAll('input').forEach(input => input.value = '');
    newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);

    addContractsTable.appendChild(newRow);
});

addContractsTable.addEventListener('click', function(e) {
    if(e.target.classList.contains('btn-remove-row')){
        const rowCount = addContractsTable.querySelectorAll('tr').length;
        if(rowCount > 1) e.target.closest('tr').remove();
        else showToast('At least one row is required', 'warning');
    }
});

addContractForm.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);

    fetch('add_contract.php', { method:'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            closeModal('addContractModal');
            showToast('Contracts added successfully!', 'success');
            setTimeout(()=>{ location.reload(); }, 1000);
        } else {
            showToast('Error: '+data.message, 'error');
        }
    })
    .catch(err => showToast('Network error.', 'error'));
});

const filterClient = document.getElementById('filterClient');
const filterField = document.getElementById('filterField');

function updateFilters() {
    const clientId = filterClient.value;
    const field = filterField.value;
    let url = '?';
    if(field) url += 'field=' + encodeURIComponent(field) + '&';
    if(clientId) url += 'client_id=' + encodeURIComponent(clientId);
    window.location.href = url;
}

filterClient.addEventListener('change', updateFilters);
filterField.addEventListener('change', updateFilters);

const editForm = document.getElementById('editContractForm');
const editCostInput = editForm.cost_per_month;

const monthsPerFrequency = {
    "Monthly": 1,
    "Every 2 months": 2,
    "Quarterly": 3,
    "Semi-Annually": 6,
    "Annually": 12,
    "Every 1.5 years": 18,
    "Every 2 years": 24,
    "Every 3 years": 36,
    "Every 4 years": 48
};

function updateEditCostPerMonth() {
    const billing = editForm.billing_type.value;

    if (billing === 'free_of_charge') {
        editCostInput.value = 'Free of Charge';
        return;
    }

    if (billing === 'bill_to_actual') {
        editCostInput.value = 'Billed to Actual';
        return;
    }

    const quantity = parseFloat(editForm.quantity.value) || 0;
    const cost = parseFloat(editForm.cost_per_unit.value) || 0;
    const freq = editForm.frequency.value;
    const months = monthsPerFrequency[freq] || 1;

    const costPerMonth = (quantity * cost) / months;
    editCostInput.value = costPerMonth.toFixed(2);
}

function handleBillingTypeEdit() {
    const billing = editForm.billing_type.value;

    const qty = editForm.quantity;
    const cost = editForm.cost_per_unit;
    const freq = editForm.frequency;

    if (billing === 'none') {
        qty.readOnly = false;
        cost.readOnly = false;
        freq.style.pointerEvents = 'auto';
        freq.style.opacity = '1';
    } else {
        qty.readOnly = true;
        cost.readOnly = true;
        freq.style.pointerEvents = 'none';
        freq.style.opacity = '0.6';
    }

    updateEditCostPerMonth();
}

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {

        const data = JSON.parse(this.dataset.contract);

        editForm.contract_id.value = data.id;
        editForm.user_id.value = data.user_id;
        editForm.unit_no.value = data.unit_no;
        editForm.particulars.value = data.particulars;
        editForm.quantity.value = data.quantity;
        editForm.unit.value = data.unit;
        editForm.cost_per_unit.value = data.cost_per_unit;
        editForm.frequency.value = data.frequency;
        editForm.category.value = data.category;
        editForm.billing_type.value = data.billing_type || 'none';
        editForm.site.value = data.site || ''; 

        handleBillingTypeEdit();

        document.getElementById('editContractModal').style.display = 'flex';
    });
});

['quantity','cost_per_unit','frequency','billing_type','site'].forEach(name => {
    editForm[name].addEventListener('input', () => {
        handleBillingTypeEdit();
    });
});

editForm.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);

    fetch('edit_contract.php', { method:'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            closeModal('editContractModal');
            showToast('Contract updated successfully!', 'success');
            setTimeout(()=>{ location.reload(); }, 800);
        } else {
            showToast('Error: '+data.message, 'error');
        }
    })
    .catch(err => showToast('Network error.', 'error'));
});

const deleteModal = document.getElementById('deleteContractModal');
const deleteForm = document.getElementById('deleteContractForm');

let contractToDelete = null;

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function() {

        const data = JSON.parse(this.dataset.contract);
        contractToDelete = data.id;

        document.getElementById('delete_unit_no').textContent = data.unit_no;
        document.getElementById('delete_particulars').textContent = data.particulars;
        document.getElementById('delete_quantity').textContent = data.quantity;
        document.getElementById('delete_unit').textContent = data.unit;
        document.getElementById('delete_cost').textContent =
            '₱ ' + parseFloat(data.cost_per_unit).toFixed(2);
        document.getElementById('delete_frequency').textContent = data.frequency;
        document.getElementById('delete_category').textContent = data.category;

        document.getElementById('deleteContractModal').style.display = 'flex';
    });
});

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {

    if (!contractToDelete) return;

    const formData = new FormData();
    formData.append('contract_id', contractToDelete);

    fetch('delete_contract.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('deleteContractModal');
            showToast('Contract removed successfully!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(() => {
        showToast('Network error while deleting.', 'error');
    });
});

document.addEventListener('click', function(e) {

    if (e.target.classList.contains('btn-view-smrf')) {
        const clientId = e.target.dataset.clientId;

        fetch('get_smrf.php?client_id=' + clientId)
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#smrfTable tbody');
                tbody.innerHTML = '';

                data.forEach(smrf => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${smrf.id}</td>
                        <td>${smrf.reference_id ?? '-'}</td>
                        <td>${smrf.created_at}</td>
                        <td>
                            <button class="btn-view-items"
                                data-smrf-id="${smrf.id}"
                                data-reference-id="${smrf.reference_id ?? ''}"
                                data-project="${smrf.project ?? ''}"
                                data-project-code="${smrf.project_code ?? ''}"
                                data-period="${smrf.period ?? ''}"
                                data-status="${smrf.status ?? ''}"
                                data-created-at="${smrf.created_at ?? ''}"
                                data-created-by="${smrf.created_by_name ?? ''}">
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                const viewModal = document.getElementById('viewSmrfModal');
                viewModal.style.display = 'flex';
                viewModal.classList.remove('side-left');
            })
            .catch(() => showToast('Failed to load SMRFs.', 'error'));
    }

    if (e.target.classList.contains('btn-view-items')) {
        const smrfTableRows = document.querySelectorAll('#smrfTable tbody tr');
        smrfTableRows.forEach(r => r.classList.remove('smrf-selected-row'));

        const currentRow = e.target.closest('tr');
        if (currentRow) currentRow.classList.add('smrf-selected-row');

        const smrfId = e.target.dataset.smrfId;
        const referenceId = e.target.dataset.referenceId;
        const project = e.target.dataset.project;
        const projectCode = e.target.dataset.projectCode;
        const period = e.target.dataset.period;
        const status = e.target.dataset.status;
        const createdBy = e.target.dataset.createdBy;
        const createdAt = e.target.dataset.createdAt;
        const clientId = e.target.dataset.clientId;

        const itemsModal = document.getElementById('smrfItemsModal');
        const viewModal = document.getElementById('viewSmrfModal');

        viewModal.classList.add('side-left');
        if (itemsModal.style.display !== 'flex') {
            itemsModal.style.display = 'flex';
            itemsModal.classList.add('side-right');
        }

        document.getElementById('modalSmrfId').textContent = smrfId ?? '-';
        document.getElementById('modalReferenceId').textContent = referenceId ?? '-';
        document.getElementById('modalProject').textContent = project || '-';
        document.getElementById('modalProjectCode').textContent = projectCode || '-';
        document.getElementById('modalPeriod').textContent = period || '-';
        document.getElementById('modalStatus').textContent = status || '-';
        document.getElementById('modalCreatedBy').textContent = createdBy || '-';
        document.getElementById('modalCreatedAt').textContent = createdAt || '-';

        const itemsTbody = document.querySelector('#smrfItemsTable tbody');
        itemsTbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center;">Loading items...</td>
            </tr>
        `;

        if (!window.currentClientId || window.currentClientId !== clientId) {
            window.currentClientId = clientId;
            window.cumulativeQtyMap = {};
        }
        const cumulativeQty = window.cumulativeQtyMap;

        fetch('get_smrf_items.php?smrf_id=' + smrfId)
            .then(res => res.json())
            .then(items => {
                itemsTbody.innerHTML = '';

                let totalSupplies = 0;
                let totalTools = 0;

                if (!items.length) {
                    document.getElementById('totalSmrfSupplies').textContent = '₱ 0.00';
                    document.getElementById('totalSmrfTools').textContent = '₱ 0.00';
                    document.getElementById('totalSmrfNoVat').textContent = '₱ 0.00';
                    document.getElementById('totalSmrfWithVat').textContent = '₱ 0.00';

                    itemsTbody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align:center;">No items found.</td>
                        </tr>
                    `;
                    return;
                }

                items.forEach(it => {
                    const tr = document.createElement('tr');

                    const amount = parseFloat(it.amount || (it.quantity * it.unit_cost)) || 0;
                    const legend = (it.legend || '').toLowerCase();

                    if (legend === 'sc') {
                        totalSupplies += amount;
                    } else if (legend === 'te') {
                        totalTools += amount;
                    }

                    cumulativeQty[it.item_code] = (cumulativeQty[it.item_code] || 0) + it.quantity;

                    let rowClass = '';
                    let quantitySubtext = '';

                    if (parseInt(it.is_in_contract) === 0) {
                        rowClass = 'smrf-mismatch-row'; 
                    } else if (cumulativeQty[it.item_code] > (it.contract_quantity || 0)) {
                        rowClass = 'smrf-exceed-row';
                        quantitySubtext = '<div style="color:#b45309; font-weight:700; font-size:0.75em; margin-top:2px;">⚠ Exceeds quantity request</div>';
                    }

                    if (rowClass) tr.classList.add(rowClass);

                    tr.innerHTML = `
                        <td>
                            ${it.item_code ?? '-'}
                            ${parseInt(it.is_in_contract) === 0 ? 
                                '<div style="color:#dc2626; font-weight:700; font-size:0.75em; margin-top:2px;">⚠ Not in Contract</div>' 
                                : ''}
                        </td>
                        <td>${it.item_description ?? '-'}</td>
                        <td>
                            ${it.quantity}
                            ${quantitySubtext}
                        </td>
                        <td>${it.unit}</td>
                        <td style="text-align: left;">₱ ${parseFloat(it.unit_cost || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td style="text-align: left;">₱ ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    `;
                    itemsTbody.appendChild(tr);
                });

                const totalNoVat = totalSupplies + totalTools;
                const vat = totalNoVat * 0.12;
                const totalWithVat = totalNoVat + vat;

                document.getElementById('totalSmrfSupplies').textContent = `₱ ${totalSupplies.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                document.getElementById('totalSmrfTools').textContent = `₱ ${totalTools.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                document.getElementById('totalSmrfNoVat').textContent = `₱ ${totalNoVat.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                document.getElementById('totalSmrfWithVat').textContent = `₱ ${totalWithVat.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            })
            .catch(() => {
                itemsTbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align:center; color:red;">Failed to load items.</td>
                    </tr>
                `;
            });
    }
});

document.querySelectorAll('#smrfItemsModal .modal-close, #smrfItemsModal .btn-cancel').forEach(btn => {
    btn.addEventListener('click', () => {
        const itemsModal = document.getElementById('smrfItemsModal');
        itemsModal.style.display = 'none';
        itemsModal.classList.remove('side-right');

        const viewModal = document.getElementById('viewSmrfModal');
        viewModal.classList.remove('side-left');
    });
});

const projectSelect = addContractForm.querySelector('select[name="user_id"]');
const fieldSelect = addContractForm.querySelector('select[name="field"]');

projectSelect.addEventListener('change', () => {
    const clientId = projectSelect.value;
    const fields = projectFields[clientId] || [];

    fieldSelect.innerHTML = '';

    if(fields.length === 0) {
        fieldSelect.innerHTML = `
            <option value="">Select Field</option>
            <option value="Housekeeping">Housekeeping</option>
            <option value="Grounds & Landscape">Grounds & Landscape</option>
        `;
        fieldSelect.value = '';
    } else if(fields.length === 1) {
        fieldSelect.innerHTML = `<option value="${fields[0]}">${fields[0]}</option>`;
        fieldSelect.value = fields[0];
    } else {
        fieldSelect.innerHTML = `<option value="">Select Field</option>`;
        fields.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f;
            opt.textContent = f;
            fieldSelect.appendChild(opt);
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll('.collapsible-header').forEach(header => {

        const targetId = header.dataset.target;
        const content = document.getElementById(targetId);

        const savedState = localStorage.getItem("collapse_" + targetId);

        if (savedState === "collapsed") {
            content.style.display = "none";
            header.classList.add("collapsed");
        } else {
            content.style.display = "block";
            header.classList.remove("collapsed");
        }

        header.addEventListener('click', function () {

            const isCollapsed = header.classList.contains('collapsed');

            if (isCollapsed) {
                content.style.display = "block";
                header.classList.remove('collapsed');
                localStorage.setItem("collapse_" + targetId, "expanded");
            } else {
                content.style.display = "none";
                header.classList.add('collapsed');
                localStorage.setItem("collapse_" + targetId, "collapsed");
            }
        });

    });

});

function toggleBudgetCard() {
    const card = document.getElementById('budgetSummaryCard');
    card.classList.toggle('collapsed');
    card.classList.toggle('expanded');
}

document.getElementById('budgetSiteFilter')?.addEventListener('change', function(){

    const site = this.value;
    const params = new URLSearchParams(window.location.search);

    if(site){
        params.set('budget_site', site);
    }else{
        params.delete('budget_site');
    }

    window.location.search = params.toString();
});
</script>

<?php require_once "../../layouts/footer.php"; ?>