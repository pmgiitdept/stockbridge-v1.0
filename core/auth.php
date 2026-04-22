<?php
require_once __DIR__ . "/session.php";
require_once __DIR__ . "/audit.php";

/**
 * RBAC Access Checker
 *
 * @param array $allowed_roles
 */
function authorize(array $allowed_roles)   
{
    if (!isset($_SESSION['role'])) {
        header("Location: /contract_system/login.php");
        exit();
    }

    if (!in_array($_SESSION['role'], $allowed_roles)) {

        // Log unauthorized access
        logAudit(
            $GLOBALS['conn'],
            $_SESSION['user_id'],
            "Unauthorized access attempt",
            $_SERVER['REQUEST_URI']
        );

        header("Location: /contract_system/modules/dashboard/index.php");
        exit();
    }
}
