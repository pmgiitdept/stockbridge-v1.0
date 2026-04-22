<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";
require_once "../../core/audit.php";

authorize(['president','operations_manager']);

$userId = $_SESSION['user_id'] ?? null;

try {

    $conn->begin_transaction();

    // 1. CLEAR OLD SUMMARY (safe rebuild)
    $conn->query("TRUNCATE TABLE inventory_monthly_summary");

    // 2. GET ALL UNIQUE ITEMS (from movements + PR)
    $itemsSql = "
        SELECT DISTINCT item_code, item_name, category
        FROM inventory_movements

        UNION

        SELECT 
            '' AS item_code,
            pi.item_description AS item_name,
            'Supply' AS category
        FROM pr_items pi
    ";

    $itemsRes = $conn->query($itemsSql);

    if (!$itemsRes) {
        throw new Exception($conn->error);
    }

    // 3. GET ALL MONTHS (from both sources)
    $monthsSql = "
        SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS month
        FROM inventory_movements

        UNION

        SELECT DISTINCT DATE_FORMAT(approved_at, '%Y-%m') AS month
        FROM pr_forms
        WHERE approved_at IS NOT NULL

        ORDER BY month ASC
    ";

    $monthsRes = $conn->query($monthsSql);

    if (!$monthsRes) {
        throw new Exception($conn->error);
    }

    $months = [];
    while ($m = $monthsRes->fetch_assoc()) {
        $months[] = $m['month'];
    }

    // 4. PREPARE INSERT
    $insertStmt = $conn->prepare("
        INSERT INTO inventory_monthly_summary
        (item_code, item_name, category, month, total_in, total_out, ending_balance)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        throw new Exception($conn->error);
    }

    // 5. PROCESS EACH ITEM
    while ($item = $itemsRes->fetch_assoc()) {

        $itemCode = $item['item_code'];
        $itemName = $item['item_name'];
        $category = $item['category'];

        $runningBalance = 0;

        foreach ($months as $month) {

            // IN from inventory
            $inSql = "
                SELECT SUM(quantity) as total_in
                FROM inventory_movements
                WHERE movement_type='IN'
                AND DATE_FORMAT(created_at,'%Y-%m') = ?
                AND item_name = ?
            ";

            $stmtIn = $conn->prepare($inSql);
            $stmtIn->bind_param("ss", $month, $itemName);
            $stmtIn->execute();
            $inRes = $stmtIn->get_result()->fetch_assoc();
            $totalIn = $inRes['total_in'] ?? 0;

            // OUT from inventory
            $outSql = "
                SELECT SUM(quantity) as total_out
                FROM inventory_movements
                WHERE movement_type='OUT'
                AND DATE_FORMAT(created_at,'%Y-%m') = ?
                AND item_name = ?
            ";

            $stmtOut = $conn->prepare($outSql);
            $stmtOut->bind_param("ss", $month, $itemName);
            $stmtOut->execute();
            $outRes = $stmtOut->get_result()->fetch_assoc();
            $totalOutInventory = $outRes['total_out'] ?? 0;

            // OUT from PR
            $prSql = "
                SELECT SUM(pi.quantity) as total_pr_out
                FROM pr_items pi
                JOIN pr_forms pf ON pi.pr_id = pf.pr_id
                WHERE pf.approved_at IS NOT NULL
                AND DATE_FORMAT(pf.approved_at,'%Y-%m') = ?
                AND pi.item_description = ?
            ";

            $stmtPr = $conn->prepare($prSql);
            $stmtPr->bind_param("ss", $month, $itemName);
            $stmtPr->execute();
            $prRes = $stmtPr->get_result()->fetch_assoc();
            $totalOutPR = $prRes['total_pr_out'] ?? 0;

            $totalOut = $totalOutInventory + $totalOutPR;

            // RUNNING BALANCE
            $runningBalance += ($totalIn - $totalOut);

            // INSERT ROW
            $insertStmt->bind_param(
                "sssssdd",
                $itemCode,
                $itemName,
                $category,
                $month,
                $totalIn,
                $totalOut,
                $runningBalance
            );

            $insertStmt->execute();
        }
    }

    $insertStmt->close();

    // LOG
    logAudit(
        $conn,
        $userId,
        "Rebuilt monthly inventory summary",
        "Inventory - Monthly Summary"
    );

    $conn->commit();

    echo "Monthly inventory summary successfully rebuilt.";

} catch (Exception $e) {

    $conn->rollback();
    echo "Error: " . $e->getMessage();
}