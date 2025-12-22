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
    
    // Fetch member's orders
    $orders_stmt = $_db->prepare("
        SELECT o.*, COUNT(oi.order_item_id) as item_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.order_id = oi.order_id 
        WHERE o.user_id = ? 
        GROUP BY o.order_id 
        ORDER BY o.order_date DESC 
        LIMIT 10
    ");
    $orders_stmt->execute([$member_id]);
    $orders = $orders_stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['notification'] = [
        'message' => "Database error: " . $e->getMessage(),
        'type' => 'error'
    ];
}

// Generate CSRF token
$csrf_token = csrf_token();
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
                <a href="users.php" class="btn-back">
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
                        <span>Orders (<?php echo count($orders); ?>)</span>
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

                <!-- Orders Tab -->
                <div class="tab-content" id="orders-tab">
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
                                <h3><i class="fas fa-receipt"></i> Recent Orders</h3>
                                <span class="total-count">Showing <?php echo count($orders); ?> orders</span>
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
                        </div>
                    <?php endif; ?>
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
                                            class="btn btn-primary"
                                            onclick="return confirm('Are you sure? This will reset the member\\'s password and email them a new one.');">
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
                                                class="btn btn-primary">
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
                                                class="btn btn-primary"
                                                onclick="return confirm('Manually verify this member\\'s email?');">
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
                                                class="btn btn-primary"
                                                onclick="return confirm('Deactivate this member\\'s account?');">
                                            <i class="fas fa-ban"></i> Deactivate Account
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate" 
                                                class="btn btn-primary"
                                                onclick="return confirm('Activate this member\\'s account?');">
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
        document.addEventListener('DOMContentLoaded', function() {
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
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Update active tab button
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show active tab content
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === tabId + '-tab') {
                            content.classList.add('active');
                        }
                    });
                });
            });
            
            // Handle form submissions with loading state
            document.querySelectorAll('.action-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Show loading overlay
                    const loadingOverlay = document.getElementById('ajaxLoading');
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'flex';
                    }
                    
                    // The form will submit normally and redirect back
                    // The loading overlay will be hidden when page reloads
                });
            });
        });
    </script>
    
    <script src="../../assets/js/notifications.js"></script>
</body>
</html>