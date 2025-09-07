<?php
/**
 * Admin - Database Tools
 * File: admin/database_tools.php
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit;
}

require_once '../db_connect.php';

// Handle database operations
$message = '';
$messageType = '';

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'optimize_tables':
            $tables = ['users', 'reminders', 'message_logs', 'categories', 'reminder_templates', 'system_logs', 'email_templates'];
            $optimized = [];
            $errors = [];
            
            foreach ($tables as $table) {
                $result = $conn->query("OPTIMIZE TABLE `$table`");
                if ($result) {
                    $optimized[] = $table;
                } else {
                    $errors[] = $table . ': ' . $conn->error;
                }
            }
            
            if (empty($errors)) {
                $message = "Successfully optimized " . count($optimized) . " tables: " . implode(', ', $optimized);
                $messageType = "success";
            } else {
                $message = "Optimization completed with errors: " . implode('; ', $errors);
                $messageType = "warning";
            }
            break;
            
        case 'repair_tables':
            $tables = ['users', 'reminders', 'message_logs', 'categories', 'reminder_templates', 'system_logs', 'email_templates'];
            $repaired = [];
            $errors = [];
            
            foreach ($tables as $table) {
                $result = $conn->query("REPAIR TABLE `$table`");
                if ($result) {
                    $repaired[] = $table;
                } else {
                    $errors[] = $table . ': ' . $conn->error;
                }
            }
            
            if (empty($errors)) {
                $message = "Successfully repaired " . count($repaired) . " tables: " . implode(', ', $repaired);
                $messageType = "success";
            } else {
                $message = "Repair completed with errors: " . implode('; ', $errors);
                $messageType = "warning";
            }
            break;
            
        case 'check_tables':
            $tables = ['users', 'reminders', 'message_logs', 'categories', 'reminder_templates', 'system_logs', 'email_templates'];
            $checked = [];
            $errors = [];
            
            foreach ($tables as $table) {
                $result = $conn->query("CHECK TABLE `$table`");
                if ($result) {
                    $row = $result->fetch_assoc();
                    if ($row['Msg_text'] == 'OK') {
                        $checked[] = $table . ' (OK)';
                    } else {
                        $errors[] = $table . ': ' . $row['Msg_text'];
                    }
                } else {
                    $errors[] = $table . ': ' . $conn->error;
                }
            }
            
            if (empty($errors)) {
                $message = "All tables checked successfully: " . implode(', ', $checked);
                $messageType = "success";
            } else {
                $message = "Table check completed with issues: " . implode('; ', $errors);
                $messageType = "warning";
            }
            break;
            
        case 'cleanup_old_data':
            $daysToKeep = (int)($_POST['days_to_keep'] ?? 90);
            $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
            $deletedCount = 0;
            
            // Clean up old message logs
            $stmt = $conn->prepare("DELETE FROM message_logs WHERE DATE(sent_at) < ?");
            $stmt->bind_param("s", $cutoffDate);
            if ($stmt->execute()) {
                $deletedCount += $conn->affected_rows;
            }
            $stmt->close();
            
            // Clean up old system logs
            $stmt = $conn->prepare("DELETE FROM system_logs WHERE DATE(created_at) < ?");
            $stmt->bind_param("s", $cutoffDate);
            if ($stmt->execute()) {
                $deletedCount += $conn->affected_rows;
            }
            $stmt->close();
            
            // Clean up completed reminders older than specified days
            $stmt = $conn->prepare("DELETE FROM reminders WHERE status IN ('completed', 'sent') AND DATE(created_at) < ?");
            $stmt->bind_param("s", $cutoffDate);
            if ($stmt->execute()) {
                $deletedCount += $conn->affected_rows;
            }
            $stmt->close();
            
            $message = "Cleaned up {$deletedCount} old records (older than {$daysToKeep} days).";
            $messageType = "success";
            break;
            
        case 'analyze_tables':
            $tables = ['users', 'reminders', 'message_logs', 'categories', 'reminder_templates', 'system_logs', 'email_templates'];
            $analyzed = [];
            $errors = [];
            
            foreach ($tables as $table) {
                $result = $conn->query("ANALYZE TABLE `$table`");
                if ($result) {
                    $analyzed[] = $table;
                } else {
                    $errors[] = $table . ': ' . $conn->error;
                }
            }
            
            if (empty($errors)) {
                $message = "Successfully analyzed " . count($analyzed) . " tables: " . implode(', ', $analyzed);
                $messageType = "success";
            } else {
                $message = "Analysis completed with errors: " . implode('; ', $errors);
                $messageType = "warning";
            }
            break;
    }
}

// Get database statistics
$dbStats = [];

// Get table information
$tablesQuery = "SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    DATA_LENGTH,
    INDEX_LENGTH,
    (DATA_LENGTH + INDEX_LENGTH) as TOTAL_SIZE,
    AUTO_INCREMENT
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY TOTAL_SIZE DESC";

$result = $conn->query($tablesQuery);
$tables = [];
$totalSize = 0;
while ($row = $result->fetch_assoc()) {
    $tables[] = $row;
    $totalSize += $row['TOTAL_SIZE'];
}

// Get database size
$dbSizeQuery = "SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB'
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()";
$result = $conn->query($dbSizeQuery);
$dbSize = $result->fetch_assoc()['DB Size in MB'];

// Get connection info
$connectionInfo = [];
$result = $conn->query("SELECT CONNECTION_ID() as connection_id, DATABASE() as database_name, USER() as user");
$connectionInfo = $result->fetch_assoc();

// Get MySQL version
$result = $conn->query("SELECT VERSION() as version");
$mysqlVersion = $result->fetch_assoc()['version'];

// Get database uptime
$result = $conn->query("SHOW STATUS LIKE 'Uptime'");
$uptime = $result->fetch_assoc()['Value'];
$uptimeHours = round($uptime / 3600, 2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Tools - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="content-header">
                <h1><i class="fas fa-database"></i> Database Tools</h1>
                <div class="header-actions">
                    <span class="db-size">Database Size: <?php echo $dbSize; ?> MB</span>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Database Overview -->
            <div class="db-overview">
                <div class="overview-cards">
                    <div class="overview-card">
                        <div class="card-icon">
                            <i class="fas fa-server"></i>
                        </div>
                        <div class="card-content">
                            <h3>MySQL Version</h3>
                            <div class="card-value"><?php echo htmlspecialchars($mysqlVersion); ?></div>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="card-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="card-content">
                            <h3>Database</h3>
                            <div class="card-value"><?php echo htmlspecialchars($connectionInfo['database_name']); ?></div>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <h3>Uptime</h3>
                            <div class="card-value"><?php echo $uptimeHours; ?> hours</div>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="card-icon">
                            <i class="fas fa-table"></i>
                        </div>
                        <div class="card-content">
                            <h3>Tables</h3>
                            <div class="card-value"><?php echo count($tables); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Operations -->
            <div class="db-operations">
                <div class="operations-grid">
                    <!-- Table Maintenance -->
                    <div class="operation-section">
                        <h3><i class="fas fa-wrench"></i> Table Maintenance</h3>
                        <div class="operation-buttons">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="check_tables">
                                <button type="submit" class="btn btn-info" onclick="return confirm('Check all database tables for errors?');">
                                    <i class="fas fa-search"></i> Check Tables
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="repair_tables">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('Repair all database tables? This may take several minutes.');">
                                    <i class="fas fa-tools"></i> Repair Tables
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="optimize_tables">
                                <button type="submit" class="btn btn-success" onclick="return confirm('Optimize all database tables? This may take several minutes.');">
                                    <i class="fas fa-tachometer-alt"></i> Optimize Tables
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="analyze_tables">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Analyze all database tables?');">
                                    <i class="fas fa-chart-line"></i> Analyze Tables
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Data Cleanup -->
                    <div class="operation-section">
                        <h3><i class="fas fa-broom"></i> Data Cleanup</h3>
                        <form method="POST" class="cleanup-form">
                            <input type="hidden" name="action" value="cleanup_old_data">
                            <div class="form-group">
                                <label for="days_to_keep">Keep data from last:</label>
                                <select name="days_to_keep" id="days_to_keep">
                                    <option value="30">30 days</option>
                                    <option value="60">60 days</option>
                                    <option value="90" selected>90 days</option>
                                    <option value="180">6 months</option>
                                    <option value="365">1 year</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('This will permanently delete old message logs, system logs, and completed reminders. Are you sure?');">
                                <i class="fas fa-trash"></i> Clean Old Data
                            </button>
                            <p class="cleanup-note">
                                <i class="fas fa-info-circle"></i>
                                This will remove old message logs, system logs, and completed reminders older than the selected period.
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Table Information -->
            <div class="table-info-section">
                <h3><i class="fas fa-table"></i> Table Information</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <th>Rows</th>
                                <th>Data Size</th>
                                <th>Index Size</th>
                                <th>Total Size</th>
                                <th>Auto Increment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($table['TABLE_NAME']); ?></strong></td>
                                    <td><?php echo number_format($table['TABLE_ROWS']); ?></td>
                                    <td><?php echo formatBytes($table['DATA_LENGTH']); ?></td>
                                    <td><?php echo formatBytes($table['INDEX_LENGTH']); ?></td>
                                    <td><?php echo formatBytes($table['TOTAL_SIZE']); ?></td>
                                    <td><?php echo $table['AUTO_INCREMENT'] ? number_format($table['AUTO_INCREMENT']) : 'N/A'; ?></td>
                                    <td>
                                        <button onclick="showTableDetails('<?php echo $table['TABLE_NAME']; ?>')" 
                                                class="btn-action btn-info" title="View Details">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-footer">
                                <td><strong>TOTAL</strong></td>
                                <td><strong><?php echo number_format(array_sum(array_column($tables, 'TABLE_ROWS'))); ?></strong></td>
                                <td colspan="2"></td>
                                <td><strong><?php echo formatBytes($totalSize); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Details Modal -->
    <div id="tableModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Table Details</h3>
                <span class="close" onclick="closeTableModal()">&times;</span>
            </div>
            <div class="modal-body" id="tableDetails">
                <!-- Table details will be loaded here -->
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        function showTableDetails(tableName) {
            fetch(`ajax/get_table_details.php?table=${tableName}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('tableDetails').innerHTML = data.html;
                        document.getElementById('tableModal').style.display = 'block';
                    } else {
                        alert('Error loading table details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading table details');
                });
        }

        function closeTableModal() {
            document.getElementById('tableModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('tableModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Add loading indicators to operation buttons
        document.querySelectorAll('.operation-buttons form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button');
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                // Re-enable button after 30 seconds as fallback
                setTimeout(() => {
                    button.disabled = false;
                    button.innerHTML = button.innerHTML.replace('<i class="fas fa-spinner fa-spin"></i> Processing...', button.getAttribute('data-original-text'));
                }, 30000);
            });
        });

        // Store original button text
        document.querySelectorAll('.operation-buttons button').forEach(button => {
            button.setAttribute('data-original-text', button.innerHTML);
        });
    </script>
</body>
</html>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>