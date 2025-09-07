<?php
/**
 * Admin - Message Logs
 * File: admin/message_logs.php
 */
session_start();

if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../timezone_utils_utc.php';

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$typeFilter   = $_GET['type']   ?? '';
$statusFilter = $_GET['status'] ?? '';
$userFilter   = $_GET['user']   ?? '';
$dateFilter   = $_GET['date']   ?? '';
$searchTerm   = trim($_GET['search'] ?? '');

// Build query
$whereConditions = [];
$params = [];
$types = '';

if ($typeFilter !== '') {
    $whereConditions[] = "ml.type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

if ($statusFilter !== '') {
    $whereConditions[] = "ml.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($userFilter !== '') {
    $whereConditions[] = "ml.user_id = ?";
    $params[] = (int)$userFilter;
    $types .= 'i';
}

if ($dateFilter !== '') {
    $whereConditions[] = "DATE(ml.sent_at) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

if ($searchTerm !== '') {
    $whereConditions[] = "(ml.recipient LIKE ? OR ml.subject LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $searchPattern = "%$searchTerm%";
    $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern]);
    $types .= 'sss';
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// ✅ Get total count separately (no LIMIT here)
$countQuery = "SELECT COUNT(*) as total FROM message_logs ml 
               LEFT JOIN users u ON ml.user_id = u.id $whereClause";

if ($params) {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalLogs = $totalResult->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $totalResult = $conn->query($countQuery);
    $totalLogs = $totalResult->fetch_assoc()['total'];
}

$totalPages = max(1, ceil($totalLogs / $limit));

// ✅ Get message logs (with LIMIT/OFFSET)
$query = "SELECT ml.*, u.first_name, u.last_name, u.email as user_email, u.subscription_type,
          r.subject as reminder_subject
          FROM message_logs ml
          LEFT JOIN users u ON ml.user_id = u.id
          LEFT JOIN reminders r ON ml.reminder_id = r.id
          $whereClause
          ORDER BY ml.sent_at DESC
          LIMIT ? OFFSET ?";

$finalParams = array_merge($params, [$limit, $offset]);
$finalTypes  = $types . 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats = [];

// Messages sent today
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN type = 'email' THEN 1 END) as emails,
    COUNT(CASE WHEN type = 'sms' THEN 1 END) as sms,
    COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
    FROM message_logs WHERE DATE(sent_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$stats['today'] = $result->fetch_assoc();
$stmt->close();

// Messages sent this month
$thisMonth = date('Y-m-01');
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN type = 'email' THEN 1 END) as emails,
    COUNT(CASE WHEN type = 'sms' THEN 1 END) as sms
    FROM message_logs WHERE sent_at >= ?");
$stmt->bind_param("s", $thisMonth);
$stmt->execute();
$result = $stmt->get_result();
$stats['month'] = $result->fetch_assoc();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Logs - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_styles.css">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="content-header">
                <h1><i class="fas fa-envelope-open-text"></i> Message Logs</h1>
                <div class="header-actions">
                    <span class="total-count">Total: <?php echo number_format($totalLogs); ?> messages</span>
                </div>
            </div>

            <!-- Stats cards here (unchanged)... -->

            <!-- Filters here (unchanged)... -->

            <!-- Message Logs Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Sent At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td>
                                        <?php if ($log['admin_id']): ?>
                                            <div class="user-info">
                                                <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong>
                                                <br>
                                                <small><?php echo htmlspecialchars($log['user_email']); ?></small>
                                                <?php if ($log['subscription_type'] === 'paid'): ?>
                                                    <span class="badge badge-premium">Premium</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['type'] === 'email'): ?>
                                            <span class="type-badge type-email"><i class="fas fa-envelope"></i> Email</span>
                                        <?php elseif ($log['type'] === 'sms'): ?>
                                            <span class="type-badge type-sms"><i class="fas fa-sms"></i> SMS</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['recipient']); ?></td>
                                    <td><?php echo htmlspecialchars($log['subject'] ?: $log['reminder_subject'] ?: 'No subject'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $log['status']; ?>">
                                            <?php
                                            switch ($log['status']) {
                                                case 'sent': echo '<i class="fas fa-check"></i> Sent'; break;
                                                case 'failed': echo '<i class="fas fa-times"></i> Failed'; break;
                                                case 'pending': echo '<i class="fas fa-clock"></i> Pending'; break;
                                                default: echo ucfirst($log['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($log['sent_at'])) {
                                            $sentAt = new DateTime($log['sent_at']);
                                            echo $sentAt->format('M j, Y g:i A');
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="viewMessage(<?php echo $log['id']; ?>)" class="btn-action btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (!empty($log['error_message'])): ?>
                                                <button onclick="viewError(<?php echo $log['id']; ?>)" class="btn-action btn-warning" title="View Error">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center">No message logs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php
                        $currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
                        $queryParams = $_GET;
                        ?>
                        <?php if ($page > 1): ?>
                            <?php $queryParams['page'] = $page - 1; ?>
                            <a href="<?php echo $currentUrl . '?' . http_build_query($queryParams); ?>" class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        <span class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo number_format($totalLogs); ?> total)
                        </span>
                        <?php if ($page < $totalPages): ?>
                            <?php $queryParams['page'] = $page + 1; ?>
                            <a href="<?php echo $currentUrl . '?' . http_build_query($queryParams); ?>" class="btn btn-secondary">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals + JS same as before -->
    <script src="js/admin.js"></script>
    <script>
        function viewMessage(id) {
            fetch(`ajax/get_message_log.php?id=${id}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        document.getElementById('messageDetails').innerHTML = d.html;
                        document.getElementById('messageModal').style.display = 'block';
                    } else alert('Error loading message details');
                });
        }
        function viewError(id) {
            fetch(`ajax/get_message_error.php?id=${id}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        document.getElementById('errorDetails').innerHTML = d.html;
                        document.getElementById('errorModal').style.display = 'block';
                    } else alert('Error loading error details');
                });
        }
        function closeModal(){document.getElementById('messageModal').style.display='none';}
        function closeErrorModal(){document.getElementById('errorModal').style.display='none';}
        window.onclick = function(e){
            if(e.target.classList.contains('modal')) e.target.style.display='none';
        }
    </script>
</body>
</html>
