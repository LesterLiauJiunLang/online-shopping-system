<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the auth library files exist - if not, use fallback
$authLibPath = __DIR__ . '/../lib/auth.php';
$helpersLibPath = __DIR__ . '/../lib/helpers.php';
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/online-shopping-system-product/';

// Initialize auth variable
$auth = null;

// Try to include auth.php if it exists
if (file_exists($authLibPath)) {
    require_once $authLibPath;
    if (class_exists('Auth')) {
        $auth = new Auth();
    }
}

// If auth.php doesn't exist, create a simple fallback
if (!$auth) {
    class SimpleAuth {
        public function isLoggedIn() {
            return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        }
        
        public function isAdmin() {
            return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
        }
    }
    $auth = new SimpleAuth();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Only include your existing auth.css --> 
    <title><?php echo isset($page_title) ? $page_title : 'Online Shopping System'; ?></title>
    <link rel="stylesheet" href="/online-shopping-system-product/assets/css/style.css">
    <link rel="stylesheet" href="/online-shopping-system-product/assets/css/auth.css">
    <link rel="stylesheet" href="/online-shopping-system-product/assets/css/admin.css">
    <style>
        /* Add minimal header styles here */
        .header {
            background: #5a4bff;
            color: white;
            padding: 15px;
            margin-bottom: 20px;
        }
        .header a {
            color: white;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <strong>Online Shopping</strong>
        </div>
        <div class="nav-links">
            <?php if ($auth->isLoggedIn()): ?>
                <span class="welcome">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?> 
                    (<?php echo htmlspecialchars($_SESSION['role'] ?? 'member'); ?>)
                </span>
                <?php if ($auth->isAdmin()): ?>
                    <a href="admin/members.php">Manage Members</a>
                <?php endif; ?>
                <!-- Cart link for logged-in members -->
                <a href="cart.php">
                🛒 Cart 
                <?php 
                // Check if cart class exists and user is logged in
                if (isset($_SESSION['user_id']) && class_exists('Cart')) {
                    // Assuming you have a Cart class with getCartItemCount method
                    $cart_count = $cart->getCartItemCount($_SESSION['user_id']);
                    if ($cart_count > 0) {
                        echo '<span class="cart-count">(' . $cart_count . ')</span>';
                    }
                } elseif (isset($_SESSION['user_id'])) {
                    // Fallback if Cart class doesn't exist
                    // You might want to implement a database query here
                    // or leave it without count for now
                }
                ?>
                </a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="container">