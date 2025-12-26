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

// If auth.php doesn't exist, create a simple fallback with SUPPORT role
if (!$auth) {
    class SimpleAuth {
        public function isLoggedIn() {
            return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        }
        
        public function isAdmin() {
            return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
        }
        
        public function isSupport() {
            return isset($_SESSION['role']) && $_SESSION['role'] === 'support';
        }
        
        public function getRole() {
            return $_SESSION['role'] ?? 'guest';
        }
    }
    $auth = new SimpleAuth();
}

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

// Get user role
$user_role = $auth->getRole();

// Get cart count for logged in users
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $db = db();
        $stmt = $db->prepare("
            SELECT SUM(quantity) as total_items 
            FROM shopping_cart 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $cart_count = $result['total_items'] ?? 0;
    } catch (Exception $e) {
        $cart_count = 0;
    }
} else {
    $cart_count = 0;
}

// Get unread notification count for logged in users
$unread_count = 0;

if ($auth->isLoggedIn()) {
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $db = db();
        
        // Get total unread notifications (including support replies AND internal chat)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $unread_count = $result['count'] ?? 0;
        
        // For support agents: Also check for urgent tickets if they want to show it
        if ($auth->isSupport()) {
            $urgent_stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM support_tickets 
                WHERE status = 'urgent' 
                AND (assigned_to = ? OR assigned_to IS NULL)
            ");
            $urgent_stmt->execute([$_SESSION['user_id']]);
            $urgent_result = $urgent_stmt->fetch();
            $urgent_count = $urgent_result['count'] ?? 0;
        }
    } catch (Exception $e) {
        // Silently fail for notifications
    }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            position: relative;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo {
            font-size: 1.5em;
            font-weight: bold;
            flex-shrink: 0;
        }
        .logo a {
            color: white;
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex: 1;
            min-width: 0;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.3s;
            white-space: nowrap;
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
            white-space: nowrap;
        }
        
        /* Role-specific menus */
        .admin-menu, .support-menu, .member-menu {
            display: flex;
            gap: 10px;
            margin-right: 10px;
            flex-wrap: wrap;
        }
        
        /* Role badges */
        .role-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 10px;
        }
        .role-badge-admin {
            background: #ffc107;
            color: #212529;
        }
        .role-badge-support {
            background: #17a2b8;
            color: white;
        }
        .role-badge-member {
            background: #28a745;
            color: white;
        }
        
        /* Profile Picture Styles */
        .profile-pic-link {
            display: flex;
            align-items: center;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s;
            flex-shrink: 0;
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
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        /* Notification Bell Styles */
        .notification-link {
            position: relative;
            display: inline-block;
            color: white;
            text-decoration: none;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.3s;
            flex-shrink: 0;
        }
        
        .notification-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
            text-decoration: none;
        }
        
        .notification-bell {
            font-size: 1.5em;
        }
        
        .notification-count {
            position: absolute;
            top: 0;
            right: 0;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
            font-weight: bold;
            border: 2px solid #5a4bff;
        }
        
        /* Support-specific badge */
        .support-badge {
            background: #e74c3c;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            font-weight: bold;
            margin-left: 5px;
        }
        
        /* Cart count badge */
        .cart-count {
            background: #e74c3c;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
            font-weight: bold;
            margin-left: 5px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .header {
                padding: 12px 10px;
                gap: 10px;
            }
            
            .admin-menu, .support-menu, .member-menu {
                gap: 6px;
                margin-right: 5px;
            }
            
            .nav-links a {
                padding: 5px 8px;
                font-size: 0.9em;
            }
            
            .user-menu {
                gap: 8px;
            }
        }
        
        @media (max-width: 992px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .logo {
                text-align: center;
                margin-bottom: 10px;
            }
            
            .nav-links {
                justify-content: center;
            }
            
            .user-menu {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .support-menu, .admin-menu, .member-menu {
                justify-content: center;
                margin-right: 0;
                margin-bottom: 5px;
            }
            
            .welcome {
                margin-right: 0;
                margin-bottom: 5px;
                text-align: center;
                width: 100%;
            }
            
            .user-menu {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .notification-link, .profile-pic-link {
                order: -1;
            }
            
            .logout-btn {
                margin-left: 0;
                margin-top: 5px;
            }
        }
        
        @media (max-width: 480px) {
            .admin-menu, .support-menu, .member-menu {
                flex-direction: column;
                align-items: center;
                width: 100%;
            }
            
            .admin-menu a, .support-menu a, .member-menu a {
                width: 100%;
                text-align: center;
                padding: 8px;
            }
            
            .nav-links a {
                font-size: 0.85em;
                padding: 8px;
                width: 100%;
                text-align: center;
            }
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
            <?php if ($auth->isLoggedIn()): ?>
                <div class="user-menu">
                    <!-- Notification Bell for ALL logged in users (admin, support, and member) -->
                    <a href="<?php echo $base_url; ?>/public/notifications.php" class="notification-link" title="Notifications">
                        <i class="fas fa-bell notification-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-count"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Profile Picture -->
                    <a href="<?php echo $base_url; ?>/public/profile.php" class="profile-pic-link" title="Go to Profile">
                        <img src="<?php echo $img_url; ?>" 
                             alt="Profile Picture" 
                             class="profile-pic"
                             onerror="this.onerror=null; this.src='<?php echo $fallback_url; ?>';"
                             data-fallback="<?php echo $fallback_url; ?>">
                    </a>
                    
                    <span class="welcome">
                        Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        <span class="role-badge role-badge-<?php echo $user_role; ?>">
                            <?php echo ucfirst($user_role); ?>
                        </span>
                    </span>
                    
                    <?php if ($auth->isAdmin()): ?>
                        <!-- ADMIN MENU -->
                        <div class="admin-menu">
                            <a href="<?php echo $base_url; ?>/public/adminDashboard.php">Dashboard</a>
                            <a href="<?php echo $base_url; ?>/public/admin/products.php">Products</a>
                            <a href="<?php echo $base_url; ?>/public/admin/product_add.php">Add Product</a>
                            <a href="<?php echo $base_url; ?>/public/admin/orders.php">Orders</a>
                            <a href="<?php echo $base_url; ?>/public/admin/categories.php">Categories</a>
                            <a href="<?php echo $base_url; ?>/public/admin/users.php">Users</a>
                            <a href="<?php echo $base_url; ?>/public/admin/support_chat.php">Chat</a>
                        </div>
                        
                    <?php elseif ($auth->isSupport()): ?>
                        <!-- SUPPORT AGENT MENU -->
                        <div class="support-menu">
                            <a href="<?php echo $base_url; ?>/public/supportDashboard.php">Dashboard</a>
                            <a href="<?php echo $base_url; ?>/public/support/tickets.php">Tickets</a>
                            <a href="<?php echo $base_url; ?>/public/support/tickets.php?status=urgent">
                                Urgent
                                <?php if (isset($urgent_count) && $urgent_count > 0): ?>
                                    <span class="support-badge"><?php echo $urgent_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo $base_url; ?>/public/support/tickets.php?status=resolved">Resolved</a>
                            <a href="<?php echo $base_url; ?>/public/admin/support_chat.php">Chat</a>
                        </div>
                        
                    <?php else: ?>
                        <!-- MEMBER/CUSTOMER MENU -->
                        <div class="member-menu">
                            <a href="<?php echo $base_url; ?>/public/memberDashboard.php">Dashboard</a>
                            <a href="<?php echo $base_url; ?>/public/members/products.php">Shop</a>
                            <a href="<?php echo $base_url; ?>/public/members/cart.php">
                                🛒 Cart 
                                <?php if ($cart_count > 0): ?>
                                    <span class="cart-count"><?= $cart_count ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo $base_url; ?>/public/members/order_tracking.php">My Orders</a>
                            <a href="<?php echo $base_url; ?>/public/support/create_ticket.php">Support</a>
                        </div>
                    <?php endif; ?>
                    
                    <a href="<?php echo $base_url; ?>/public/logout.php" class="logout-btn">Logout</a>
                </div>
            <?php else: ?>
                <!-- GUEST MENU (not logged in) -->
                <a href="<?php echo $base_url; ?>/public/index.php">Home</a>
                <a href="<?php echo $base_url; ?>/public/products.php">Products</a>
                <a href="<?php echo $base_url; ?>/public/login.php">Login</a>
                <a href="<?php echo $base_url; ?>/public/register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="container">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle broken profile images
            $('.profile-pic').on('error', function() {
                var fallbackUrl = $(this).data('fallback');
                if (fallbackUrl) {
                    $(this).attr('src', fallbackUrl);
                }
            });
            
            // Auto-hide notifications after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>