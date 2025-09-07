<?php
// Get current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="admin-sidebar">
    <nav>
        <ul class="sidebar-menu">
            <li>
                <a href="index.php" class="<?php echo $current_page == 'index' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="users.php" class="<?php echo $current_page == 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    User Management
                </a>
            </li>
            <li>
                <a href="reminders.php" class="<?php echo $current_page == 'reminders' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    All Reminders
                </a>
            </li>
            <li>
                <a href="message_logs.php" class="<?php echo $current_page == 'message_logs' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open-text"></i>
                    Message Logs
                </a>
            </li>
            <li>
                <a href="analytics.php" class="<?php echo $current_page == 'analytics' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    Analytics
                </a>
            </li>
            <li>
                <a href="system_logs.php" class="<?php echo $current_page == 'system_logs' ? 'active' : ''; ?>">
                    <i class="fas fa-list-alt"></i>
                    System Logs
                </a>
            </li>
            <li>
                <a href="email_templates.php" class="<?php echo $current_page == 'email_templates' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    Email Templates
                </a>
            </li>
            <li>
                <a href="categories.php" class="<?php echo $current_page == 'categories' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    Categories
                </a>
            </li>
            <li>
                <a href="support.php" class="<?php echo $current_page == 'support' ? 'active' : ''; ?>">
                    <i class="fas fa-headset"></i>
                    Support
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?php echo $current_page == 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    System Settings
                </a>
            </li>
            <li>
                <a href="database.php" class="<?php echo $current_page == 'database' ? 'active' : ''; ?>">
                    <i class="fas fa-database"></i>
                    Database Tools
                </a>
            </li>
            <li>
                <a href="backup.php" class="<?php echo $current_page == 'backup' ? 'active' : ''; ?>">
                    <i class="fas fa-download"></i>
                    Backup & Restore
                </a>
            </li>
            <li>
                <a href="admin_users.php" class="<?php echo $current_page == 'admin_users' ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i>
                    Admin Users
                </a>
            </li>
        </ul>
    </nav>
</aside>