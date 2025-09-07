<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../db_connect.php';


// Handle actions (delete, activate, deactivate)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $reminderId = (int)$_GET['id'];
    
    switch ($action) {
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM reminders WHERE id = ?");
            $stmt->bind_param("i", $reminderId);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Reminder deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting reminder.";
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
            break;
            
        case 'activate':
            $stmt = $conn->prepare("UPDATE reminders SET status = 'pending' WHERE id = ?");
            $stmt->bind_param("i", $reminderId);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Reminder activated successfully.";
                $_SESSION['message_type'] = "success";
            }
            $stmt->close();
            break;
            
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE reminders SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param("i", $reminderId);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Reminder deactivated successfully.";
                $_SESSION['message_type'] = "success";
            }
            $stmt->close();
            break;
    }
    
    header("Location: reminders.php");
    exit;
}

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$userFilter = isset($_GET['user']) ? $_GET['user'] : '';
$searchFilter = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$whereConditions = [];
$params = [];
$types = '';

if (!empty($statusFilter)) {
    $whereConditions[] = "r.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($userFilter)) {
    $whereConditions[] = "r.user_id = ?";
    $params[] = $userFilter;
    $types .= 'i';
}

if (!empty($searchFilter)) {
    $whereConditions[] = "(r.subject LIKE ? OR r.message LIKE ? OR r.recipients LIKE ?)";
    $searchTerm = '%' . $searchFilter . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM reminders r LEFT JOIN users u ON r.user_id = u.id $whereClause";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalResults = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalResults / $limit);
$countStmt->close();

// Get reminders
$query = "SELECT r.*, u.email, u.first_name, u.last_name, 
          CONCAT(u.first_name, ' ', u.last_name) as user_name
          FROM reminders r 
          LEFT JOIN users u ON r.user_id = u.id 
          $whereClause
          ORDER BY r.created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$reminders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get users for filter dropdown
$usersQuery = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email FROM users ORDER BY first_name, last_name";
$usersResult = $conn->query($usersQuery);
$users = $usersResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Reminders - Admin Panel</title>
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        
        /* ===============================
   Admin Panel Styles
   File: css/admin_styles.css
   =============================== */

/* ----- CSS Variables ----- */
:root{
  --bg: #f6f7fb;
  --panel: #ffffff;
  --text: #1f2937;
  --muted: #6b7280;
  --border: #e5e7eb;
  --brand: #2563eb;
  --brand-600:#1d4ed8;
  --success:#16a34a;
  --warning:#f59e0b;
  --danger:#dc2626;
  --info:#0ea5e9;
  --secondary:#64748b;
  --shadow: 0 8px 24px rgba(2,6,23,0.08);
  --radius: 14px;
}

/* ----- Base ----- */
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  background:var(--bg);
  color:var(--text);
  font: 14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, "Helvetica Neue", Arial, "Apple Color Emoji","Segoe UI Emoji";
}
a{color:var(--brand);text-decoration:none}
a:hover{color:var(--brand-600)}
img{max-width:100%;display:block}
.text-center{text-align:center}
.text-muted{color:var(--muted)}
.badge{display:inline-block;padding:.25rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600}
.badge-light{background:#f3f4f6;color:#374151}
.badge-primary{background:var(--brand);color:#fff}
.badge-success{background:var(--success);color:#fff}
.badge-warning{background:var(--warning);color:#111827}
.badge-danger{background:var(--danger);color:#fff}
.badge-info{background:var(--info);color:#0b1220}
.badge-secondary{background:var(--secondary);color:#fff}

/* ----- Layout ----- */
.admin-container{
  display:grid;
  grid-template-columns: 260px 1fr;
  min-height:100vh;
}

/* Optional: style your included sidebar if it uses .admin-sidebar */
.admin-sidebar{
  background:#0f172a;
  color:#e2e8f0;
  padding:22px 16px;
}
.admin-sidebar a{color:#e2e8f0;opacity:.9}
.admin-sidebar a:hover{opacity:1}

.admin-main{
  padding:24px;
}

/* ----- Header / Breadcrumb ----- */
.admin-header{
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  flex-wrap:wrap;
  gap:12px;
  margin-bottom:18px;
}
.admin-header h1{
  margin:0;
  font-size:24px;
  display:flex;
  align-items:center;
  gap:10px;
}
.admin-breadcrumb{
  font-size:13px;
  color:var(--muted);
}
.admin-breadcrumb a{color:inherit}
.admin-breadcrumb a:hover{color:var(--text)}

/* ----- Alerts ----- */
.alert{
  border:1px solid var(--border);
  background:#fff;
  box-shadow:var(--shadow);
  padding:12px 14px;
  border-radius:10px;
  margin:14px 0;
}
.alert-success{border-color:#bbf7d0;background:#ecfdf5;color:#065f46}
.alert-error{border-color:#fecaca;background:#fef2f2;color:#7f1d1d}
.alert-warning{border-color:#fde68a;background:#fffbeb;color:#78350f}
.alert-info{border-color:#bae6fd;background:#eff6ff;color:#1e3a8a}

/* ----- Filters card ----- */
.filters-card{
  background:var(--panel);
  border:1px solid var(--border);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  padding:16px;
  margin:18px 0;
}
.filters-form{
  display:flex;
  flex-wrap:wrap;
  gap:12px 16px;
  align-items:flex-end;
}
.filter-group{
  display:flex;
  flex-direction:column;
  gap:6px;
  min-width:220px;
}
.filter-group label{
  font-size:12px;
  color:var(--muted);
}
.filters-form input[type="text"],
.filters-form select{
  border:1px solid var(--border);
  background:#fff;
  border-radius:10px;
  padding:10px 12px;
  outline:none;
  transition:border-color .2s, box-shadow .2s;
}
.filters-form input[type="text"]:focus,
.filters-form select:focus{
  border-color:var(--brand);
  box-shadow:0 0 0 3px rgba(37,99,235,.15);
}

/* ----- Buttons ----- */
.btn{
  display:inline-flex;
  align-items:center;
  gap:8px;
  border:1px solid transparent;
  background:#f3f4f6;
  color:#111827;
  padding:10px 14px;
  border-radius:10px;
  cursor:pointer;
  font-weight:600;
  transition:transform .06s ease, box-shadow .15s ease, background .2s ease, color .2s ease;
  box-shadow:0 1px 0 rgba(0,0,0,.02);
}
.btn:hover{transform:translateY(-1px)}
.btn:active{transform:translateY(0)}
.btn-primary{background:var(--brand);color:#fff}
.btn-primary:hover{background:var(--brand-600)}
.btn-secondary{background:#eef2ff;color:#1e3a8a;border-color:#c7d2fe}
.btn-info{background:#e0f2fe;color:#075985;border-color:#bae6fd}
.btn-warning{background:#fff7ed;color:#9a3412;border-color:#fed7aa}
.btn-success{background:#dcfce7;color:#166534;border-color:#bbf7d0}
.btn-danger{background:#fee2e2;color:#991b1b;border-color:#fecaca}

.btn-sm{padding:6px 8px;border-radius:8px;font-size:12px}

/* ----- Results summary ----- */
.results-summary{
  font-size:13px;
  color:var(--muted);
  margin:10px 2px 14px;
}

/* ----- Table ----- */
.table-container{
  background:var(--panel);
  border:1px solid var(--border);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  overflow:hidden;
}

.admin-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
}
.admin-table thead th{
  text-align:left;
  background:#f8fafc;
  color:#334155;
  font-size:12px;
  letter-spacing:.02em;
  padding:12px 14px;
  border-bottom:1px solid var(--border);
}
.admin-table tbody td{
  padding:14px;
  border-top:1px solid var(--border);
  vertical-align:top;
}
.admin-table tbody tr:hover{
  background:#fafafa;
}

/* Column helpers */
.admin-table td.actions{
  white-space:nowrap;
}
.admin-table td small{
  font-size:12px;
}

/* Icon tints (optional) */
.text-primary{color:var(--brand)}
.text-warning{color:#b45309}

/* ----- Pagination ----- */
.pagination-container{
  display:flex;
  justify-content:center;
  margin:18px 0 28px;
}
.pagination{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.page-link{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:36px;
  height:36px;
  padding:0 12px;
  border:1px solid var(--border);
  border-radius:10px;
  background:#fff;
  color:#111827;
  font-weight:600;
  transition:background .2s, border-color .2s, transform .06s;
}
.page-link:hover{background:#f8fafc}
.page-link.active{
  background:var(--brand);
  border-color:var(--brand);
  color:#fff;
}

/* ----- Utilities ----- */
.hidden{display:none!important}

/* ----- Responsive ----- */
@media (max-width: 1024px){
  .admin-container{grid-template-columns: 220px 1fr}
}
@media (max-width: 840px){
  .admin-container{grid-template-columns: 1fr}
  .admin-main{padding:16px}
  .filters-form{gap:10px}
  .filter-group{min-width:min(100%, 320px)}
  .admin-header{align-items:flex-start}
}
@media (max-width: 520px){
  .btn{width:100%;justify-content:center}
  .filters-form .btn,
  .filters-form .btn + .btn{width:auto}
  .admin-table thead{display:none}
  .admin-table, .admin-table tbody, .admin-table tr, .admin-table td{display:block;width:100%}
  .admin-table tbody tr{padding:10px 12px}
  .admin-table tbody td{border:none;padding:6px 0}
  .admin-table tbody tr + tr{border-top:1px solid var(--border)}
  .admin-table td.actions{margin-top:8px}
}

    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-bell"></i> All Reminders</h1>
                <div class="admin-breadcrumb">
                    <a href="dashboard.php">Dashboard</a> > All Reminders
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="scheduled" <?php echo $statusFilter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>User:</label>
                        <select name="user">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Search:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>" 
                               placeholder="Search subject, message, or recipients...">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="reminders.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </form>
            </div>

            <!-- Results Summary -->
            <div class="results-summary">
                <p>Showing <?php echo count($reminders); ?> of <?php echo $totalResults; ?> reminders</p>
            </div>

            <!-- Reminders Table -->
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>User</th>
                            <th>Recipients</th>
                            <th>Scheduled</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reminders)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No reminders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reminders as $reminder): ?>
                                <tr>
                                    <td><?php echo $reminder['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($reminder['subject'], 0, 50)); ?></strong>
                                        <?php if (strlen($reminder['subject']) > 50): ?>...<?php endif; ?>
                                        <br>
                                        <small class="text-muted">
                                            Created: <?php echo date('M j, Y g:i A', strtotime($reminder['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($reminder['user_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($reminder['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $recipients = explode(',', $reminder['recipients']);
                                        echo htmlspecialchars(trim($recipients[0]));
                                        if (count($recipients) > 1): 
                                        ?>
                                            <br><small class="text-muted">+<?php echo count($recipients) - 1; ?> more</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($reminder['scheduled_time']): ?>
                                            <?php echo date('M j, Y g:i A', strtotime($reminder['scheduled_time'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not scheduled</span>
                                        <?php endif; ?>
                                        <?php if ($reminder['is_recurring']): ?>
                                            <br><span class="badge badge-info"><i class="fas fa-sync"></i> Recurring</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($reminder['status']) {
                                            case 'pending':
                                                $statusClass = 'badge-warning';
                                                break;
                                            case 'scheduled':
                                                $statusClass = 'badge-info';
                                                break;
                                            case 'processing':
                                                $statusClass = 'badge-primary';
                                                break;
                                            case 'completed':
                                                $statusClass = 'badge-success';
                                                break;
                                            case 'failed':
                                                $statusClass = 'badge-danger';
                                                break;
                                            case 'inactive':
                                                $statusClass = 'badge-secondary';
                                                break;
                                            default:
                                                $statusClass = 'badge-light';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($reminder['status'] ?: 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($reminder['delivery_method'] === 'both'): ?>
                                            <i class="fas fa-envelope text-primary"></i>
                                            <i class="fas fa-sms text-warning"></i>
                                        <?php elseif ($reminder['delivery_method'] === 'sms'): ?>
                                            <i class="fas fa-sms text-warning"></i> SMS
                                        <?php else: ?>
                                            <i class="fas fa-envelope text-primary"></i> Email
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="../view_reminder.php?id=<?php echo $reminder['id']; ?>" 
                                           class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($reminder['status'] !== 'completed'): ?>
                                            <?php if ($reminder['status'] === 'inactive'): ?>
                                                <a href="reminders.php?action=activate&id=<?php echo $reminder['id']; ?>" 
                                                   class="btn btn-sm btn-success" title="Activate">
                                                    <i class="fas fa-play"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="reminders.php?action=deactivate&id=<?php echo $reminder['id']; ?>" 
                                                   class="btn btn-sm btn-warning" title="Deactivate">
                                                    <i class="fas fa-pause"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="reminders.php?action=delete&id=<?php echo $reminder['id']; ?>" 
                                               class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this reminder?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="page-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="js/admin.js"></script>
</body>
</html>