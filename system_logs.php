<?php
/**
 * Admin - System Logs
 * File: admin/system_logs.php
 */
session_start();

// ✅ Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../db_connect.php';

// Handle log actions
$message = '';
$messageType = '';

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'clear_old_logs':
            $daysToKeep = (int)($_POST['days_to_keep'] ?? 30);
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
            
            $stmt = $conn->prepare("DELETE FROM system_logs WHERE created_at < ?");
            $stmt->bind_param("s", $cutoffDate);
            
            if ($stmt->execute()) {
                $deletedRows = $conn->affected_rows;
                $message = "Deleted {$deletedRows} old log entries (older than {$daysToKeep} days).";
                $messageType = "success";
            } else {
                $message = "Error clearing old logs.";
                $messageType = "error";
            }
            $stmt->close();
            break;
            
        case 'clear_all_logs':
            if ($conn->query("DELETE FROM system_logs")) {
                $message = "All system logs cleared successfully.";
                $messageType = "success";
            } else {
                $message = "Error clearing all logs.";
                $messageType = "error";
            }
            break;
    }
}
