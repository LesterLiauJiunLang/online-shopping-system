<?php
// public/admin/member_details.php
require_once __DIR__ . '/../../lib/base.php';
require_once __DIR__ . '/../../lib/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
require_admin();

// Get member ID from query string
$member_id = get('id', 0);

if (!$member_id) {
    redirect('users.php');
}

// Initialize variables
$member = null;
$orders = [];
$activities = [];
$total_orders = 0;
$total_activities = 0;

// Check if this is an AJAX request for orders or activities
$is_ajax_orders = isset($_GET['ajax']) && $_GET['ajax'] == 'orders';
$is_ajax_activities = isset($_GET['ajax']) && $_GET['ajax'] == 'activities';

// Pagination and filtering variables
$orders_page = get('orders_page', 1);
$activities_page = get('activities_page', 1);
$per_page = 5; // 5 items per page

// Filter variables
$order_status = get('order_status', '');
$order_min_amount = get('order_min_amount', '');
$order_max_amount = get('order_max_amount', '');
$order_date_from = get('order_date_from', '');
$order_date_to = get('order_date_to', '');

// Handle member actions
if (is_post()) {
    $action = post('action');
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $_SESSION['notification'] = [
            'message' => "Security token invalid. Please try again.",
            'type' => 'error'
        ];
    } elseif ($action) {
        try {
            switch ($action) {
                case 'update_profile':
                    // Handle profile update
                    $username = post('username');
                    $email = post('email');
                    $is_active = post('is_active', 0);
                    
                    // Validate input
                    if (empty($username) || empty($email)) {
                        $_SESSION['notification'] = [
                            'message' => "Username and email are required.",
                            'type' => 'error'
                        ];
                        break;
                    }
                    
                    // Check if username or email already exists (excluding current user)
                    $check_username = $_db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
                    $check_username->execute([$username, $member_id]);
                    if ($check_username->fetchColumn() > 0) {
                        $_SESSION['notification'] = [
                            'message' => "Username already exists.",
                            'type' => 'error'
                        ];
                        break;
                    }
                    
                    $check_email = $_db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
                    $check_email->execute([$email, $member_id]);
                    if ($check_email->fetchColumn() > 0) {
                        $_SESSION['notification'] = [
                            'message' => "Email already exists.",
                            'type' => 'error'
                        ];
                        break;
                    }
                    
                    // Update member
                    $stmt = $_db->prepare("UPDATE users SET username = ?, email = ?, is_active = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$username, $email, $is_active, $member_id]);
                    
                    $_SESSION['notification'] = [
                        'message' => "Profile updated successfully.",
                        'type' => 'success'
                    ];
                    break;
                    
                case 'reset_password':
                    // Generate random password
                    $new_password = bin2hex(random_bytes(8)); // 16 characters
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $stmt = $_db->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$password_hash, $member_id]);
                    
                    // Send email with new password
                    if ($member) {
                        $mail = get_mail();
                        $mail->addAddress($member['email'], $member['username']);
                        $mail->Subject = 'Password Reset - Tech Gadgets Shop';
                        $mail->Body = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .password { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 18px; text-align: center; margin: 20px 0; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <h2>Password Reset</h2>
                                <p>Hello " . encode($member['username']) . ",</p>
                                <p>Your password has been reset by an administrator.</p>
                                <div class='password'>" . encode($new_password) . "</div>
                                <p>Please login and change your password immediately.</p>
                                <p><strong>Note:</strong> For security reasons, please change this password after logging in.</p>
                            </div>
                        </body>
                        </html>";
                        
                        if ($mail->send()) {
                            $_SESSION['notification'] = [
                                'message' => "Password reset successfully. New password has been emailed to the member.",
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['notification'] = [
                                'message' => "Password reset but email failed to send. New password: " . $new_password,
                                'type' => 'error'
                            ];
                        }
                    }
                    break;
                    
                case 'reset_attempts':
                    $stmt = $_db->prepare("UPDATE users SET login_attempts = 0, last_attempt = NULL WHERE user_id = ?");
                    $stmt->execute([$member_id]);
                    
                    $_SESSION['notification'] = [
                        'message' => "Login attempts reset successfully.",
                        'type' => 'success'
                    ];
                    break;
                    
                case 'verify_email':
                    $stmt = $_db->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE user_id = ?");
                    $stmt->execute([$member_id]);
                    
                    $_SESSION['notification'] = [
                        'message' => "Email verified manually.",
                        'type' => 'success'
                    ];
                    break;
                    
                case 'activate':
                    $stmt = $_db->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$member_id]);
                    
                    $_SESSION['notification'] = [
                        'message' => "Account activated successfully.",
                        'type' => 'success'
                    ];
                    break;
                    
                case 'deactivate':
                    $stmt = $_db->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$member_id]);
                    
                    $_SESSION['notification'] = [
                        'message' => "Account deactivated successfully.",
                        'type' => 'success'
                    ];
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['notification'] = [
                'message' => "Database error: " . $e->getMessage(),
                'type' => 'error'
            ];
        }
    }
    
    // Redirect back to same page to show the toast
    header("Location: member_details.php?id=" . $member_id);
    exit();
}

// Fetch member details
try {
    $stmt = $_db->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'member'");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        $_SESSION['notification'] = [
            'message' => "Member not found.",
            'type' => 'error'
        ];
        redirect('users.php');
    }
    
    // Build order query with filters
    $order_where = "WHERE o.user_id = ?";
    $order_params = [$member_id];
    
    if (!empty($order_status)) {
        $order_where .= " AND o.status = ?";
        $order_params[] = $order_status;
    }
    
    if (!empty($order_min_amount)) {
        $order_where .= " AND o.total_amount >= ?";
        $order_params[] = $order_min_amount;
    }
    
    if (!empty($order_max_amount)) {
        $order_where .= " AND o.total_amount <= ?";
        $order_params[] = $order_max_amount;
    }
    
    if (!empty($order_date_from)) {
        $order_where .= " AND DATE(o.order_date) >= ?";
        $order_params[] = $order_date_from;
    }
    
    if (!empty($order_date_to)) {
        $order_where .= " AND DATE(o.order_date) <= ?";
        $order_params[] = $order_date_to;
    }
    
    // Get total orders count for pagination
    $count_stmt = $_db->prepare("
        SELECT COUNT(*) as total 
        FROM orders o 
        $order_where
    ");
    $count_stmt->execute($order_params);
    $total_orders = $count_stmt->fetch()['total'];
    
    // Calculate offset for pagination
    $order_offset = ($orders_page - 1) * $per_page;
    $total_order_pages = ceil($total_orders / $per_page);
    
    // Fetch member's orders with pagination
    $orders_stmt = $_db->prepare("
        SELECT o.*, COUNT(oi.order_item_id) as item_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.order_id = oi.order_id 
        $order_where
        GROUP BY o.order_id 
        ORDER BY o.order_date DESC 
        LIMIT $per_page OFFSET $order_offset
    ");
    $orders_stmt->execute($order_params);
    $orders = $orders_stmt->fetchAll();
    
    // Get total activities count for pagination
    $activities_count_stmt = $_db->prepare("
        SELECT COUNT(*) as total 
        FROM user_activity 
        WHERE user_id = ?
    ");
    $activities_count_stmt->execute([$member_id]);
    $total_activities = $activities_count_stmt->fetch()['total'];
    
    // Calculate offset for activities pagination
    $activities_offset = ($activities_page - 1) * $per_page;
    $total_activities_pages = ceil($total_activities / $per_page);
    
    // Fetch member's activity history with pagination
    $activities_stmt = $_db->prepare("
        SELECT activity_type, description, ip_address, created_at 
        FROM user_activity 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT $per_page OFFSET $activities_offset
    ");
    $activities_stmt->execute([$member_id]);
    $activities = $activities_stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['notification'] = [
        'message' => "Database error: " . $e->getMessage(),
        'type' => 'error'
    ];
}

// Generate CSRF token
$csrf_token = csrf_token();

// If this is an AJAX request for orders, output only orders content and exit
if ($is_ajax_orders) {
    ob_start();
    ?>
    <!-- Orders Tab Content for AJAX -->
    <div class="tab-content active" id="orders-tab">
        <!-- Order Filters -->
        <div class="filter-section">
            <form method="GET" class="filter-form" id="orderFilterForm">
                <input type="hidden" name="id" value="<?php echo $member_id; ?>">
                <input type="hidden" name="orders_page" value="1">
                
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="order_status">Status</label>
                        <select id="order_status" name="order_status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $order_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $order_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo $order_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $order_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="order_min_amount">Min Amount (RM)</label>
                        <input type="number" id="order_min_amount" name="order_min_amount" 
                               min="0" step="0.01" value="<?php echo $order_min_amount; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="order_max_amount">Max Amount (RM)</label>
                        <input type="number" id="order_max_amount" name="order_max_amount" 
                               min="0" step="0.01" value="<?php echo $order_max_amount; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="order_date_from">From Date</label>
                        <input type="date" id="order_date_from" name="order_date_from" 
                               value="<?php echo $order_date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="order_date_to">To Date</label>
                        <input type="date" id="order_date_to" name="order_date_to" 
                               value="<?php echo $order_date_to; ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="javascript:void(0)" class="btn clear-filter-btn">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>No Orders Found</h3>
                <p>This member hasn't placed any orders yet.</p>
            </div>
        <?php else: ?>
            <div class="table-section">
                <div class="table-header">
                    <h3><i class="fas fa-receipt"></i> Orders</h3>
                    <span class="total-count">Showing <?php echo count($orders); ?> of <?php echo $total_orders; ?> orders</span>
                </div>
                <div class="table-responsive">
                    <table class="members-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo format_date($order['order_date']); ?></td>
                                    <td>RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $order['status'] === 'completed' ? 'active' : ($order['status'] === 'processing' ? 'pending' : 'inactive'); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $order['item_count']; ?></td>
                                    <td>
                                        <span class="verification-badge <?php echo $order['payment_status'] === 'paid' ? 'verified' : 'pending'; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Orders Pagination -->
                <?php if ($total_order_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_order_pages; $i++): ?>
                            <a href="javascript:void(0)" 
                               data-page="<?php echo $i; ?>"
                               class="page-link order-page-link <?php echo $i == $orders_page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
    exit();
}

// If this is an AJAX request for activities, output only activities content and exit
if ($is_ajax_activities) {
    ob_start();
    ?>
    <!-- Activities Tab Content for AJAX -->
    <div class="tab-content active" id="activity-tab">
        <?php if (empty($activities)): ?>
            <div class="empty-activity">
                <i class="fas fa-history"></i>
                <h3>No Activity Found</h3>
                <p>This member hasn't performed any actions yet.</p>
            </div>
        <?php else: ?>
            <!-- Activity Timeline -->
            <div class="activity-timeline">
                <?php foreach ($activities as $activity): ?>
                <div class="activity-item activity-<?php echo $activity['activity_type']; ?>">
                    <div class="activity-dot"></div>
                    <div class="activity-header">
                        <div>
                            <span class="activity-title">
                                <i class="fas fa-<?php 
                                    switch(strtolower($activity['activity_type'])) {
                                        case 'login': echo 'sign-in-alt'; break;
                                        case 'logout': echo 'sign-out-alt'; break;
                                        case 'profile_update': echo 'user-edit'; break;
                                        case 'order_placed': echo 'shopping-cart'; break;
                                        case 'product_view': echo 'eye'; break;
                                        default: echo 'circle';
                                    }
                                ?>"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                <?php if (!empty($activity['ip_address'])): ?>
                                <span class="activity-ip"><?php echo $activity['ip_address']; ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <span class="activity-time">
                            <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($activity['description'])): ?>
                    <p class="activity-description">
                        <?php echo htmlspecialchars($activity['description']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Activities Pagination -->
            <?php if ($total_activities_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_activities_pages; $i++): ?>
                        <a href="javascript:void(0)" 
                           data-page="<?php echo $i; ?>"
                           class="page-link activity-page-link <?php echo $i == $activities_page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Details - Admin Panel</title>
    <?php require_once '../../includes/header.php'; ?>
    <link rel="stylesheet" href="../../assets/css/notifications.css">
    <link rel="stylesheet" href="../../assets/css/users.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Custom styles to replace Bootstrap */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-back:hover {
            background: #545b62;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background: white;
            color: #007bff;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .page-link:hover {
            background: #f8f9fa;
        }
        
        .page-link.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        /* Filter styles */
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        /* Activity Timeline Styles */
        .activity-timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .activity-dot {
            position: absolute;
            left: -40px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 3px;
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .activity-title {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .activity-ip {
            margin-left: 10px;
            padding: 2px 8px;
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .activity-time {
            color: #666;
            font-size: 12px;
        }
        
        .activity-description {
            margin: 0;
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .empty-activity {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-activity i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        /* Activity type colors */
        .activity-login .activity-dot { background: #28a745; box-shadow: 0 0 0 3px #28a745; }
        .activity-logout .activity-dot { background: #6c757d; box-shadow: 0 0 0 3px #6c757d; }
        .activity-profile_update .activity-dot { background: #007bff; box-shadow: 0 0 0 3px #007bff; }
        .activity-order_placed .activity-dot { background: #ffc107; box-shadow: 0 0 0 3px #ffc107; }
        .activity-product_view .activity-dot { background: #17a2b8; box-shadow: 0 0 0 3px #17a2b8; }
    </style>
</head>
<body>
    <div class="ajax-loading" id="ajaxLoading">
        <div class="spinner"></div>
    </div>
    
    <div class="admin-container">
        <!-- Page Header -->
        <div class="admin-header">
            <div class="header-content">
                <h1>
                    <i class="fas fa-user"></i>
                    Member Details
                </h1>
                <p class="header-subtitle">View and manage member account details</p>
            </div>
            <div class="header-actions">
                <a href="users.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Members
                </a>
            </div>
        </div>

        <?php if ($member): ?>
            <!-- Member Summary Card -->
            <div class="stat-card">
                <div class="member-info">
                    <div class="member-avatar large-avatar">
                        <?php if ($member['profile_photo']): ?>
                            <img src="<?php echo base('uploads/profile/' . $member['profile_photo']); ?>" 
                                 alt="<?php echo encode($member['username']); ?>">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="member-details">
                        <h2 class="member-name"><?php echo encode($member['username']); ?></h2>
                        <div class="member-meta">
                            <span class="member-id">ID: #<?php echo $member['user_id']; ?></span>
                            <span class="member-email">
                                <i class="fas fa-envelope"></i>
                                <?php echo encode($member['email']); ?>
                            </span>
                            <span class="member-joined">
                                <i class="fas fa-calendar-alt"></i>
                                Joined: <?php echo format_date($member['created_at'], 'M d, Y'); ?>
                            </span>
                            <span class="member-login">
                                <i class="fas fa-sign-in-alt"></i>
                                Last Login: <?php echo format_date($member['last_login'], 'M d, Y H:i'); ?>
                            </span>
                        </div>
                        
                        <!-- Status Indicators -->
                        <div class="member-status">
                            <?php if ($member['is_active']): ?>
                                <span class="status-badge active">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Active Account</span>
                                </span>
                            <?php else: ?>
                                <span class="status-badge inactive">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Inactive Account</span>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($member['email_verified']): ?>
                                <span class="verification-badge verified">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Email Verified</span>
                                </span>
                            <?php else: ?>
                                <span class="verification-badge pending">
                                    <i class="fas fa-clock"></i>
                                    <span>Email Pending</span>
                                    <?php if ($member['verification_code']): ?>
                                        <div class="verification-code">
                                            Code: <?php echo $member['verification_code']; ?>
                                        </div>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($member['login_attempts'] >= 3): ?>
                                <span class="status-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Account Locked (<?php echo $member['login_attempts']; ?> failed attempts)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="search-filter-section">
                <div class="section-header">
                    <h3><i class="fas fa-cog"></i> Manage Member</h3>
                </div>
                
                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="profile">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </button>
                    <button class="tab-btn" data-tab="orders">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders (<?php echo $total_orders; ?>)</span>
                    </button>
                    <!-- ADDED: Activity Tab Button -->
                    <button class="tab-btn" data-tab="activity">
                        <i class="fas fa-history"></i>
                        <span>Activity (<?php echo $total_activities; ?>)</span>
                    </button>
                    <button class="tab-btn" data-tab="actions">
                        <i class="fas fa-tools"></i>
                        <span>Quick Actions</span>
                    </button>
                </div>

                <!-- Profile Tab -->
                <div class="tab-content active" id="profile-tab">
                    <form method="POST" class="profile-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="username">
                                    <i class="fas fa-user"></i>
                                    Username
                                </label>
                                <input type="text" id="username" name="username" 
                                       value="<?php echo encode($member['username']); ?>" required>
                            </div>
                            
                            <div class="filter-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    Email Address
                                </label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo encode($member['email']); ?>" required>
                            </div>
                            
                            <div class="filter-group">
                                <label for="is_active">
                                    <i class="fas fa-toggle-on"></i>
                                    Account Status
                                </label>
                                <select id="is_active" name="is_active">
                                    <option value="1" <?php echo $member['is_active'] ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo !$member['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" name="action" value="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                    
                    <!-- Additional Info -->
                    <div class="info-section">
                        <h4><i class="fas fa-info-circle"></i> Account Information</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">User ID</span>
                                <span class="info-value">#<?php echo $member['user_id']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Role</span>
                                <span class="info-value"><?php echo ucfirst($member['role']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Login Attempts</span>
                                <span class="info-value"><?php echo $member['login_attempts']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Updated</span>
                                <span class="info-value"><?php echo format_date($member['updated_at']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Login</span>
                                <span class="info-value"><?php echo format_date($member['last_login']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Created</span>
                                <span class="info-value"><?php echo format_date($member['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Orders Tab (Loaded via AJAX) -->
                <div class="tab-content" id="orders-tab">
                    <!-- Content will be loaded via AJAX -->
                </div>

                <!-- Activities Tab (Loaded via AJAX) -->
                <div class="tab-content" id="activity-tab">
                    <!-- Content will be loaded via AJAX -->
                </div>

                <!-- Actions Tab -->
                <div class="tab-content" id="actions-tab">
                    <div class="action-grid">
                        <!-- Reset Password -->
                        <div class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <div class="action-content">
                                <h4>Reset Password</h4>
                                <p>Generate a new random password and email it to the member.</p>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <button type="submit" name="action" value="reset_password" 
                                            class="btn btn-primary reset-password-btn">
                                        <i class="fas fa-redo"></i> Reset Password
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Reset Login Attempts -->
                        <?php if ($member['login_attempts'] > 0): ?>
                            <div class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-unlock"></i>
                                </div>
                                <div class="action-content">
                                    <h4>Reset Login Attempts</h4>
                                    <p>Clear failed login attempts (currently: <?php echo $member['login_attempts']; ?>).</p>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <button type="submit" name="action" value="reset_attempts" 
                                                class="btn btn-primary reset-attempts-btn">
                                            <i class="fas fa-sync"></i> Reset Attempts
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Manual Email Verification -->
                        <?php if (!$member['email_verified']): ?>
                            <div class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-envelope-check"></i>
                                </div>
                                <div class="action-content">
                                    <h4>Verify Email Manually</h4>
                                    <p>Manually mark this member's email as verified.</p>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <button type="submit" name="action" value="verify_email" 
                                                class="btn btn-primary verify-email-btn">
                                            <i class="fas fa-check-circle"></i> Verify Email
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Account Status Toggle -->
                        <div class="action-card">
                            <div class="action-icon">
                                <?php if ($member['is_active']): ?>
                                    <i class="fas fa-user-slash"></i>
                                <?php else: ?>
                                    <i class="fas fa-user-check"></i>
                                <?php endif; ?>
                            </div>
                            <div class="action-content">
                                <h4>Account Status</h4>
                                <p>Current status: <strong><?php echo $member['is_active'] ? 'Active' : 'Inactive'; ?></strong></p>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                                    
                                    <?php if ($member['is_active']): ?>
                                        <button type="submit" name="action" value="deactivate" 
                                                class="btn btn-primary deactivate-btn">
                                            <i class="fas fa-ban"></i> Deactivate Account
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate" 
                                                class="btn btn-primary activate-btn">
                                            <i class="fas fa-check"></i> Activate Account
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Show toast notification if exists in session -->
    <?php if (isset($_SESSION['notification'])): ?>
    <script>
        $(document).ready(function() {
            // Small delay to ensure notifications are initialized
            setTimeout(function() {
                if (typeof showToast === 'function') {
                    showToast(
                        <?php echo json_encode($_SESSION['notification']['message'] ?? ''); ?>,
                        <?php echo json_encode($_SESSION['notification']['type'] ?? 'success'); ?>,
                        5000
                    );
                }
            }, 100);
        });
    </script>
    <?php 
        unset($_SESSION['notification']);
    endif; 
    ?>
    
    <script>
        $(document).ready(function() {
            // Store current page state
            let currentOrdersPage = <?php echo $orders_page; ?>;
            let currentActivitiesPage = <?php echo $activities_page; ?>;
            let currentOrderFilters = {
                status: '<?php echo $order_status; ?>',
                min_amount: '<?php echo $order_min_amount; ?>',
                max_amount: '<?php echo $order_max_amount; ?>',
                date_from: '<?php echo $order_date_from; ?>',
                date_to: '<?php echo $order_date_to; ?>'
            };
            
            // Tab switching functionality
            $('.tab-btn').on('click', function() {
                const tabId = $(this).data('tab');
                
                // Update active tab button
                $('.tab-btn').removeClass('active');
                $(this).addClass('active');
                
                // Show active tab content
                $('.tab-content').removeClass('active');
                const $tabContent = $('#' + tabId + '-tab');
                $tabContent.addClass('active');
                
                // Load tab content via AJAX if empty
                if (tabId === 'orders' && $tabContent.children().length === 0) {
                    loadOrdersTab();
                } else if (tabId === 'activity' && $tabContent.children().length === 0) {
                    loadActivitiesTab();
                }
            });
            
            // Function to load orders tab via AJAX
            function loadOrdersTab(page = currentOrdersPage, filters = currentOrderFilters) {
                $('#ajaxLoading').show();
                
                $.ajax({
                    url: 'member_details.php',
                    type: 'GET',
                    data: {
                        id: <?php echo $member_id; ?>,
                        ajax: 'orders',
                        orders_page: page,
                        order_status: filters.status,
                        order_min_amount: filters.min_amount,
                        order_max_amount: filters.max_amount,
                        order_date_from: filters.date_from,
                        order_date_to: filters.date_to
                    },
                    success: function(response) {
                        $('#orders-tab').html(response);
                        currentOrdersPage = page;
                        currentOrderFilters = filters;
                        
                        // Re-attach event listeners for the newly loaded content
                        attachOrdersTabEvents();
                        $('#ajaxLoading').hide();
                    },
                    error: function() {
                        alert('Error loading orders. Please try again.');
                        $('#ajaxLoading').hide();
                    }
                });
            }
            
            // Function to load activities tab via AJAX
            function loadActivitiesTab(page = currentActivitiesPage) {
                $('#ajaxLoading').show();
                
                $.ajax({
                    url: 'member_details.php',
                    type: 'GET',
                    data: {
                        id: <?php echo $member_id; ?>,
                        ajax: 'activities',
                        activities_page: page
                    },
                    success: function(response) {
                        $('#activity-tab').html(response);
                        currentActivitiesPage = page;
                        
                        // Re-attach event listeners for the newly loaded content
                        attachActivitiesTabEvents();
                        $('#ajaxLoading').hide();
                    },
                    error: function() {
                        alert('Error loading activities. Please try again.');
                        $('#ajaxLoading').hide();
                    }
                });
            }
            
            // Attach events to orders tab content
            function attachOrdersTabEvents() {
                // Order pagination
                $('.order-page-link').off('click').on('click', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadOrdersTab(page, currentOrderFilters);
                });
                
                // Order filter form
                $('#orderFilterForm').off('submit').on('submit', function(e) {
                    e.preventDefault();
                    const formData = $(this).serializeArray();
                    const filters = {};
                    
                    // Extract filter values
                    formData.forEach(function(item) {
                        if (item.name !== 'id' && item.name !== 'orders_page') {
                            filters[item.name] = item.value;
                        }
                    });
                    
                    loadOrdersTab(1, filters);
                });
                
                // Clear filters
                $('.clear-filter-btn').off('click').on('click', function() {
                    $('#orderFilterForm')[0].reset();
                    loadOrdersTab(1, {});
                });
            }
            
            // Attach events to activities tab content
            function attachActivitiesTabEvents() {
                // Activities pagination
                $('.activity-page-link').off('click').on('click', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadActivitiesTab(page);
                });
            }
            
            // Handle confirmation dialogs with jQuery
            $('.reset-password-btn').on('click', function(e) {
                if (!confirm('Are you sure? This will reset the member\'s password and email them a new one.')) {
                    e.preventDefault();
                } else {
                    // Show loading overlay
                    $('#ajaxLoading').show();
                }
            });
            
            $('.verify-email-btn').on('click', function(e) {
                if (!confirm('Manually verify this member\'s email?')) {
                    e.preventDefault();
                } else {
                    $('#ajaxLoading').show();
                }
            });
            
            $('.deactivate-btn').on('click', function(e) {
                if (!confirm('Deactivate this member\'s account?')) {
                    e.preventDefault();
                } else {
                    $('#ajaxLoading').show();
                }
            });
            
            $('.activate-btn').on('click', function(e) {
                if (!confirm('Activate this member\'s account?')) {
                    e.preventDefault();
                } else {
                    $('#ajaxLoading').show();
                }
            });
            
            // Handle form submissions with loading state
            $('.action-form').on('submit', function(e) {
                // Show loading overlay
                $('#ajaxLoading').show();
            });
            
            // Load orders tab if it's active on page load
            if ($('#orders-tab').hasClass('active') || window.location.hash === '#orders-tab') {
                // If coming from a pagination link, load orders tab
                $('.tab-btn[data-tab="profile"]').removeClass('active');
                $('.tab-btn[data-tab="orders"]').addClass('active');
                $('.tab-content').removeClass('active');
                $('#orders-tab').addClass('active');
                loadOrdersTab();
            }
            
            // Load activities tab if it's active on page load
            if (window.location.hash === '#activity-tab') {
                $('.tab-btn[data-tab="profile"]').removeClass('active');
                $('.tab-btn[data-tab="activity"]').addClass('active');
                $('.tab-content').removeClass('active');
                $('#activity-tab').addClass('active');
                loadActivitiesTab();
            }
            
            // Auto-hide loading overlay after 5 seconds (safety)
            setTimeout(function() {
                $('#ajaxLoading').hide();
            }, 5000);
        });
    </script>
    
    <script src="../../assets/js/notifications.js"></script>
</body>
</html>