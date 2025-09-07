<?php
/**
 * AJAX endpoint to get user data for editing
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../../db_connect.php';

$userId = (int)($_GET['id'] ?? 0);

if (!$userId) {
    http_response_code(400);
    exit('Invalid user ID');
}

$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, subscription_type, plan_type, 
           subscription_expiry, is_active, created_at
    FROM users 
    WHERE id = ?
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    header('Content-Type: application/json');
    echo json_encode($user);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
}

$stmt->close();
?>