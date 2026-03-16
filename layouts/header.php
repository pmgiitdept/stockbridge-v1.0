<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../config/database.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Stockbridge CMS</title>
    <link rel="stylesheet" href="/contract_system/assets/css/style.css">
    <link rel="stylesheet" href="/contract_system/assets/css/global.css">
    <link rel="icon" href="/contract_system/assets/images/stockbridge-logo-title.PNG">
</head>
<body>
<div id="page-loader">
    <div class="loader-wrapper">
        <div class="loader"></div>
        <div class="loader-text">Loading<span class="dots"></span></div>
    </div>
</div>