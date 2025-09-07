<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// admin/support.php - Admin panel for managing support tickets
session_start();
// Use the main admin authentication check, consistent with other admin pages
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin'])) {
    // If not logged in, redirect to the main admin login page
    header("Location: login.php");
    exit;
}

require_once '../db_connect.php';
require_once '../email_config.php';

$adminName = $_SESSION['admin_name'] ?? 'Support Admin';
$adminEmail = $_SESSION['admin_email'] ?? 'support@freereminders.net';

// Handle admin reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_reply'])) {
    $ticketId = (int)$_POST['ticket_id'];
    $replyMessage = trim($_POST['reply_message']);
    $isInternal = isset($_POST['is_internal']) ? 1 : 0;
    $newStatus = $_POST['status'] ?? '';
    
    if (!empty($replyMessage) && $ticketId) {
        // Get ticket info for email notification
        $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($ticket) {
            // Insert admin reply
            $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_name, sender_email, message, is_internal) VALUES (?, 'admin', ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $ticketId, $adminName, $adminEmail, $replyMessage, $isInternal);
            
            if ($stmt->execute()) {
                // Update ticket status if provided
                if ($newStatus && in_array($newStatus, ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])) {
                    $updateStmt = $conn->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->bind_param("si", $newStatus, $ticketId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // Set closed_at if status is closed
                    if ($newStatus === 'closed') {
                        $closeStmt = $conn->prepare("UPDATE support_tickets SET closed_at = NOW() WHERE id = ?");
                        $closeStmt->bind_param("i", $ticketId);
                        $closeStmt->execute();
                        $closeStmt->close();
                    }
                } else {
                    // Default status update
                    $defaultStatus = $isInternal ? $ticket['status'] : 'waiting_customer';
                    $updateStmt = $conn->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->bind_param("si", $defaultStatus, $ticketId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                
                // Send email notification to customer (only for non-internal messages)
                if (!$isInternal) {
                    $subject = "Support Update - Ticket #$ticketId: " . $ticket['subject'];
                    $message = "
                    <html>
                    <head><title>Support Ticket Update</title></head>
                    <body>
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                            <div style='background-color: #0066CC; color: white; padding: 20px; text-align: center;'>
                                <h1>FreeReminders.net Support</h1>
                            </div>
                            <div style='padding: 20px; background-color: #f9f9f9;'>
                                <h2>Update on Your Support Ticket</h2>
                                <p>Dear " . htmlspecialchars($ticket['name']) . ",</p>
                                <p>Our support team has responded to your ticket <strong>#$ticketId</strong>.</p>
                                
                                <div style='background-color: white; padding: 15px; border-left: 4px solid #FF6600; margin: 20px 0;'>
                                    <h3>Support Team Response:</h3>
                                    <p>" . nl2br(htmlspecialchars($replyMessage)) . "</p>
                                </div>
                                
                                <div style='background-color: white; padding: 15px; border-left: 4px solid #0066CC; margin: 20px 0;'>
                                    <h3>Ticket Details:</h3>
                                    <p><strong>Ticket ID:</strong> #$ticketId</p>
                                    <p><strong>Subject:</strong> " . htmlspecialchars($ticket['subject']) . "</p>
                                    <p><strong>Status:</strong> " . ucfirst(str_replace('_', ' ', $newStatus ?: $ticket['status'])) . "</p>
                                </div>
                                
                                <p>You can view and reply to this ticket by logging into your account:</p>
                                <div style='text-align: center; margin: 20px 0;'>
                                    <a href='http://" . $_SERVER['HTTP_HOST'] . "/support/my_tickets.php' 
                                       style='background-color: #0066CC; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                                       View My Tickets
                                    </a>
                                </div>
                                
                                <p>Thank you for using FreeReminders.net!</p>
                                
                                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;'>
                                    <p>This is an automated message. You can reply to this email or log into your account to continue the conversation.</p>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    send_email($ticket['email'], $subject, $message);
                }
                
                $_SESSION['admin_message'] = "Reply sent successfully!";
                $_SESSION['admin_message_type'] = "success";
            }
            $stmt->close();
        }
    }
    
    header("Location: support.php" . (isset($_GET['ticket']) ? "?ticket=" . $_GET['ticket'] : ""));
    exit;
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_tickets'])) {
    $action = $_POST['bulk_action'];
    $ticketIds = array_map('intval', $_POST['selected_tickets']);
    
    if (!empty($ticketIds)) {
        $placeholders = str_repeat('?,', count($ticketIds) - 1) . '?';
        $types = str_repeat('i', count($ticketIds));
        
        switch ($action) {
            case 'close':
                $stmt = $conn->prepare("UPDATE support_tickets SET status = 'closed', closed_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ticketIds);
                $stmt->execute();
                $_SESSION['admin_message'] = count($ticketIds) . " tickets closed successfully.";
                break;
                
            case 'reopen':
                $stmt = $conn->prepare("UPDATE support_tickets SET status = 'open', closed_at = NULL, updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ticketIds);
                $stmt->execute();
                $_SESSION['admin_message'] = count($ticketIds) . " tickets reopened successfully.";
                break;
                
            case 'mark_resolved':
                $stmt = $conn->prepare("UPDATE support_tickets SET status = 'resolved', updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ticketIds);
                $stmt->execute();
                $_SESSION['admin_message'] = count($ticketIds) . " tickets marked as resolved.";
                break;
        }
        if (isset($stmt)) {
            $stmt->close();
        }
        $_SESSION['admin_message_type'] = "success";
    }
    
    header("Location: support.php");
    exit;
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($statusFilter) {
    $whereClause .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($priorityFilter) {
    $whereClause .= " AND priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}

if ($categoryFilter) {
    $whereClause .= " AND category = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}

if ($searchQuery) {
    $whereClause .= " AND (subject LIKE ? OR name LIKE ? OR email LIKE ? OR id = ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchQuery; // For exact ID match
    $types .= "sssi";
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM support_tickets $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalTickets = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($totalTickets / $perPage);

// Get tickets for current page
$sql = "
    SELECT 
        t.*,
        COUNT(tm.id) as message_count,
        MAX(tm.created_at) as last_reply_at,
        (SELECT tm2.sender_type FROM ticket_messages tm2 WHERE tm2.ticket_id = t.id ORDER BY tm2.created_at DESC LIMIT 1) as last_sender_type
    FROM support_tickets t
    LEFT JOIN ticket_messages tm ON t.id = tm.ticket_id
    $whereClause
    GROUP BY t.id
    ORDER BY 
        CASE t.status 
            WHEN 'open' THEN 1 
            WHEN 'waiting_admin' THEN 2 
            WHEN 'in_progress' THEN 3 
            WHEN 'waiting_customer' THEN 4 
            WHEN 'resolved' THEN 5 
            WHEN 'closed' THEN 6 
        END,
        t.priority DESC,
        t.updated_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get specific ticket details if viewing one
$currentTicket = null;
$ticketMessages = [];

if (isset($_GET['ticket'])) {
    $ticketId = (int)$_GET['ticket'];
    
    // Get ticket details
    $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE id = ?");
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $currentTicket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($currentTicket) {
        // Get all messages for this ticket
        $stmt = $conn->prepare("
            SELECT 
                tm.*,
                CASE 
                    WHEN tm.sender_type = 'admin' THEN 'Support Team'
                    ELSE tm.sender_name 
                END as display_name
            FROM ticket_messages tm
            WHERE tm.ticket_id = ? 
            ORDER BY tm.created_at ASC
        ");
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $ticketMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets Admin - FreeReminders.net</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <style>
        :root {
            --primary-color: #0066CC;
            --primary-dark: #004494;
            --secondary-color: #FF6600;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --white: #FFFFFF;
            --dark-grey: #333333;
            --light-grey: #F5F5F5;
            --mid-grey: #6c757d;
            --border-color: #E0E0E0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-grey);
            color: var(--dark-grey);
        }

        /* Specific styles for support page */
        .support-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            align-items: flex-start;
        }

        .support-layout.details-visible {
            grid-template-columns: minmax(0, 2fr) minmax(450px, 1fr);
        }

        .filters-section {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 200px;
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .filter-group select, .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .tickets-section {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            background: var(--light-grey);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .tickets-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tickets-table th,
        .tickets-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .tickets-table th {
            background-color: var(--light-grey);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .tickets-table tr:hover {
            background-color: #f8f9fa;
        }

        .ticket-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }

        .ticket-status.open { background-color: #fff3cd; color: #856404; }
        .ticket-status.in_progress { background-color: #d1ecf1; color: #0c5460; }
        .ticket-status.waiting_admin { background-color: #f8d7da; color: #721c24; }
        .ticket-status.waiting_customer { background-color: #cce5ff; color: #004085; }
        .ticket-status.resolved { background-color: #d4edda; color: #155724; }
        .ticket-status.closed { background-color: #e2e3e5; color: #383d41; }

        .priority-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-low { background-color: #e8f5e8; color: #2e7d32; }
        .priority-medium { background-color: #e3f2fd; color: #1565c0; }
        .priority-high { background-color: #fff3e0; color: #ef6c00; }
        .priority-urgent { background-color: #ffebee; color: #c62828; }

        .ticket-details {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }

        .ticket-details-header {
            background: var(--primary-color);
            color: var(--white);
            padding: 15px;
            border-radius: 8px 8px 0 0;
        }

        .ticket-details-content {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-weight: 600;
            color: var(--mid-grey);
            margin-bottom: 5px;
        }

        .info-value {
            color: var(--dark-grey);
        }

        .message-thread {
            margin: 20px 0;
        }

        .message-item {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .message-customer {
            background-color: #f8f9fa;
            border-left-color: var(--primary-color);
        }

        .message-admin {
            background-color: #fff3e0;
            border-left-color: var(--secondary-color);
        }

        .message-internal {
            background-color: #ffe6e6;
            border-left-color: var(--danger-color);
            opacity: 0.9;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.85rem;
        }

        .sender-info {
            font-weight: 600;
        }

        .message-time {
            color: var(--mid-grey);
        }

        .admin-reply-form {
            background: var(--light-grey);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-family: inherit;
            resize: vertical;
        }

        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .form-options {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 15px 0;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success-color);
            color: var(--white);
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: var(--dark-grey);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }

        .btn-info {
            background-color: var(--info-color);
            color: var(--white);
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--dark-grey);
        }

        .pagination a:hover {
            background-color: var(--light-grey);
        }

        .pagination .current {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--mid-grey);
            font-size: 0.9rem;
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .support-layout.details-visible {
                grid-template-columns: 1fr;
            }
            
            .filters-row {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
        }

        .no-tickets {
            text-align: center;
            padding: 40px;
            color: var(--mid-grey);
        }

        .ticket-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .ticket-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-headset"></i> Support Tickets</h1>
                <p>View and manage support requests from users</p>
            </div>

            <?php if (isset($_SESSION['admin_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['admin_message_type'] ?? 'success'; ?>">
                    <?php echo htmlspecialchars($_SESSION['admin_message']); ?>
                </div>
                <?php 
                unset($_SESSION['admin_message'], $_SESSION['admin_message_type']); 
                ?>
            <?php endif; ?>

            <?php
            // Get stats for dashboard
            $statsQuery = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
                    SUM(CASE WHEN status = 'waiting_admin' THEN 1 ELSE 0 END) as waiting_admin,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tickets,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as today_tickets
                FROM support_tickets
            ";
            $statsResult = $conn->query($statsQuery);
            $stats = $statsResult->fetch_assoc();
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--primary-color);"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Tickets</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['open_tickets']; ?></div>
                    <div class="stat-label">Open Tickets</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['waiting_admin']; ?></div>
                    <div class="stat-label">Waiting for Admin</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--info-color);"><?php echo $stats['in_progress']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['resolved']; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['urgent_tickets']; ?></div>
                    <div class="stat-label">Urgent Priority</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--secondary-color);"><?php echo $stats['today_tickets']; ?></div>
                    <div class="stat-label">Today's Tickets</div>
                </div>
            </div>

            <div class="filters-section">
                <form method="get" id="filterForm">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" onchange="document.getElementById('filterForm').submit()">
                                <option value="">All Statuses</option>
                                <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="waiting_admin" <?php echo $statusFilter === 'waiting_admin' ? 'selected' : ''; ?>>Waiting Admin</option>
                                <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="waiting_customer" <?php echo $statusFilter === 'waiting_customer' ? 'selected' : ''; ?>>Waiting Customer</option>
                                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="priority">Priority</label>
                            <select name="priority" id="priority" onchange="document.getElementById('filterForm').submit()">
                                <option value="">All Priorities</option>
                                <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="category">Category</label>
                            <select name="category" id="category" onchange="document.getElementById('filterForm').submit()">
                                <option value="">All Categories</option>
                                <option value="technical" <?php echo $categoryFilter === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                <option value="billing" <?php echo $categoryFilter === 'billing' ? 'selected' : ''; ?>>Billing</option>
                                <option value="feature_request" <?php echo $categoryFilter === 'feature_request' ? 'selected' : ''; ?>>Feature Request</option>
                                <option value="bug_report" <?php echo $categoryFilter === 'bug_report' ? 'selected' : ''; ?>>Bug Report</option>
                                <option value="reminder_issues" <?php echo $categoryFilter === 'reminder_issues' ? 'selected' : ''; ?>>Reminder Issues</option>
                                <option value="general" <?php echo $categoryFilter === 'general' ? 'selected' : ''; ?>>General</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" placeholder="ID, subject, name, email..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="support-layout <?php echo $currentTicket ? 'details-visible' : ''; ?>">
                <div class="tickets-section">
                    <div class="section-header">
                        <h3>
                            Support Tickets 
                            <span style="color: var(--mid-grey); font-weight: normal; font-size: 0.9rem;">
                                (<?php echo $totalTickets; ?> total, showing <?php echo count($tickets); ?>)
                            </span>
                        </h3>
                        
                        <div class="bulk-actions">
                            <form method="post" style="display: inline-flex; gap: 10px; align-items: center;">
                                <select name="bulk_action" required>
                                    <option value="">Bulk Actions</option>
                                    <option value="close">Close Selected</option>
                                    <option value="reopen">Reopen Selected</option>
                                    <option value="mark_resolved">Mark Resolved</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (empty($tickets)): ?>
                        <div class="no-tickets">
                            <i class="fas fa-ticket-alt" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                            <h3>No tickets found</h3>
                            <p>Try adjusting your filters or search criteria.</p>
                        </div>
                    <?php else: ?>
                        <form id="bulkForm">
                            <table class="tickets-table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAll"></th>
                                        <th>ID</th>
                                        <th>Subject</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Category</th>
                                        <th>Messages</th>
                                        <th>Last Activity</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr <?php echo $currentTicket && $currentTicket['id'] == $ticket['id'] ? 'style="background-color: #e3f2fd;"' : ''; ?>>
                                            <td>
                                                <input type="checkbox" name="selected_tickets[]" value="<?php echo $ticket['id']; ?>" form="bulkForm">
                                            </td>
                                            <td>
                                                <a href="?ticket=<?php echo $ticket['id']; ?>" class="ticket-link">
                                                    #<?php echo $ticket['id']; ?>
                                                </a>
                                            </td>
                                            <td>
                                                <a href="?ticket=<?php echo $ticket['id']; ?>" class="ticket-link">
                                                    <?php echo htmlspecialchars(mb_substr($ticket['subject'], 0, 50) . (strlen($ticket['subject']) > 50 ? '...' : '')); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.9rem;">
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($ticket['name']); ?></div>
                                                    <div style="color: var(--mid-grey); font-size: 0.8rem;">
                                                        <?php echo htmlspecialchars($ticket['email']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="ticket-status <?php echo $ticket['status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.9rem;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['category'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--primary-color); font-weight: 500;">
                                                    <?php echo $ticket['message_count']; ?>
                                                </span>
                                                <?php if ($ticket['last_sender_type'] === 'customer' && $ticket['status'] !== 'closed'): ?>
                                                    <i class="fas fa-exclamation-circle" style="color: var(--danger-color); margin-left: 5px;" title="Customer replied - needs attention"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 0.85rem; color: var(--mid-grey);">
                                                <?php 
                                                echo $ticket['last_reply_at'] 
                                                    ? date('M j, g:i A', strtotime($ticket['last_reply_at']))
                                                    : date('M j, g:i A', strtotime($ticket['updated_at'])); 
                                                ?>
                                            </td>
                                            <td style="font-size: 0.85rem; color: var(--mid-grey);">
                                                <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($currentTicket): ?>
                    <div class="ticket-details">
                        <div class="ticket-details-header">
                            <h3>Ticket #<?php echo $currentTicket['id']; ?>: <?php echo htmlspecialchars($currentTicket['subject']); ?></h3>
                            <div style="font-size: 0.9rem; margin-top: 5px; opacity: 0.9;">
                                <?php echo htmlspecialchars($currentTicket['name']); ?> (<?php echo htmlspecialchars($currentTicket['email']); ?>)
                            </div>
                        </div>
                        
                        <div class="ticket-details-content">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="ticket-status <?php echo $currentTicket['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $currentTicket['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Priority</div>
                                    <div class="info-value">
                                        <span class="priority-badge priority-<?php echo $currentTicket['priority']; ?>">
                                            <?php echo ucfirst($currentTicket['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Category</div>
                                    <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $currentTicket['category'])); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Created</div>
                                    <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($currentTicket['created_at'])); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Last Updated</div>
                                    <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($currentTicket['updated_at'])); ?></div>
                                </div>
                                
                                <?php if ($currentTicket['closed_at']): ?>
                                <div class="info-item">
                                    <div class="info-label">Closed</div>
                                    <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($currentTicket['closed_at'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="message-thread">
                                <h4 style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color);">
                                    <i class="fas fa-comments"></i> Conversation (<?php echo count($ticketMessages); ?> messages)
                                </h4>
                                
                                <?php foreach ($ticketMessages as $message): ?>
                                    <div class="message-item message-<?php echo $message['sender_type']; ?> <?php echo $message['is_internal'] ? 'message-internal' : ''; ?>">
                                        <div class="message-header">
                                            <div class="sender-info">
                                                <?php if ($message['sender_type'] === 'admin'): ?>
                                                    <i class="fas fa-user-tie" style="color: var(--secondary-color); margin-right: 5px;"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-user" style="color: var(--primary-color); margin-right: 5px;"></i>
                                                <?php endif; ?>
                                                <strong><?php echo htmlspecialchars($message['display_name']); ?></strong>
                                                <?php if ($message['is_internal']): ?>
                                                    <span style="background: var(--danger-color); color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: 5px;">
                                                        INTERNAL NOTE
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="message-time">
                                                <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($currentTicket['status'] !== 'closed'): ?>
                                <div class="admin-reply-form">
                                    <h4><i class="fas fa-reply"></i> Reply to Ticket</h4>
                                    <form method="post">
                                        <input type="hidden" name="ticket_id" value="<?php echo $currentTicket['id']; ?>">
                                        
                                        <div class="form-group">
                                            <label for="reply_message">Message</label>
                                            <textarea name="reply_message" id="reply_message" required placeholder="Type your reply here..."></textarea>
                                        </div>
                                        
                                        <div class="form-options">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="is_internal" id="is_internal">
                                                <label for="is_internal">Internal Note (customer won't see this)</label>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="status">Update Status</label>
                                            <select name="status" id="status">
                                                <option value="">Keep Current Status</option>
                                                <option value="open">Open</option>
                                                <option value="in_progress">In Progress</option>
                                                <option value="waiting_customer">Waiting for Customer</option>
                                                <option value="resolved">Resolved</option>
                                                <option value="closed">Closed</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" name="admin_reply" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send Reply
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 20px; background-color: #f8f9fa; border-radius: 8px; margin-top: 20px;">
                                    <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success-color); margin-bottom: 10px;"></i>
                                    <h4 style="color: var(--success-color);">Ticket Closed</h4>
                                    <p style="color: var(--mid-grey);">This ticket has been closed and no longer accepts replies.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Handle select all checkbox
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_tickets[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Handle bulk actions form
        document.querySelector('.bulk-actions form')?.addEventListener('submit', function(e) {
            const selectedTickets = document.querySelectorAll('input[name="selected_tickets[]"]:checked');
            if (selectedTickets.length === 0) {
                e.preventDefault();
                alert('Please select at least one ticket.');
                return;
            }
            
            // Add selected ticket IDs to the bulk action form
            selectedTickets.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_tickets[]';
                hiddenInput.value = checkbox.value;
                this.appendChild(hiddenInput);
            });
        });
    </script>

</body>
</html>