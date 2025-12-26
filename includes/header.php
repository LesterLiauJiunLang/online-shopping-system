<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Check session variables
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set base URL for consistent navigation
$base_url = '/online-shopping-system';

// Include the auth.php file from lib directory
$authLibPath = __DIR__ . '/../lib/auth.php';

// Initialize auth variable
$auth = null;

// Try to include auth.php if it exists
if (file_exists($authLibPath)) {
    require_once $authLibPath;
    
    // Check if the auth functions exist (functions-based system)
    // OR if we need to create an Auth class instance
    if (function_exists('is_logged_in')) {
        // Functions-based system
        $auth = new stdClass();
        $auth->isLoggedIn = function() {
            return is_logged_in();
        };
        $auth->isAdmin = function() {
            return is_admin();
        };
    } else {
        // Try to use SimpleAuth fallback
        $auth = new SimpleAuth();
    }
} else {
    // Create a simple fallback
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

// Alternative: Direct session check (simpler approach)
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get user's profile photo from session
$profile_photo = $_SESSION['photo'] ?? 'avatar.png';

// FIXED: Use the same path structure as your profile page
$fallback_url = $base_url . '/public/assets/images/avatar.png';

// Check multiple possible locations for the profile photo
if (!empty($profile_photo) && $profile_photo !== 'avatar.png') {
    // Option 1: Check in the uploads directory (absolute path like profile page)
    $uploads_dir_abs = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile/';
    $uploads_file_abs = $uploads_dir_abs . $profile_photo;
    
    // Option 2: Check in the project's uploads directory
    $uploads_dir_project = $_SERVER['DOCUMENT_ROOT'] . $base_url . '/public/uploads/profile/';
    $uploads_file_project = $uploads_dir_project . $profile_photo;
    
    // Choose the correct URL based on where the file actually is
    if (file_exists($uploads_file_abs)) {
        // File is in absolute uploads path (like your profile page shows)
        $img_url = '/uploads/profile/' . htmlspecialchars($profile_photo);
    } elseif (file_exists($uploads_file_project)) {
        // File is in project uploads path
        $img_url = $base_url . '/public/uploads/profile/' . htmlspecialchars($profile_photo);
    } else {
        // File not found, use fallback
        $img_url = $fallback_url;
    }
} else {
    $img_url = $fallback_url;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'GadgetMart'; ?></title>
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
        /* Profile Picture Styles - UPDATED */
        .profile-pic-link {
            display: flex;
            align-items: center;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s;
        }
        .profile-pic-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
            text-decoration: none;
        }
        .profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background-color: rgba(255, 255, 255, 0.1);
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        /* Optional: Add a tooltip for the profile picture */
        .profile-pic-link[title]:hover::after {
            content: attr(title);
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-top: 45px;
            margin-left: -20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    
    <div class="header">
        <div class="logo">
            <a href="<?php echo $base_url; ?>/public/index.php">🛒 GadgetMart</a>
        </div>
        <div class="nav-links">
            <?php if ($isLoggedIn): ?>
                <div class="user-menu">
                    <!-- Profile Picture ONLY (no text) -->
                    <a href="<?php echo $base_url; ?>/public/profile.php" class="profile-pic-link" title="Go to Profile">
                        <img src="<?php echo $img_url; ?>" 
                             alt="Profile Picture" 
                             class="profile-pic"
                             onerror="this.onerror=null; this.src='<?php echo $fallback_url; ?>';">
                    </a>
                    
                    <span class="welcome">
                        Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </span>
                    
                    <?php if ($isAdmin): ?>
                        <div class="admin-menu">
                            <a href="<?php echo $base_url; ?>/public/adminDashboard.php">Dashboard</a>
                            <a href="<?php echo $base_url; ?>/public/admin/products.php">Products</a>
                            <a href="<?php echo $base_url; ?>/public/admin/product_add.php">Add Product</a>
                            <a href="<?php echo $base_url; ?>/public/admin/orders.php">Orders</a>
                            <a href="<?php echo $base_url; ?>/public/admin/categories.php">Categories</a>
                            <a href="<?php echo $base_url; ?>/public/admin/users.php">Users</a>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>/public/dashboard.php">Dashboard</a>
                        <a href="<?php echo $base_url; ?>/public/members/products.php">Shop</a>
                        <a href="<?php echo $base_url; ?>/public/members/cart.php">Cart</a>
                        <a href="<?php echo $base_url; ?>/public/members/order_detail.php">Order</a>
                    <?php endif; ?>
                    
                    <a href="<?php echo $base_url; ?>/public/logout.php" class="logout-btn">Logout</a>
                </div>
            <?php else: ?>
                <a href="<?php echo $base_url; ?>/public/index.php">Home</a>
                <a href="<?php echo $base_url; ?>/public/products.php">Products</a>
                <a href="<?php echo $base_url; ?>/public/login.php">Login</a>
                <a href="<?php echo $base_url; ?>/public/register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="container">