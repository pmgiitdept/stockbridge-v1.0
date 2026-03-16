<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['purchasing_officer']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
?>

<div class="main-content">
    <h1>Purchase Requests</h1>
    <p>All approved purchase requests from the President will appear here. This section will be functional once the workflow for client → operations officer → operations manager → president is implemented.</p>

    <div class="content-grid">
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Requested By</th>
                            <th>Project</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" style="text-align:center;">No purchase requests yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once "../../layouts/footer.php"; ?>
