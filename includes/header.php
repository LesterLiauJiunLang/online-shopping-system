<?php
<<<<<<< HEAD
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../lib/base.php';  // Fixed path
require_once __DIR__ . '/../lib/auth.php'; 
check_remember_token(); 

=======
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set base URL for consistent navigation
$base_url = '/online-shopping-system-product';

// Check if the auth library files exist - if not, use fallback
$authLibPath = __DIR__ . '/../lib/auth.php';
$helpersLibPath = __DIR__ . '/../lib/helpers.php';

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
>>>>>>> origin/LoginAndProductPart
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Only include your existing auth.css --> 
    <title><?php echo isset($page_title) ? $page_title : 'Online Shopping System'; ?></title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/auth.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/admin.css">
    <style>
<<<<<<< HEAD
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
        .header { background: #333; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; margin: 0 15px; }
        .header img { width: 36px; height: 36px; border-radius: 5px; border: 1px solid #666; object-fit: cover; }
        .header .profile { display: flex; align-items: center; gap: 10px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background: white; border-radius: 5px; }
=======
        /* Add minimal header styles here */
        .header {
            background: #5a4bff;
            color: white;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5em;
            font-weight: bold;
        }
        .logo a {
            color: white;
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .nav-links .logout-btn {
            background-color: #ff4757;
        }
        .nav-links .logout-btn:hover {
            background-color: #ff3742;
        }
        .welcome {
            margin-right: 10px;
            font-size: 0.9em;
        }
        .admin-menu {
            display: flex;
            gap: 10px;
            margin-right: 10px;
        }
>>>>>>> origin/LoginAndProductPart
    </style>
</head>
<body>
    <div class="header">
<<<<<<< HEAD
        <div>
            <?php if (is_logged_in()): ?>
                <a href="dashboard.php">Dashboard</a>
                <?php if (is_admin()): ?>
                    <a href="admin/users.php">Manage Members</a>
                <?php else: ?>
                    <a href="shop.php">Browse Products</a>
                    <a href="cart.php">Cart</a>
                    <a href="orders.php">My Orders</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>

        <?php if (is_logged_in()): ?>
        <div class="profile">
            <a href="profile.php">
                <img src="<?= base('uploads/profile/' . encode($_SESSION['photo'] ?? 'avatar.png')) ?>" alt="Profile">
            </a>
            <span><?= encode($_SESSION['username']) ?> (<?= encode($_SESSION['role']) ?>)</span>
            <a href="logout.php">Logout</a>
        </div>
        <?php endif; ?>
=======
        <div class="logo">
            <a href="<?php echo $base_url; ?>/public/index.php">🛒 Online Shopping</a>
        </div>
        <div class="nav-links">
            <?php if ($auth->isLoggedIn()): ?>
                <span class="welcome">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                </span>
                
                <?php if ($auth->isAdmin()): ?>
                    <div class="admin-menu">
                        <a href="<?php echo $base_url; ?>/public/admin/products.php">Products</a>
                        <a href="<?php echo $base_url; ?>/public/admin/product_add.php">Add Product</a>
                        <a href="<?php echo $base_url; ?>/public/admin/orders.php">Orders</a>
                        <a href="<?php echo $base_url; ?>/public/admin/categories.php">Categories</a>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>/public/dashboard.php">Dashboard</a>
                    <a href="<?php echo $base_url; ?>/public/products.php">Shop</a>
                    <a href="<?php echo $base_url; ?>/public/cart.php">Cart</a>
                <?php endif; ?>
                
                <a href="<?php echo $base_url; ?>/public/logout.php" class="logout-btn">Logout</a>
            <?php else: ?>
                <a href="<?php echo $base_url; ?>/public/index.php">Home</a>
                <a href="<?php echo $base_url; ?>/public/products.php">Products</a>
                <a href="<?php echo $base_url; ?>/public/login.php">Login</a>
                <a href="<?php echo $base_url; ?>/public/register.php">Register</a>
            <?php endif; ?>
        </div>
>>>>>>> origin/LoginAndProductPart
    </div>
    <div class="container">