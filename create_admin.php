<?php
// create_admin.php - Run this once to create your admin account
require_once '../db_connect.php';

$username = 'admin1';
$password = 'password';
$first_name = 'Admin';
$last_name = 'User';
$email = 'admin@test.com';

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash, first_name, last_name, email, role) VALUES (?, ?, ?, ?, ?, 'super_admin')");
$stmt->bind_param("sssss", $username, $password_hash, $first_name, $last_name, $email);

if ($stmt->execute()) {
    echo "Admin user created successfully!";
} else {
    echo "Error creating admin user: " . $conn->error;
}
?>