<?php
require 'config.php'; // database connection

$username = "Rahman";   // change this
$full_name = "Rahman"; // change this
$password = password_hash("student123", PASSWORD_DEFAULT); // change password

$stmt = $pdo->prepare("
    INSERT INTO users (username, password, role, display_name)
    VALUES (?, ?, 'student', ?)
");

$stmt->execute([$username, $password, $full_name]);

echo "New admin created successfully!";
?>