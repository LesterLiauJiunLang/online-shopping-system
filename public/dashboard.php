<?php
// dashboard.php - ADD DEBUGGING

// Debug: Log what's happening
error_log("DASHBOARD: Starting...");
error_log("DASHBOARD: Session ID: " . session_id());

// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("DASHBOARD: Session data: " . json_encode($_SESSION));

// Check if we have session data
if (empty($_SESSION)) {
    error_log("DASHBOARD: EMPTY SESSION - redirecting to login");
    header("Location: login.php");
    exit();
}

// Try auth
require_once '../lib/auth.php';
error_log("DASHBOARD: Calling auth()...");

// Call auth
auth();

// If we get here, auth passed
error_log("DASHBOARD: auth() passed!");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <?php require_once '../includes/header.php'; ?>
</head>
<body>
    <div class="container">
        <h2>Dashboard</h2>
        
        <!-- Welcome message using $_user (Practical 7 style) -->
        <p>Welcome, <?php echo encode($_user['name']); ?>!</p>
        
        
        <!-- Role badge -->
        <span class="role-badge"><?php echo $_user['role']; ?></span>
        
        <div class="nav">
            <h3>Quick Links:</h3>
            
            <!-- COMMON LINKS (All users) -->
            <ul>
                <li><a href="profile.php">Update Profile</a></li>
                <li><a href="change-password.php">Change Password</a></li>
                
                <!-- ADMIN-SPECIFIC LINKS -->
                <?php if ($_user['role'] === 'admin'): ?>
                    <li><a href="admin/users.php">Manage Members</a></li>
                    <li><a href="admin/products.php">Manage Products</a></li>
                    <li><a href="admin/orders.php">Manage Orders</a></li>
                    <li><a href="admin/reports.php">View Reports</a></li>
                    <li><a href="admin/settings.php">System Settings</a></li>
                    
                <!-- MEMBER-SPECIFIC LINKS -->    
                <?php else: ?>
                    <li><a href="shop.php">Browse Products</a></li>
                    <li><a href="cart.php">Shopping Cart</a></li>
                    <li><a href="orders.php">My Orders</a></li>
                    <li><a href="wishlist.php">My Wishlist</a></li>
                    <li><a href="reviews.php">My Reviews</a></li>
                <?php endif; ?>
                
                <!-- MORE COMMON LINKS -->
                <li><a href="messages.php">Messages</a></li>
                <li><a href="settings.php">Account Settings</a></li>
            </ul>
        </div>
        
        <!-- STATISTICS SECTION (Role-based) -->
        <div class="stats">
            <h3>Quick Stats:</h3>
            
            <?php if ($_user['role'] === 'admin'): ?>
                <!-- Admin Statistics -->
                <div class="stat-card">
                    <h4>Total Members</h4>
                    <p>1,234</p>
                </div>
                <div class="stat-card">
                    <h4>Pending Orders</h4>
                    <p>56</p>
                </div>
                <div class="stat-card">
                    <h4>Low Stock Items</h4>
                    <p>12</p>
                </div>
                
            <?php else: ?>
                <!-- Member Statistics -->
                <div class="stat-card">
                    <h4>My Orders</h4>
                    <p>15</p>
                </div>
                <div class="stat-card">
                    <h4>Cart Items</h4>
                    <p>3</p>
                </div>
                <div class="stat-card">
                    <h4>Wishlist Items</h4>
                    <p>8</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- RECENT ACTIVITY -->
        <div class="recent-activity">
            <h3>Recent Activity</h3>
            <?php if ($_user['role'] === 'admin'): ?>
                <ul>
                    <li>New member registered: John Doe</li>
                    <li>Order #1234 placed</li>
                    <li>Product "iPhone 15" updated</li>
                </ul>
            <?php else: ?>
                <ul>
                    <li>You viewed "Wireless Earbuds"</li>
                    <li>Order #5678 shipped</li>
                    <li>Your review was helpful to 3 people</li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    
    <?php require_once '../includes/footer.php'; ?>
</body>
</html>