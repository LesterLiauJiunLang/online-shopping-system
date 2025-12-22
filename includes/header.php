<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set base URL for consistent navigation
$base_url = '/online-shopping-system';

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
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/notifications.css">
    <style>
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
    </style>
</head>
<body>
    
    <div class="header">
<<<<<<< Updated upstream
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
    </div>
    <div class="container">