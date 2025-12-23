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
    
    // Update session with current email if not set
    if (!isset($_SESSION['email']) && $email) {
        $_SESSION['email'] = $email;
    }
    
    // Set photo for header if exists
    if ($profile_photo) {
        $_SESSION['photo'] = $profile_photo;
    }
    
    // Get personalized member statistics
    $member_stats = [];
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
        
        // Get recent user activity (last 10 activities)
        $stmt = $_db->prepare('
            SELECT activity_type, description, ip_address, created_at
            FROM user_activity
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ');
        $stmt->execute([$uid]);
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
        error_log("Error fetching member stats: " . $e->getMessage());
        $member_stats = [];
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
    
    <!-- Personalized Member Dashboard -->
    <div class="profile-stats-card" style="background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 25px; margin-bottom: 30px;">
        <div class="card-header" style="border-bottom: 1px solid #eee; margin-bottom: 20px; padding-bottom: 15px;">
            <h5 style="margin: 0; color: #333; font-size: 18px;"><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($username); ?>!</h5>
        </div>
        
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
            <div class="stat-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div class="stat-icon" style="font-size: 24px; color: #007bff; margin-bottom: 10px;">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-value" style="font-size: 28px; font-weight: bold; color: #333;">
                    <?php echo $member_stats['order_count'] ?? 0; ?>
                </div>
                <div class="stat-label" style="color: #666; font-size: 14px;">Total Orders</div>
            </div>
            
            <div class="stat-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div class="stat-icon" style="font-size: 24px; color: #007bff; margin-bottom: 10px;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value" style="font-size: 28px; font-weight: bold; color: #333;">
                    <?php echo $member_stats['cart_count'] ?? 0; ?>
                </div>
                <div class="stat-label" style="color: #666; font-size: 14px;">Items in Cart</div>
            </div>
            
            <div class="stat-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div class="stat-icon" style="font-size: 24px; color: #007bff; margin-bottom: 10px;">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value" style="font-size: 28px; font-weight: bold; color: #333;">
                    $<?php echo number_format($member_stats['total_spent'] ?? 0, 2); ?>
                </div>
                <div class="stat-label" style="color: #666; font-size: 14px;">Total Spent</div>
            </div>
            
            <div class="stat-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div class="stat-icon" style="font-size: 24px; color: #007bff; margin-bottom: 10px;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value" style="font-size: 20px; font-weight: bold; color: #333;">
                    <?php echo date('M j, Y', strtotime($created_at)); ?>
                </div>
                <div class="stat-label" style="color: #666; font-size: 14px;">Member Since</div>
            </div>
        </div>
        
        <!-- Activity Summary -->
        <?php if (!empty($member_stats['activity_types'])): ?>
        <div class="activity-summary" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <h6 style="color: #333; margin-bottom: 15px; font-size: 16px;"><i class="fas fa-chart-bar"></i> Activity Summary</h6>
            <div class="activity-types" style="display: flex; flex-wrap: wrap; gap: 10px;">
                <?php foreach ($member_stats['activity_types'] as $activity): ?>
                <span class="activity-tag" style="
                    background: rgba(0, 123, 255, 0.1);
                    color: #007bff;
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-size: 13px;
                    font-weight: 500;
                    border: 1px solid rgba(0, 123, 255, 0.2);
                    display: flex;
                    align-items: center;
                    gap: 5px;
                ">
                    <i class="fas fa-<?php 
                        switch(strtolower($activity['activity_type'])) {
                            case 'login': echo 'sign-in-alt'; break;
                            case 'logout': echo 'sign-out-alt'; break;
                            case 'profile_update': echo 'user-edit'; break;
                            case 'order_placed': echo 'shopping-cart'; break;
                            case 'product_view': echo 'eye'; break;
                            case 'review_posted': echo 'star'; break;
                            default: echo 'circle';
                        }
                    ?>"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                    <span style="background: #007bff; color: white; border-radius: 10px; padding: 1px 6px; font-size: 11px;">
                        <?php echo $activity['count']; ?>
                    </span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Orders -->
        <?php if (!empty($member_stats['recent_orders'])): ?>
        <div class="recent-orders" style="margin-top: 20px;">
            <h6 style="color: #333; margin-bottom: 15px; font-size: 16px;"><i class="fas fa-history"></i> Recent Orders</h6>
            <div class="orders-list" style="background: #f8f9fa; border-radius: 8px; padding: 15px;">
                <?php foreach ($member_stats['recent_orders'] as $order): ?>
                <div class="order-item" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee;">
                    <div>
                        <span style="font-weight: 600; color: #333;">Order #<?php echo $order['order_id']; ?></span>
                        <span style="color: #666; font-size: 13px; margin-left: 10px;"><?php echo date('M j, Y', strtotime($order['order_date'])); ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <span style="font-weight: 600; color: #333;">$<?php echo number_format($order['total_amount'], 2); ?></span>
                        <span class="order-status" style="
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-size: 12px;
                            font-weight: 600;
                            background: <?php 
                                switch(strtolower($order['status'])) {
                                    case 'delivered': echo '#d4edda'; break;
                                    case 'shipped': echo '#cce5ff'; break;
                                    case 'confirmed': echo '#fff3cd'; break;
                                    default: echo '#f8d7da';
                                }
                            ?>;
                            color: <?php 
                                switch(strtolower($order['status'])) {
                                    case 'delivered': echo '#155724'; break;
                                    case 'shipped': echo '#004085'; break;
                                    case 'confirmed': echo '#856404'; break;
                                    default: echo '#721c24';
                                }
                            ?>;
                        ">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (count($member_stats['recent_orders']) > 0): ?>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="orders.php" class="btn-verify" style="display: inline-flex; align-items: center; padding: 4px 12px;">
                        <i class="fas fa-list"></i> View All Orders
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
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
                <a href="password_reset.php" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
    
    <!-- Recent Activity Timeline -->
    <?php if (!empty($member_stats['recent_activities'])): ?>
    <div class="activity-timeline-card" style="background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-top: 30px;">
        <div class="card-header" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); color: white; padding: 20px 25px; border-bottom: 1px solid #495057;">
            <h5 style="margin: 0; font-size: 18px;"><i class="fas fa-history"></i> Recent Activity</h5>
        </div>
        <div class="card-body" style="padding: 25px; background: white; color: #333;">
            <div class="timeline" style="position: relative; padding-left: 30px;">
                <?php foreach ($member_stats['recent_activities'] as $index => $activity): ?>
                <div class="timeline-item" style="position: relative; margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #eee;">
                    <!-- Timeline dot -->
                    <div style="position: absolute; left: -40px; top: 0; width: 20px; height: 20px; border-radius: 50%; background: #007bff; border: 3px solid white; box-shadow: 0 0 0 3px #007bff;"></div>
                    
                    <!-- Timeline content -->
                    <div class="timeline-content">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div>
                                <span style="font-weight: 600; color: #333; font-size: 15px;">
                                    <i class="fas fa-<?php 
                                        switch(strtolower($activity['activity_type'])) {
                                            case 'login': echo 'sign-in-alt'; break;
                                            case 'logout': echo 'sign-out-alt'; break;
                                            case 'profile_update': echo 'user-edit'; break;
                                            case 'order_placed': echo 'shopping-cart'; break;
                                            case 'product_view': echo 'eye'; break;
                                            case 'review_posted': echo 'star'; break;
                                            default: echo 'circle';
                                        }
                                    ?>" style="margin-right: 8px; color: #007bff;"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                </span>
                                <span style="margin-left: 10px; padding: 2px 8px; background: rgba(0, 123, 255, 0.1); color: #007bff; border-radius: 4px; font-size: 11px; font-weight: 500;">
                                    <?php echo $activity['ip_address'] ?? 'N/A'; ?>
                                </span>
                            </div>
                            <span style="color: #666; font-size: 12px;">
                                <?php 
                                    $timeAgo = time_ago($activity['created_at']);
                                    echo $timeAgo ? $timeAgo . ' ago' : date('M j, g:i A', strtotime($activity['created_at']));
                                ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($activity['description'])): ?>
                        <p style="margin: 0; color: #555; font-size: 14px; line-height: 1.5;">
                            <?php echo htmlspecialchars($activity['description']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($member_stats['recent_activities']) > 0): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" class="btn-verify" onclick="loadMoreActivities()" style="display: inline-flex; align-items: center; padding: 4px 12px;">
                        <i class="fas fa-redo"></i> Load More Activities
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Email Verification Section -->
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
    
    <!-- Account Information Card -->
    <div class="account-info-card" style="background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-top: 30px;">
        <div class="card-header" style="background: linear-gradient(135deg, #444 0%, #222 100%); color: white; padding: 20px 25px; border-bottom: 1px solid #444;">
            <h5 style="margin: 0; font-size: 18px;"><i class="fas fa-info-circle"></i> Account Information</h5>
        </div>
        <div class="card-body" style="padding: 25px; background: white; color: #333;">
            <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="info-item">
                    <div class="info-label" style="color: #666; font-size: 14px; margin-bottom: 5px;">Account Role</div>
                    <div class="info-value" style="font-weight: 600; color: #333; font-size: 16px;">
                        <?php echo ucfirst($user_role); ?>
                        <?php if ($user_role === 'admin'): ?>
                            <span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-left: 8px;">Admin</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label" style="color: #666; font-size: 14px; margin-bottom: 5px;">Member Since</div>
                    <div class="info-value" style="font-weight: 600; color: #333; font-size: 16px;">
                        <?php echo date('F j, Y', strtotime($created_at)); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label" style="color: #666; font-size: 14px; margin-bottom: 5px;">Last Login</div>
                    <div class="info-value" style="font-weight: 600; color: #333; font-size: 16px;">
                        <?php echo $last_login ? date('M j, Y g:i A', strtotime($last_login)) : 'Never'; ?>
                    </div>
                </div>
                
                <?php if (!empty($member_stats['last_order_date'])): ?>
                <div class="info-item">
                    <div class="info-label" style="color: #666; font-size: 14px; margin-bottom: 5px;">Last Order</div>
                    <div class="info-value" style="font-weight: 600; color: #333; font-size: 16px;">
                        <?php echo date('M j, Y', strtotime($member_stats['last_order_date'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Favorite Categories -->
            <?php if (!empty($member_stats['top_categories'])): ?>
            <div class="favorite-categories" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                <h6 style="color: #333; margin-bottom: 15px; font-size: 16px;"><i class="fas fa-heart"></i> Your Favorite Categories</h6>
                <div class="categories-list" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php foreach ($member_stats['top_categories'] as $category): ?>
                    <span class="category-tag" style="
                        background: rgba(0, 123, 255, 0.1);
                        color: #007bff;
                        padding: 6px 12px;
                        border-radius: 20px;
                        font-size: 14px;
                        font-weight: 500;
                        border: 1px solid rgba(0, 123, 255, 0.2);
                    ">
                        <?php echo htmlspecialchars($category['name']); ?>
                        <span style="background: #007bff; color: white; border-radius: 10px; padding: 1px 6px; font-size: 11px; margin-left: 5px;">
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