<?php
require_once "../../config/database.php";
require_once "../../core/session.php";
require_once "../../core/auth.php";

authorize(['purchasing_officer', 'president', 'admin', 'operations_manager']);

require_once "../../layouts/header.php";
require_once "../../layouts/sidebar.php";
?>

<div class="main-content">
    <h1>Forms Status</h1>
    <p>This section will display the status of all forms from different categories once the workflow is fully implemented.</p>
    <div class="content-grid">
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Form ID</th>
                            <th>Category</th>
                            <th>Requested By</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" style="text-align:center;">No form data available yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once "../../layouts/footer.php"; ?>
