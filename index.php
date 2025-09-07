<?php
/**
 * Admin Panel - Main Dashboard
 * FreeReminders.net Admin Interface
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

require_once '../db_connect.php';

// Get system statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Active subscriptions
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE subscription_type = 'paid'");
$stats['paid_users'] = $result->fetch_assoc()['total'];

// Total reminders
$result = $conn->query("SELECT COUNT(*) as total FROM reminders");
$stats['total_reminders'] = $result->fetch_assoc()['total'];

// Reminders sent today
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as total FROM reminders WHERE DATE(last_sent) = '$today'");
$stats['reminders_today'] = $result->fetch_assoc()['total'];

// Messages sent this month
$currentMonth = date('Y-m');
$result = $conn->query("SELECT COUNT(*) as total FROM message_logs WHERE DATE_FORMAT(sent_at, '%Y-%m') = '$currentMonth'");
$stats['messages_this_month'] = $result->fetch_assoc()['total'];

// Failed reminders
$result = $conn->query("SELECT COUNT(*) as total FROM reminders WHERE status = 'failed'");
$stats['failed_reminders'] = $result->fetch_assoc()['total'];

// Revenue calculation (rough estimate)
$result = $conn->query("
    SELECT 
        SUM(CASE 
            WHEN plan_type = 'monthly' THEN 5.99 
            WHEN plan_type = 'annual' THEN 39.99 
            WHEN plan_type = '3-year' THEN 89.99 
            ELSE 0 
        END) as total_revenue
    FROM users WHERE subscription_type = 'paid'
");
$stats['estimated_revenue'] = $result->fetch_assoc()['total_revenue'] ?? 0;

// Recent activity
$recentUsers = $conn->query("SELECT first_name, last_name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$recentReminders = $conn->query("
    SELECT r.subject, u.first_name, u.last_name, r.created_at, r.status 
    FROM reminders r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FreeReminders.net</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>
                <p>System statistics and recent activity</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Users</p>
                        <span class="stat-detail"><?php echo $stats['paid_users']; ?> Premium</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon reminders">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_reminders']); ?></h3>
                        <p>Total Reminders</p>
                        <span class="stat-detail"><?php echo $stats['reminders_today']; ?> sent today</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon messages">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['messages_this_month']); ?></h3>
                        <p>Messages This Month</p>
                        <span class="stat-detail"><?php echo $stats['failed_reminders']; ?> failed</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3>$<?php echo number_format($stats['estimated_revenue'], 2); ?></h3>
                        <p>Est. Monthly Revenue</p>
                        <span class="stat-detail"><?php echo round(($stats['paid_users']/$stats['total_users'])*100, 1); ?>% conversion</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-section">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="quick-actions">
                    <a href="users.php" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Manage Users</span>
                    </a>
                    <a href="reminders.php" class="quick-action-btn">
                        <i class="fas fa-bell"></i>
                        <span>View All Reminders</span>
                    </a>
                    <a href="system_logs.php" class="quick-action-btn">
                        <i class="fas fa-list-alt"></i>
                        <span>System Logs</span>
                    </a>
                    <a href="settings.php" class="quick-action-btn">
                        <i class="fas fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                    <a href="email_templates.php" class="quick-action-btn">
                        <i class="fas fa-envelope-open-text"></i>
                        <span>Email Templates</span>
                    </a>
                    <a href="database.php" class="quick-action-btn">
                        <i class="fas fa-database"></i>
                        <span>Database Tools</span>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="dashboard-row">
                <div class="dashboard-section half-width">
                    <h2><i class="fas fa-user-clock"></i> Recent Users</h2>
                    <div class="activity-list">
                        <?php foreach ($recentUsers as $user): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></p>
                                <p class="activity-detail"><?php echo htmlspecialchars($user['email']); ?></p>
                                <p class="activity-time"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dashboard-section half-width">
                    <h2><i class="fas fa-bell"></i> Recent Reminders</h2>
                    <div class="activity-list">
                        <?php foreach ($recentReminders as $reminder): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong><?php echo htmlspecialchars($reminder['subject']); ?></strong></p>
                                <p class="activity-detail">by <?php echo htmlspecialchars($reminder['first_name'] . ' ' . $reminder['last_name']); ?></p>
                                <p class="activity-time">
                                    <?php echo date('M j, Y g:i A', strtotime($reminder['created_at'])); ?>
                                    <span class="status-badge <?php echo $reminder['status']; ?>"><?php echo ucfirst($reminder['status']); ?></span>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- System Health -->
            <div class="dashboard-section">
                <h2><i class="fas fa-heartbeat"></i> System Health</h2>
                <div class="health-checks">
                    <?php
                    // Database connectivity check
                    $dbHealthy = $conn->ping();
                    
                    // Check if reminder processor is working (last reminder sent in last hour)
                    $lastHour = date('Y-m-d H:i:s', strtotime('-1 hour'));
                    $result = $conn->query("SELECT COUNT(*) as count FROM reminders WHERE last_sent >= '$lastHour'");
                    $recentSent = $result->fetch_assoc()['count'];
                    
                    // Check disk space (if possible)
                    $diskSpace = disk_free_space('/');
                    $totalSpace = disk_total_space('/');
                    $diskUsagePercent = $diskSpace ? round((($totalSpace - $diskSpace) / $totalSpace) * 100, 1) : 'Unknown';
                    ?>
                    
                    <div class="health-item <?php echo $dbHealthy ? 'healthy' : 'unhealthy'; ?>">
                        <i class="fas fa-database"></i>
                        <span>Database Connection</span>
                        <span class="health-status"><?php echo $dbHealthy ? 'Healthy' : 'Error'; ?></span>
                    </div>
                    
                    <div class="health-item <?php echo $recentSent > 0 ? 'healthy' : 'warning'; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Reminder Processor</span>
                        <span class="health-status"><?php echo $recentSent > 0 ? 'Active' : 'Inactive'; ?></span>
                    </div>
                    
                    <div class="health-item <?php echo $diskUsagePercent < 90 ? 'healthy' : 'warning'; ?>">
                        <i class="fas fa-hdd"></i>
                        <span>Disk Usage</span>
                        <span class="health-status"><?php echo $diskUsagePercent; ?>%</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="js/admin.js"></script>
</body>
</html>