<?php
// At the top of profile.php
require_once '../lib/base.php';      // includes your base functions
require_once '../lib/auth.php';      // includes auth functions

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// ----------------------------------------------------------------------------
// Pagination settings for Recent Activities
// ----------------------------------------------------------------------------
$activities_per_page = 5; // Number of activities to show per page
$current_page = isset($_GET['activity_page']) ? (int)$_GET['activity_page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $activities_per_page;

// ----------------------------------------------------------------------------
// GET: Load current user data
// ----------------------------------------------------------------------------
if (is_get()) {
    $stm = $_db->prepare('SELECT username, email, profile_photo, role, created_at, last_login FROM users WHERE user_id = ?');
    $stm->execute([$uid]);
    $u = $stm->fetch();

    if (!$u) {
        redirect('dashboard.php');
    }

    // Set variables from database
    $username = $u['username'] ?? '';
    $email = $u['email'] ?? '';
    $profile_photo = $u['profile_photo'] ?? '';
    $user_role = $u['role'] ?? 'member';
    $created_at = $u['created_at'] ?? '';
    $last_login = $u['last_login'] ?? '';
    
    // Store user role in session for easy access
    $_SESSION['role'] = $user_role;
    
    // Update session with current email if not set
    if (!isset($_SESSION['email']) && $email) {
        $_SESSION['email'] = $email;
    }
    
    // Set photo for header if exists
    if ($profile_photo) {
        $_SESSION['photo'] = $profile_photo;
    }
    
    // Initialize member_stats array for all roles
    $member_stats = [];
    
    // Get personalized member statistics ONLY for members
    if ($user_role === 'member') {
        try {
            // Get order count and total spent
            $stmt = $_db->prepare('
                SELECT 
                    COUNT(*) as order_count,
                    SUM(total_amount) as total_spent,
                    MAX(order_date) as last_order_date
                FROM orders 
                WHERE user_id = ? AND status != "cancelled"
            ');
            $stmt->execute([$uid]);
            $order_stats = $stmt->fetch();
            
            $member_stats['order_count'] = $order_stats['order_count'] ?? 0;
            $member_stats['total_spent'] = $order_stats['total_spent'] ?? 0;
            $member_stats['last_order_date'] = $order_stats['last_order_date'] ?? null;
            
            // Get cart item count
            $stmt = $_db->prepare('SELECT COUNT(*) as cart_count FROM shopping_cart WHERE user_id = ?');
            $stmt->execute([$uid]);
            $cart_stats = $stmt->fetch();
            $member_stats['cart_count'] = $cart_stats['cart_count'] ?? 0;
            
            // Get favorite categories (most purchased)
            $stmt = $_db->prepare('
                SELECT c.name, COUNT(*) as purchase_count
                FROM orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                JOIN products p ON oi.product_id = p.product_id
                JOIN category c ON p.category_id = c.category_id
                WHERE o.user_id = ? AND o.status != "cancelled"
                GROUP BY c.category_id
                ORDER BY purchase_count DESC
                LIMIT 3
            ');
            $stmt->execute([$uid]);
            $member_stats['top_categories'] = $stmt->fetchAll();
            
            // Get recent orders (last 3)
            $stmt = $_db->prepare('
                SELECT order_id, order_date, total_amount, status
                FROM orders
                WHERE user_id = ?
                ORDER BY order_date DESC
                LIMIT 3
            ');
            $stmt->execute([$uid]);
            $member_stats['recent_orders'] = $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error fetching member stats: " . $e->getMessage());
            // Don't set to empty array, keep what we have
        }
    }
    
    // For ALL roles (including admin and support), get activity data
    try {
        // Get total count of activities for pagination
        $stmt = $_db->prepare('SELECT COUNT(*) as total FROM user_activity WHERE user_id = ?');
        $stmt->execute([$uid]);
        $total_activities = $stmt->fetchColumn();
        $member_stats['total_activities'] = (int)$total_activities;
        
        // Calculate total pages
        $total_pages = ceil($total_activities / $activities_per_page);
        $member_stats['total_activity_pages'] = $total_pages;
        
        // Get paginated user activity
        $stmt = $_db->prepare('
            SELECT activity_type, description, ip_address, created_at
            FROM user_activity
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ');
        // Bind parameters
        $stmt->bindValue(1, $uid, PDO::PARAM_INT);
        $stmt->bindValue(2, $activities_per_page, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $member_stats['recent_activities'] = $stmt->fetchAll();
        
        // Get activity counts by type
        $stmt = $_db->prepare('
            SELECT activity_type, COUNT(*) as count
            FROM user_activity
            WHERE user_id = ?
            GROUP BY activity_type
            ORDER BY count DESC
        ');
        $stmt->execute([$uid]);
        $member_stats['activity_types'] = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error fetching activity stats: " . $e->getMessage());
        // Ensure arrays exist even if there's an error
        if (!isset($member_stats['recent_activities'])) {
            $member_stats['recent_activities'] = [];
        }
        if (!isset($member_stats['activity_types'])) {
            $member_stats['activity_types'] = [];
        }
        if (!isset($member_stats['total_activities'])) {
            $member_stats['total_activities'] = 0;
        }
        if (!isset($member_stats['total_activity_pages'])) {
            $member_stats['total_activity_pages'] = 1;
        }
    }
}

// ----------------------------------------------------------------------------
// POST: Update profile
// ----------------------------------------------------------------------------
if (is_post()) {
    $username = req('username');
    $email = req('email');
    $photo = $_SESSION['photo'] ?? 'avatar.png';
    
    // FIXED: Use $_FILES instead of get_file() which doesn't exist
    $f = isset($_FILES['photo']) && $_FILES['photo']['error'] != UPLOAD_ERR_NO_FILE ? $_FILES['photo'] : null;

    // Validate: username
    if ($username == '') {
        $_err['username'] = 'Required';
    }
    else if (strlen($username) > 50) {
        $_err['username'] = 'Maximum 50 characters';
    }

    // Validate: email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else {
        // Check if email is unique (excluding current user's email)
        $stm = $_db->prepare('
            SELECT COUNT(*) FROM users 
            WHERE email = ? AND user_id != ?
        ');
        $stm->execute([$email, $uid]);

        if ($stm->fetchColumn() > 0) {
            $_err['email'] = 'Duplicated';
        }
    }

    // Validate: photo (file) --> optional
    if ($f) {
        if (!str_starts_with($f['type'], 'image/')) {
            $_err['photo'] = 'Must be image';
        }
        else if ($f['size'] > 2 * 1024 * 1024) {
            $_err['photo'] = 'Maximum 2MB';
        }
    }

    // DB operation
    if (empty($_err)) {
        // Start transaction
        $_db->beginTransaction();
        
        try {
            // (1) Handle photo upload --> optional
            if ($f && !isset($_err['photo'])) {
                // Delete old photo if exists and not default avatar
                if ($photo && $photo != 'avatar.png') {
                    $old_path = root("uploads/profile/$photo");
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }
                
                // Use the new function from base.php
                $name = generate_profile_filename($uid, $f['name']);
                $path = root("uploads/profile/$name");
                
                // Save the uploaded image
                if (save_uploaded_image($f, $path)) {
                    $photo = $name;
                    $_SESSION['photo'] = $photo;
                    
                    // Log activity: Profile picture updated
                    log_user_activity($uid, 'profile_update', 'Updated profile picture', $_SERVER['REMOTE_ADDR']);
                } else {
                    throw new Exception("Failed to save uploaded image.");
                }
            }
            
            // (2) Update user (username, email, profile_photo)
            $stm = $_db->prepare('
                UPDATE users 
                SET username = ?, email = ?, profile_photo = ?
                WHERE user_id = ?
            ');
            $stm->execute([$username, $email, $photo, $uid]);

            // (3) Update session variables
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;

            // (4) Log profile update activity
            log_user_activity($uid, 'profile_update', 'Updated profile information', $_SERVER['REMOTE_ADDR']);

            $_db->commit();
            
            temp('info', 'Profile updated successfully!');
            redirect('profile.php');
            
        } catch (Exception $e) {
            $_db->rollBack();
            temp('error', 'Error updating profile: ' . $e->getMessage());
        }
    }
}

// ----------------------------------------------------------------------------
// Get verification status for display
// ----------------------------------------------------------------------------
try {
    $stmt = $_db->prepare('SELECT email_verified, verification_code FROM users WHERE user_id = ?');
    $stmt->execute([$uid]);
    $verification = $stmt->fetch();
    $is_verified = $verification['email_verified'] ?? 0;
    $has_code = !empty($verification['verification_code'] ?? '');
} catch (Exception $e) {
    $is_verified = 0;
    $has_code = false;
}

// ----------------------------------------------------------------------------
// Set page title
$_title = 'My Profile';
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../assets/css/profile.css?v=<?php echo time(); ?>">

<div class="profile-container">
    <div class="profile-header">
        <h2>My Profile</h2>
        <p>Manage your account information and preferences</p>
    </div>
    
    <?php 
    $message = temp('info');
    if ($message) {
        echo '<div class="success-message">' . htmlspecialchars($message) . '</div>';
    }
    $error = temp('error');
    if ($error) {
        echo '<div class="error-message">' . htmlspecialchars($error) . '</div>';
    }
    ?>
    
    <!-- Personalized Member Dashboard (ONLY for members) -->
    <?php if ($user_role === 'member'): ?>
    <div class="profile-stats-card">
        <div class="card-header">
            <h5><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($username); ?>!</h5>
        </div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-value">
                    <?php echo $member_stats['order_count'] ?? 0; ?>
                </div>
                <div class="stat-label">Total Orders</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value">
                    <?php echo $member_stats['cart_count'] ?? 0; ?>
                </div>
                <div class="stat-label">Items in Cart</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value">
                    $<?php echo number_format($member_stats['total_spent'] ?? 0, 2); ?>
                </div>
                <div class="stat-label">Total Spent</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value">
                    <?php echo date('M j, Y', strtotime($created_at)); ?>
                </div>
                <div class="stat-label">Member Since</div>
            </div>
        </div>
        
        <!-- Activity Summary (Visible for all roles) -->
        <?php if (!empty($member_stats['activity_types'])): ?>
        <div class="activity-summary">
            <h6><i class="fas fa-chart-bar"></i> Activity Summary</h6>
            <div class="activity-types">
                <?php foreach ($member_stats['activity_types'] as $activity): ?>
                <span class="activity-tag">
                    <i class="fas fa-<?php 
                        switch(strtolower($activity['activity_type'])) {
                            case 'login': echo 'sign-in-alt'; break;
                            case 'logout': echo 'sign-out-alt'; break;
                            case 'profile_update': echo 'user-edit'; break;
                            case 'order_placed': echo 'shopping-cart'; break;
                            case 'product_view': echo 'eye'; break;
                            case 'review_posted': echo 'star'; break;
                            case 'admin_action': echo 'user-shield'; break;
                            case 'support_ticket': echo 'life-ring'; break;
                            default: echo 'circle';
                        }
                    ?>"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                    <span class="activity-count">
                        <?php echo $activity['count']; ?>
                    </span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Orders (ONLY for members) -->
        <?php if (!empty($member_stats['recent_orders'])): ?>
        <div class="recent-orders">
            <h6><i class="fas fa-history"></i> Recent Orders</h6>
            <div class="orders-list">
                <?php foreach ($member_stats['recent_orders'] as $order): ?>
                <div class="order-item">
                    <div class="order-info">
                        <span class="order-id">Order #<?php echo $order['order_id']; ?></span>
                        <span class="order-date"><?php echo date('M j, Y', strtotime($order['order_date'])); ?></span>
                    </div>
                    <div class="order-details">
                        <span class="order-amount">$<?php echo number_format($order['total_amount'], 2); ?></span>
                        <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (count($member_stats['recent_orders']) > 0): ?>
                <div class="view-all-orders">
                    <a href="orders.php" class="btn-verify">
                        <i class="fas fa-list"></i> View All Orders
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Profile Update Form -->
    <div class="profile-form">
        <form method="post" enctype="multipart/form-data" id="profile-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                       maxlength="50" required>
                <span class="form-error"><?php echo $_err['username'] ?? ''; ?></span>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                       maxlength="100" required>
                <span class="form-error"><?php echo $_err['email'] ?? ''; ?></span>
            </div>
            
            <!-- Profile Picture with Button to Open Modal -->
            <div class="photo-upload">
                <div class="current-photo">
                    <img src="<?php echo base("uploads/profile/" . ($profile_photo ?? 'avatar.png')); ?>" 
                         alt="Current Profile Photo" class="photo-preview" id="current-photo-preview">
                    <div class="photo-actions">
                        <button type="button" class="btn btn-secondary" id="change-photo-btn">
                            <i class="fas fa-camera"></i> Change Profile Picture
                        </button>
                    </div>
                </div>
                <!-- Hidden file input for the form -->
                <input type="file" id="photo" name="photo" accept="image/*" style="display: none;">
                <span class="form-error"><?php echo $_err['photo'] ?? ''; ?></span>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="btn_update" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                
                <!-- Change Password button (ONLY for members) -->
                <?php if ($user_role === 'member'): ?>
                <a href="password_reset.php" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <?php endif; ?>
                
                <!-- Back button - different destinations based on role -->
                <?php if ($user_role === 'member'): ?>
                <a href="memberDashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <?php elseif ($user_role === 'admin'): ?>
                <a href="adminDashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Admin Dashboard
                </a>
                <?php elseif ($user_role === 'support'): ?>
                <a href="supportDashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Support Dashboard
                </a>
                <?php else: ?>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Recent Activity Timeline with Pagination (Visible for all roles) -->
    <?php if (isset($member_stats['recent_activities']) && !empty($member_stats['recent_activities'])): ?>
    <div class="activity-timeline-card">
        <div class="card-header">
            <h5><i class="fas fa-history"></i> Recent Activity</h5>
            <?php if (isset($member_stats['total_activities']) && $member_stats['total_activities'] > 0): ?>
            <div class="activity-info">
                <span class="total-activities">
                    <?php echo $member_stats['total_activities']; ?> total activities
                </span>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="timeline">
                <?php foreach ($member_stats['recent_activities'] as $index => $activity): ?>
                <div class="timeline-item">
                    <!-- Timeline dot -->
                    <div class="timeline-dot"></div>
                    
                    <!-- Timeline content -->
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="timeline-title">
                                <i class="fas fa-<?php 
                                    switch(strtolower($activity['activity_type'])) {
                                        case 'login': echo 'sign-in-alt'; break;
                                        case 'logout': echo 'sign-out-alt'; break;
                                        case 'profile_update': echo 'user-edit'; break;
                                        case 'order_placed': echo 'shopping-cart'; break;
                                        case 'product_view': echo 'eye'; break;
                                        case 'review_posted': echo 'star'; break;
                                        case 'admin_action': echo 'user-shield'; break;
                                        case 'support_ticket': echo 'life-ring'; break;
                                        default: echo 'circle';
                                    }
                                ?>"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                <span class="timeline-ip">
                                    <?php echo $activity['ip_address'] ?? 'N/A'; ?>
                                </span>
                            </div>
                            <span class="timeline-time">
                                <?php 
                                    $timeAgo = time_ago($activity['created_at']);
                                    echo $timeAgo ? $timeAgo . ' ago' : date('M j, g:i A', strtotime($activity['created_at']));
                                ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($activity['description'])): ?>
                        <p class="timeline-description">
                            <?php echo htmlspecialchars($activity['description']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Pagination Controls -->
                <?php if (isset($member_stats['total_activity_pages']) && $member_stats['total_activity_pages'] > 1): ?>
                <div class="activity-pagination">
                    <div class="pagination-info">
                        Showing <?php 
                            $start = $offset + 1;
                            $end = min($offset + $activities_per_page, $member_stats['total_activities'] ?? 0);
                            echo $start . '-' . $end;
                        ?> of <?php echo $member_stats['total_activities'] ?? 0; ?> activities
                    </div>
                    <div class="pagination-controls">
                        <?php if ($current_page > 1): ?>
                        <a href="?activity_page=<?php echo $current_page - 1; ?>" class="pagination-btn prev-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php
                            $total_pages = $member_stats['total_activity_pages'] ?? 1;
                            $max_pages_to_show = 5;
                            $start_page = max(1, $current_page - floor($max_pages_to_show / 2));
                            $end_page = min($total_pages, $start_page + $max_pages_to_show - 1);
                            
                            // Adjust start page if we're near the end
                            if ($end_page - $start_page + 1 < $max_pages_to_show) {
                                $start_page = max(1, $end_page - $max_pages_to_show + 1);
                            }
                            
                            // Show first page if not in range
                            if ($start_page > 1) {
                                echo '<a href="?activity_page=1" class="pagination-number">1</a>';
                                if ($start_page > 2) echo '<span class="pagination-ellipsis">...</span>';
                            }
                            
                            // Show page numbers
                            for ($page = $start_page; $page <= $end_page; $page++):
                                if ($page == $current_page): ?>
                                    <span class="pagination-number current"><?php echo $page; ?></span>
                                <?php else: ?>
                                    <a href="?activity_page=<?php echo $page; ?>" class="pagination-number">
                                        <?php echo $page; ?>
                                    </a>
                                <?php endif;
                            endfor;
                            
                            // Show last page if not in range
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) echo '<span class="pagination-ellipsis">...</span>';
                                echo '<a href="?activity_page=' . $total_pages . '" class="pagination-number">' . $total_pages . '</a>';
                            }
                            ?>
                        </div>
                        
                        <?php if ($current_page < $total_pages): ?>
                        <a href="?activity_page=<?php echo $current_page + 1; ?>" class="pagination-btn next-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php elseif (isset($member_stats['total_activities']) && $member_stats['total_activities'] == 0): ?>
    <div class="activity-timeline-card">
        <div class="card-header">
            <h5><i class="fas fa-history"></i> Recent Activity</h5>
        </div>
        <div class="card-body">
            <div class="no-activities">
                <i class="fas fa-clock"></i>
                <p>No activity recorded yet.</p>
                <p class="small">Your activities will appear here once you start using the system.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Email Verification Section (ONLY for members) -->
    <?php if ($user_role === 'member'): ?>
    <div class="verification-card">
        <div class="card-header">
            <h5><i class="fas fa-envelope"></i> Email Verification Status</h5>
        </div>
        <div class="card-body">
            <?php if ($is_verified): ?>
                <div class="verification-status alert-success">
                    <div class="status-icon">✅</div>
                    <div class="status-content">
                        <h6>Email Verified</h6>
                        <p>Your email <strong><?php echo htmlspecialchars($email); ?></strong> is verified and ready to use.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="verification-status alert-warning">
                    <div class="status-icon">⚠️</div>
                    <div class="status-content">
                        <h6>Email Not Verified</h6>
                        <p>
                            Your email <strong><?php echo htmlspecialchars($email); ?></strong> is not verified.
                            You need to verify your email to access all features.
                        </p>
                    </div>
                </div>
                
                <div class="verification-features">
                    <h6>Features requiring verification:</h6>
                    <ul class="features-list">
                        <li>Complete purchases (checkout)</li>
                        <li>View order history</li>
                        <li>Write product reviews</li>
                        <li>Save shipping addresses</li>
                        <li>Receive order notifications</li>
                    </ul>
                </div>
                
                <div class="verification-actions">
                    <!-- Single button that goes to verification page - email will be sent automatically -->
                    <a href="verify_email_code.php" class="btn-verify">
                        <i class="fas fa-envelope"></i> Verify Email Now
                    </a>
                    
                    <button type="button" class="btn-info" onclick="openVerificationPopup()">
                        <i class="fas fa-info-circle"></i> Learn More
                    </button>
                </div>
                
                <div class="verification-tips">
                    <p><i class="fas fa-exclamation-triangle"></i> Check your spam folder if you don't see the email</p>
                    <p><i class="fas fa-clock"></i> Verification codes expire in 10 minutes</p>
                    <p><i class="fas fa-redo"></i> You can request a new code on the verification page</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Account Information Card -->
    <div class="account-info-card">
        <div class="card-header">
            <h5><i class="fas fa-info-circle"></i> Account Information</h5>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Account Role</div>
                    <div class="info-value">
                        <?php echo ucfirst($user_role); ?>
                        <?php if ($user_role === 'admin'): ?>
                            <span class="role-badge admin-badge">Admin</span>
                        <?php elseif ($user_role === 'support'): ?>
                            <span class="role-badge support-badge">Support</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Account Created</div>
                    <div class="info-value">
                        <?php echo date('F j, Y', strtotime($created_at)); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Last Login</div>
                    <div class="info-value">
                        <?php echo $last_login ? date('M j, Y g:i A', strtotime($last_login)) : 'Never'; ?>
                    </div>
                </div>
                
                <!-- Last Order (ONLY for members) -->
                <?php if ($user_role === 'member' && !empty($member_stats['last_order_date'])): ?>
                <div class="info-item">
                    <div class="info-label">Last Order</div>
                    <div class="info-value">
                        <?php echo date('M j, Y', strtotime($member_stats['last_order_date'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Favorite Categories (ONLY for members) -->
            <?php if ($user_role === 'member' && !empty($member_stats['top_categories'])): ?>
            <div class="favorite-categories">
                <h6><i class="fas fa-heart"></i> Your Favorite Categories</h6>
                <div class="categories-list">
                    <?php foreach ($member_stats['top_categories'] as $category): ?>
                    <span class="category-tag">
                        <?php echo htmlspecialchars($category['name']); ?>
                        <span class="category-count">
                            <?php echo $category['purchase_count']; ?>
                        </span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Profile Picture Upload Modal (Popup) -->
<div class="modal-overlay" id="photo-modal" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Upload Profile Picture</h3>
            <button type="button" class="close-modal" id="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Drag & Drop Area -->
            <div class="modal-drag-drop-area" id="modal-drop-zone">
                <div class="drag-drop-content">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="upload-text">Drag & Drop your image here</div>
                    <div class="upload-subtext">or click to browse</div>
                    <div class="file-types">Supported: JPG, PNG, GIF (Max 2MB)</div>
                </div>
                
                <!-- Hidden file input for the modal -->
                <input type="file" id="modal-file-input" accept="image/*" style="display: none;">
                
                <!-- Preview area -->
                <div class="preview-area" id="modal-preview" style="display: none;">
                    <img src="" alt="Preview" id="modal-preview-image">
                    <div class="preview-info">
                        <span id="modal-file-name"></span>
                        <span id="modal-file-size"></span>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" id="cancel-upload">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-upload" disabled>
                    <i class="fas fa-check"></i> Use This Photo
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openVerificationPopup() {
    // Calculate position to center the popup
    const width = 800;
    const height = 600;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    // Open verify_required.php in a popup window
    window.open(
        'verify_required.php',
        'verificationInfo',
        `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,status=no`
    );
}

function loadMoreActivities() {
    // This function would load more activities via AJAX
    // For now, just redirect to a full activity log page
    window.location.href = 'activity_log.php';
}

// Time ago function for better time display
function timeAgo(timestamp) {
    const seconds = Math.floor((new Date() - new Date(timestamp)) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) return interval + " year" + (interval === 1 ? "" : "s");
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) return interval + " month" + (interval === 1 ? "" : "s");
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) return interval + " day" + (interval === 1 ? "" : "s");
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) return interval + " hour" + (interval === 1 ? "" : "s");
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) return interval + " minute" + (interval === 1 ? "" : "s");
    
    return Math.floor(seconds) + " second" + (seconds === 1 ? "" : "s");
}

// Update time ago on page load
document.addEventListener('DOMContentLoaded', function() {
    const timeElements = document.querySelectorAll('.time-ago');
    timeElements.forEach(el => {
        const timestamp = el.getAttribute('data-time');
        if (timestamp) {
            el.textContent = timeAgo(timestamp) + ' ago';
        }
    });
});
</script>
<script src="../assets/js/profile.js"></script>

<?php include '../includes/footer.php'; ?>

<?php
// Add this helper function to calculate time ago
function time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) : 'just now';
}
?>
