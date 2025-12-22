<?php
// public/admin/admin_sidebar.php
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <h2>Admin Panel</h2>
        <div class="admin-info">
            <img src="<?php echo base('assets/uploads/' . ($_SESSION['user']['profile_photo'] ?: 'avatar.png')); ?>" 
                 alt="Admin" class="admin-avatar">
            <div>
                <p class="admin-name"><?php echo encode($_SESSION['user']['username']); ?></p>
                <p class="admin-role"><?php echo ucfirst($_SESSION['user']['role']); ?></p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="icon-dashboard"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' || 
                                                basename($_SERVER['PHP_SELF']) == 'member_details.php' ? 'active' : ''; ?>">
                    <i class="icon-users"></i> Users
                </a>
            </li>
            <li>
                <a href="products.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                    <i class="icon-products"></i> Products
                </a>
            </li>
            <li>
                <a href="categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                    <i class="icon-categories"></i> Categories
                </a>
            </li>
            <li>
                <a href="orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                    <i class="icon-orders"></i> Orders
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="icon-reports"></i> Reports
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="icon-settings"></i> Settings
                </a>
            </li>
            <li class="divider"></li>
            <li>
                <a href="<?php echo base('public/'); ?>" target="_blank">
                    <i class="icon-store"></i> View Store
                </a>
            </li>
            <li>
                <a href="<?php echo base('public/logout.php'); ?>">
                    <i class="icon-logout"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <p>Version 1.0.0</p>
    </div>
</aside>