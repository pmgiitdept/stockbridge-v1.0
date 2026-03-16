<?php
require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

// Only allow operations_officer access
authorize(['operations_officer']);
?>

<div class="main-content">
    <div class="page-header">
        <h1><?= basename(__FILE__, '.php') ?></h1>
        <p class="page-subtitle">This page is under construction.</p>
    </div>
    <div class="card">
        <p style="text-align:center; padding:30px;">Coming Soon...</p>
    </div>
</div>

<?php require_once "../../layouts/footer.php"; ?>