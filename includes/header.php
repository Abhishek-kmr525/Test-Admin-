<?php
// Ensure this is only included in admin pages with proper session
if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    // Don't show header if not properly authenticated
    return;
}
?>
<header class="admin-header">
    <div class="header-content">
        <a href="index.php" class="admin-logo">
            <i class="fas fa-shield-alt"></i>
            Admin Panel
        </a>
        
        <div class="admin-user-info">
            <span class="admin-welcome">
                Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username']); ?>
            </span>
            <nav class="admin-nav">
                <a href="../dashboard.php" target="_blank" title="View Main Site">
                    <i class="fas fa-external-link-alt"></i> Main Site
                </a>
                <a href="logout.php" title="Logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
    </div>
</header>