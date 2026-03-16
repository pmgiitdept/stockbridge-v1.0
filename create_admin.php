<?php
require_once "config/database.php";

$password = password_hash("admin123", PASSWORD_DEFAULT);

$query = "INSERT INTO users 
(full_name, email, password, role) 
VALUES 
('System Administrator', 'admin@system.com', '$password', 'admin')";

mysqli_query($conn, $query);

echo "Admin created!";
?>