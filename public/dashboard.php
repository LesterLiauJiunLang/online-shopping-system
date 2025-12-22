<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}


// Include auth functions
require_once '../lib/auth.php';

// Call auth to check login
auth();

// Now redirect based on role
$user_role = $_user['role'] ?? $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'member';

if ($user_role === 'admin') {
    // Redirect admin to admin dashboard
    header("Location: adminDashboard.php");
    exit();
} else {
    // Redirect member to member dashboard
    header("Location: memberDashboard.php");
    exit();
}

// If for some reason redirect fails, show a simple page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Redirecting...</title>
    <meta http-equiv="refresh" content="2;url=memberDashboard.php">
</head>
<body>
    <p>Redirecting to your dashboard...</p>
</body>
</html>
<?php
// Authenticate user
require_once '../lib/auth.php';
auth();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <?php require_once '../includes/header.php'; ?>
    <link rel="stylesheet" href="<?php echo base('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo base('assets/css/emailVerification.css'); ?>">
</head>
<body>
    <div class="container">
        <h2>Dashboard</h2>
        
        <!-- Welcome message -->
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
    </div>

    <?php include '../includes/verification_popup.php'; ?>
    
    <?php require_once '../includes/footer.php'; ?>

</body>
</html>