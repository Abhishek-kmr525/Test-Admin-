<?php
/**
 * Admin - Analytics Dashboard
 * File: admin/analytics.php
 */
session_start();

// ✅ Check if user is logged in and is admin
if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../db_connect.php';


// Date range for analytics (default: last 30 days)
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Ensure start date is not after end date
if (strtotime($startDate) > strtotime($endDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// Get overall statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Premium users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE subscription_type = 'paid'");
$stats['premium_users'] = $result->fetch_assoc()['total'];

// Total reminders
$result = $conn->query("SELECT COUNT(*) as total FROM reminders");
$stats['total_reminders'] = $result->fetch_assoc()['total'];

// Active reminders
$result = $conn->query("SELECT COUNT(*) as total FROM reminders WHERE status IN ('pending', 'scheduled', 'processing')");
$stats['active_reminders'] = $result->fetch_assoc()['total'];

// Messages in date range
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_messages,
    COUNT(CASE WHEN type = 'email' THEN 1 END) as total_emails,
    COUNT(CASE WHEN type = 'sms' THEN 1 END) as total_sms,
    COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful_messages,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_messages
    FROM message_logs 
    WHERE DATE(sent_at) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$messageStats = $result->fetch_assoc();
$stmt->close();

// Daily message statistics for chart
$dailyStats = [];
$stmt = $conn->prepare("SELECT 
    DATE(sent_at) as date,
    COUNT(*) as total,
    COUNT(CASE WHEN type = 'email' THEN 1 END) as emails,
    COUNT(CASE WHEN type = 'sms' THEN 1 END) as sms,
    COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful
    FROM message_logs 
    WHERE DATE(sent_at) BETWEEN ? AND ?
    GROUP BY DATE(sent_at)
    ORDER BY DATE(sent_at)");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $dailyStats[] = $row;
}
$stmt->close();

// User registration statistics
$userStats = [];
$stmt = $conn->prepare("SELECT 
    DATE(created_at) as date,
    COUNT(*) as registrations,
    COUNT(CASE WHEN subscription_type = 'paid' THEN 1 END) as premium_registrations
    FROM users 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $userStats[] = $row;
}
$stmt->close();

// Top users by message volume
$topUsers = [];
$stmt = $conn->prepare("SELECT 
    u.id, u.first_name, u.last_name, u.email, u.subscription_type,
    COUNT(ml.id) as message_count,
    COUNT(CASE WHEN ml.type = 'email' THEN 1 END) as email_count,
    COUNT(CASE WHEN ml.type = 'sms' THEN 1 END) as sms_count
    FROM users u
    JOIN message_logs ml ON u.id = ml.user_id
    WHERE DATE(ml.sent_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY message_count DESC
    LIMIT 10");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $topUsers[] = $row;
}
$stmt->close();

// Reminder categories statistics
$categoryStats = [];
$stmt = $conn->prepare("SELECT 
    COALESCE(c.name, 'Custom') as category_name,
    COUNT(r.id) as reminder_count,
    COUNT(CASE WHEN r.status = 'completed' OR r.status = 'sent' THEN 1 END) as completed_count
    FROM reminders r
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE DATE(r.created_at) BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY reminder_count DESC
    LIMIT 10");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categoryStats[] = $row;
}
$stmt->close();

// Calculate success rates
$successRate = $messageStats['total_messages'] > 0 ? 
    round(($messageStats['successful_messages'] / $messageStats['total_messages']) * 100, 2) : 0;

$premiumPercentage = $stats['total_users'] > 0 ? 
    round(($stats['premium_users'] / $stats['total_users']) * 100, 2) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="content-header">
                <h1><i class="fas fa-chart-bar"></i> Analytics Dashboard</h1>
                <div class="header-actions">
                    <form method="GET" class="date-range-form">
                        <div class="date-range-group">
                            <label for="start_date">From:</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="date-range-group">
                            <label for="end_date">To:</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Update
                        </button>
                    </form>
                </div>
            </div>

            <!-- Overview Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Users</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-details">
                            <span class="detail-item">
                                <i class="fas fa-crown text-gold"></i> 
                                <?php echo number_format($stats['premium_users']); ?> Premium (<?php echo $premiumPercentage; ?>%)
                            </span>
                            <span class="detail-item">
                                <i class="fas fa-user text-grey"></i> 
                                <?php echo number_format($stats['total_users'] - $stats['premium_users']); ?> Free
                            </span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Reminders</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_reminders']); ?></div>
                        <div class="stat-details">
                            <span class="detail-item">
                                <i class="fas fa-play text-green"></i> 
                                <?php echo number_format($stats['active_reminders']); ?> Active
                            </span>
                            <span class="detail-item">
                                <i class="fas fa-check text-blue"></i> 
                                <?php echo number_format($stats['total_reminders'] - $stats['active_reminders']); ?> Completed
                            </span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Messages (Period)</h3>
                        <div class="stat-number"><?php echo number_format($messageStats['total_messages']); ?></div>
                        <div class="stat-details">
                            <span class="detail-item">
                                <i class="fas fa-envelope text-blue"></i> 
                                <?php echo number_format($messageStats['total_emails']); ?> Email
                            </span>
                            <span class="detail-item">
                                <i class="fas fa-sms text-orange"></i> 
                                <?php echo number_format($messageStats['total_sms']); ?> SMS
                            </span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Success Rate</h3>
                        <div class="stat-number"><?php echo $successRate; ?>%</div>
                        <div class="stat-details">
                            <span class="detail-item success-count">
                                <i class="fas fa-check"></i> 
                                <?php echo number_format($messageStats['successful_messages']); ?> Successful
                            </span>
                            <span class="detail-item error-count">
                                <i class="fas fa-times"></i> 
                                <?php echo number_format($messageStats['failed_messages']); ?> Failed
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Daily Messages Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Daily Message Volume</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="dailyMessagesChart"></canvas>
                    </div>
                </div>

                <!-- User Registrations Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><i class="fas fa-user-plus"></i> Daily Registrations</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="userRegistrationsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Tables -->
            <div class="analytics-tables">
                <!-- Top Users -->
                <div class="analytics-table">
                    <div class="table-header">
                        <h3><i class="fas fa-trophy"></i> Top Users by Message Volume</h3>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>Plan</th>
                                    <th>Total</th>
                                    <th>Email</th>
                                    <th>SMS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($topUsers)): ?>
                                    <?php foreach ($topUsers as $index => $user): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge rank-<?php echo min($index + 1, 3); ?>">
                                                    #<?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                    <br>
                                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($user['subscription_type'] === 'paid'): ?>
                                                    <span class="badge badge-premium">Premium</span>
                                                <?php else: ?>
                                                    <span class="badge badge-free">Free</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo number_format($user['message_count']); ?></strong></td>
                                            <td><?php echo number_format($user['email_count']); ?></td>
                                            <td><?php echo number_format($user['sms_count']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No data available for selected period</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Category Statistics -->
                <div class="analytics-table">
                    <div class="table-header">
                        <h3><i class="fas fa-tags"></i> Reminder Categories</h3>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total Reminders</th>
                                    <th>Completed</th>
                                    <th>Completion Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($categoryStats)): ?>
                                    <?php foreach ($categoryStats as $category): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                                            </td>
                                            <td><?php echo number_format($category['reminder_count']); ?></td>
                                            <td><?php echo number_format($category['completed_count']); ?></td>
                                            <td>
                                                <?php 
                                                $completionRate = $category['reminder_count'] > 0 ? 
                                                    round(($category['completed_count'] / $category['reminder_count']) * 100, 1) : 0;
                                                ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $completionRate; ?>%"></div>
                                                    <span class="progress-text"><?php echo $completionRate; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No data available for selected period</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        // Daily Messages Chart
        const dailyMessagesCtx = document.getElementById('dailyMessagesChart').getContext('2d');
        const dailyMessagesChart = new Chart(dailyMessagesCtx, {
            type: 'line',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($dailyStats, 'date')) . '"'; ?>],
                datasets: [{
                    label: 'Total Messages',
                    data: [<?php echo implode(',', array_column($dailyStats, 'total')); ?>],
                    borderColor: '#0066CC',
                    backgroundColor: 'rgba(0, 102, 204, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Email',
                    data: [<?php echo implode(',', array_column($dailyStats, 'emails')); ?>],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }, {
                    label: 'SMS',
                    data: [<?php echo implode(',', array_column($dailyStats, 'sms')); ?>],
                    borderColor: '#FF6600',
                    backgroundColor: 'rgba(255, 102, 0, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // User Registrations Chart
        const userRegistrationsCtx = document.getElementById('userRegistrationsChart').getContext('2d');
        const userRegistrationsChart = new Chart(userRegistrationsCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($userStats, 'date')) . '"'; ?>],
                datasets: [{
                    label: 'Total Registrations',
                    data: [<?php echo implode(',', array_column($userStats, 'registrations')); ?>],
                    backgroundColor: 'rgba(0, 102, 204, 0.8)',
                    borderColor: '#0066CC',
                    borderWidth: 1
                }, {
                    label: 'Premium Registrations',
                    data: [<?php echo implode(',', array_column($userStats, 'premium_registrations')); ?>],
                    backgroundColor: 'rgba(255, 193, 7, 0.8)',
                    borderColor: '#ffc107',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>