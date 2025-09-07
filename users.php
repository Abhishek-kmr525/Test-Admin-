<?php
/**
 * Admin Panel - User Management
 */

session_start();

if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

require_once '../db_connect.php';

// Handle user actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $userId = (int)$_POST['user_id'];
    
    switch ($action) {
        case 'update_subscription':
            $subscriptionType = $_POST['subscription_type'];
            $planType = $_POST['plan_type'] ?? null;
            $expiryDate = $_POST['expiry_date'] ?? null;
            
            $stmt = $conn->prepare("UPDATE users SET subscription_type = ?, plan_type = ?, subscription_expiry = ? WHERE id = ?");
            $stmt->bind_param("sssi", $subscriptionType, $planType, $expiryDate, $userId);
            $stmt->execute();
            
            $_SESSION['admin_message'] = "User subscription updated successfully.";
            break;
            
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $_SESSION['admin_message'] = "User account deactivated.";
            break;
            
        case 'activate':
            $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $_SESSION['admin_message'] = "User account activated.";
            break;
            
        case 'delete':
            // Use a transaction to ensure all or nothing is deleted
            $conn->begin_transaction();
            try {
                // Delete user's reminders first
                $stmt = $conn->prepare("DELETE FROM reminders WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                
                // Delete user's message logs
                $stmt = $conn->prepare("DELETE FROM message_logs WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                
                // Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['admin_message'] = "User and all associated data deleted.";
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $_SESSION['admin_message'] = "Error deleting user. The operation was rolled back.";
                // For debugging, you might want to log the error
                error_log("User deletion failed for user_id {$userId}: " . $exception->getMessage());
            }
            break;
    }
    
    // Redirect back to the same page with existing query parameters to preserve filters and pagination
    $redirectUrl = $_SERVER['REQUEST_URI'];
    header("Location: " . $redirectUrl);
    exit;
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $whereClause .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}

if ($filter != 'all') {
    switch ($filter) {
        case 'paid':
            $whereClause .= " AND subscription_type = 'paid'";
            break;
        case 'free':
            $whereClause .= " AND subscription_type = 'free'";
            break;
        case 'inactive':
            $whereClause .= " AND is_active = 0";
            break;
        case 'expired':
            $whereClause .= " AND subscription_type = 'paid' AND subscription_expiry < NOW()";
            break;
    }
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
if ($params) {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
} else {
    $totalUsers = $conn->query($countQuery)->fetch_assoc()['total'];
}

$totalPages = ceil($totalUsers / $limit);

// Get users
$query = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM reminders WHERE user_id = u.id) as reminder_count,
           (SELECT COUNT(*) FROM message_logs WHERE user_id = u.id AND sent_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) as monthly_messages
    FROM users u 
    $whereClause 
    ORDER BY u.created_at DESC 
    LIMIT $limit OFFSET $offset
";

if ($params) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $users = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
                <h1><i class="fas fa-users"></i> User Management</h1>
                <p>Manage user accounts and subscriptions</p>
            </div>

            <?php if (isset($_SESSION['admin_message'])): ?>
                <div class="admin-message success">
                    <?php echo $_SESSION['admin_message']; unset($_SESSION['admin_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="search-filter-bar">
                <form method="GET" class="search-form">
                    <div class="search-input-group">
                        <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                    </div>
                    
                    <select name="filter" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="paid" <?php echo $filter == 'paid' ? 'selected' : ''; ?>>Premium Users</option>
                        <option value="free" <?php echo $filter == 'free' ? 'selected' : ''; ?>>Free Users</option>
                        <option value="inactive" <?php echo $filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="expired" <?php echo $filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </form>
                
                <div class="results-info">
                    Showing <?php echo count($users); ?> of <?php echo $totalUsers; ?> users
                </div>
            </div>

            <!-- Users Table -->
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Subscription</th>
                            <th>Activity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div class="user-meta">
                                        ID: <?php echo $user['id']; ?> | 
                                        Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="subscription-info">
                                    <?php 
                                    $badgeClass = $user['subscription_type'] == 'paid' ? 'premium' : 'free';
                                    $planDisplay = $user['subscription_type'] == 'paid' ? 
                                        ucfirst($user['plan_type'] ?? 'monthly') . ' Premium' : 
                                        'Free Plan';
                                    ?>
                                    <span class="subscription-badge <?php echo $badgeClass; ?>">
                                        <?php echo $planDisplay; ?>
                                    </span>
                                    <?php if ($user['subscription_type'] == 'paid' && $user['subscription_expiry']): ?>
                                        <div class="expiry-date">
                                            Expires: <?php echo date('M j, Y', strtotime($user['subscription_expiry'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="activity-stats">
                                    <div><?php echo $user['reminder_count']; ?> reminders</div>
                                    <div><?php echo $user['monthly_messages']; ?> messages this month</div>
                                    <div class="last-login">
                                        Last: <?php echo $user['last_login'] ? date('M j, g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $statusClass = $user['is_active'] ? 'active' : 'inactive';
                                $statusText = $user['is_active'] ? 'Active' : 'Inactive';
                                
                                // Check if subscription is expired
                                if ($user['subscription_type'] == 'paid' && $user['subscription_expiry'] && strtotime($user['subscription_expiry']) < time()) {
                                    $statusClass = 'expired';
                                    $statusText = 'Expired';
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" onclick="editUser(<?php echo $user['id']; ?>)" class="action-btn edit" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" onclick="viewUser(<?php echo $user['id']; ?>)" class="action-btn view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($user['is_active']): ?>
                                    <button type="button" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'deactivate')" class="action-btn deactivate" title="Deactivate">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'activate')" class="action-btn activate" title="Activate">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" onclick="deleteUser(<?php echo $user['id']; ?>)" class="action-btn delete" title="Delete User">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>" 
                       class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- User Edit Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST">
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div class="form-group">
                        <label>Subscription Type</label>
                        <select name="subscription_type" id="editSubscriptionType" onchange="togglePlanType()">
                            <option value="free">Free</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="planTypeGroup" style="display: none;">
                        <label>Plan Type</label>
                        <select name="plan_type" id="editPlanType">
                            <option value="monthly">Monthly Premium</option>
                            <option value="annual">Annual Premium</option>
                            <option value="3-year">3-Year Premium Plus</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="expiryGroup" style="display: none;">
                        <label>Expiry Date</label>
                        <input type="date" name="expiry_date" id="editExpiryDate">
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn primary">Update</button>
                        <button type="button" onclick="closeModal()" class="btn secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User View Modal -->
    <div id="viewUserModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3>User Details</h3>
                <span class="modal-close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="userDetails">
                <!-- User details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    function editUser(userId) {
        console.log('Edit user clicked:', userId);
        
        // Check if AJAX files exist first, or provide fallback
        fetch('ajax/get_user.php?id=' + userId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch user data');
                }
                return response.json();
            })
            .then(data => {
                document.getElementById('editUserId').value = userId;
                document.getElementById('editSubscriptionType').value = data.subscription_type || 'free';
                document.getElementById('editPlanType').value = data.plan_type || 'monthly';
                document.getElementById('editExpiryDate').value = data.subscription_expiry || '';
                
                togglePlanType();
                document.getElementById('userModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error fetching user data:', error);
                // Fallback: show modal with empty form
                document.getElementById('editUserId').value = userId;
                document.getElementById('editSubscriptionType').value = 'free';
                document.getElementById('editPlanType').value = 'monthly';
                document.getElementById('editExpiryDate').value = '';
                togglePlanType();
                document.getElementById('userModal').style.display = 'block';
            });
    }

    function viewUser(userId) {
        console.log('View user clicked:', userId);
        
        // Check if AJAX files exist first, or provide fallback
        fetch('ajax/get_user_details.php?id=' + userId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch user details');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('userDetails').innerHTML = html;
                document.getElementById('viewUserModal').style.display = 'block';
            })
            .catch(error => {
                console.error('Error fetching user details:', error);
                // Fallback: show basic user info
                document.getElementById('userDetails').innerHTML = `
                    <div class="user-details-fallback">
                        <h4>User ID: ${userId}</h4>
                        <p>Detailed user information is currently unavailable. Please check the AJAX endpoint files.</p>
                        <p>Expected files: ajax/get_user_details.php</p>
                    </div>
                `;
                document.getElementById('viewUserModal').style.display = 'block';
            });
    }

    function toggleUserStatus(userId, action) {
        console.log('Toggle user status:', userId, action);
        
        const actionText = action === 'activate' ? 'activate' : 'deactivate';
        if (confirm(`Are you sure you want to ${actionText} this user?`)) {
            submitAction(action, userId);
        }
    }

    function deleteUser(userId) {
        console.log('Delete user clicked:', userId);
        
        if (confirm('Are you sure you want to delete this user? This will permanently delete all their data including reminders and cannot be undone.')) {
            submitAction('delete', userId);
        }
    }

    function submitAction(action, userId) {
        // Create form element
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        // Create hidden inputs
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        
        // Append inputs to form
        form.appendChild(actionInput);
        form.appendChild(userIdInput);
        
        // Append form to body and submit
        document.body.appendChild(form);
        form.submit();
        
        // Clean up
        document.body.removeChild(form);
    }

    function togglePlanType() {
        const subscriptionType = document.getElementById('editSubscriptionType').value;
        const planTypeGroup = document.getElementById('planTypeGroup');
        const expiryGroup = document.getElementById('expiryGroup');
        
        if (subscriptionType === 'paid') {
            planTypeGroup.style.display = 'block';
            expiryGroup.style.display = 'block';
        } else {
            planTypeGroup.style.display = 'none';
            expiryGroup.style.display = 'none';
        }
    }

    function closeModal() {
        document.getElementById('userModal').style.display = 'none';
    }

    function closeViewModal() {
        document.getElementById('viewUserModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('userModal');
        const viewModal = document.getElementById('viewUserModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
        if (event.target == viewModal) {
            viewModal.style.display = 'none';
        }
    }

    // Add error handling for form submissions
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        console.log('Form submitted');
        // You can add additional validation here if needed
    });

    // Debug: Log when page loads
    console.log('User management page loaded');
    console.log('Total users displayed:', <?php echo count($users); ?>);
    </script>
</body>
</html>