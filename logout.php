<?php
/**
 * Admin Logout
 */

session_start();

// Log the logout action if admin was logged in
if (isset($_SESSION['admin_id'])) {
    require_once '../db_connect.php';
    
    $stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action, description, ip_address) VALUES (?, 'admin_logout', 'Admin logged out', ?)");
    $stmt->bind_param("is", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php?logged_out=1");
exit;
?>