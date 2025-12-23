<?php
// public/admin/users.php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/base.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
require_admin();

// ============================
// AJAX ENDPOINT FOR ACTIONS
// ============================
if (is_post() && isset($_POST['ajax_action'])) {
    $action = $_POST['ajax_action'];
    $user_id = $_POST['user_id'] ?? 0;
    
    $response = [
        'success' => false,
        'message' => 'Unknown action',
        'type' => 'error'
    ];
    
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $response = [
            'success' => false,
            'message' => "Security token invalid.",
            'type' => 'error'
        ];
    } elseif ($action && $user_id) {
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $_db->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $response = [
                        'success' => true,
                        'message' => "Member activated successfully.",
                        'type' => 'success'
                    ];
                    break;
                    
                case 'deactivate':
                    $stmt = $_db->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $response = [
                        'success' => true,
                        'message' => "Member deactivated successfully.",
                        'type' => 'success'
                    ];
                    break;
                    
                case 'delete':
                    $order_check = $_db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                    $order_check->execute([$user_id]);
                    $order_count = $order_check->fetchColumn();
                    
                    if ($order_count > 0) {
                        $response = [
                            'success' => false,
                            'message' => "Cannot delete member with existing orders. Deactivate instead.",
                            'type' => 'error'
                        ];
                    } else {
                        $stmt = $_db->prepare("DELETE FROM users WHERE user_id = ? AND role = 'member'");
                        $stmt->execute([$user_id]);
                        if ($stmt->rowCount() > 0) {
                            $response = [
                                'success' => true,
                                'message' => "Member deleted successfully.",
                                'type' => 'success'
                            ];
                        } else {
                            $response = [
                                'success' => false,
                                'message' => "Cannot delete admin users.",
                                'type' => 'error'
                            ];
                        }
                    }
                    break;
                    
                case 'resend_verification':
                    $stmt = $_db->prepare("SELECT email, username, verification_code FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $member = $stmt->fetch();
                    
                    if ($member) {
                        $verification_code = sprintf('%06d', random_int(0, 999999));
                        $verification_code_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        $update_stmt = $_db->prepare("UPDATE users SET verification_code = ?, verification_code_expires = ? WHERE user_id = ?");
                        $update_stmt->execute([$verification_code, $verification_code_expires, $user_id]);
                        
                        if (send_verification_code_email($member['email'], $member['username'], $verification_code)) {
                            $response = [
                                'success' => true,
                                'message' => "Verification email resent successfully.",
                                'type' => 'success'
                            ];
                        } else {
                            $response = [
                                'success' => false,
                                'message' => "Failed to send verification email.",
                                'type' => 'error'
                            ];
                        }
                    }
                    break;
            }
        } catch (PDOException $e) {
            $response = [
                'success' => false,
                'message' => "Database error: " . $e->getMessage(),
                'type' => 'error'
            ];
        }
    }
    
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// ============================
// REGULAR PAGE LOAD
// ============================
// Get filter parameters
$search = get('search', '');
$status = get('status', 'all'); // all, active, inactive, locked
$verification = get('verification', 'all'); // all, verified, pending
$sort = get('sort', 'newest'); // newest, oldest, name_asc, name_desc, last_login
$page = get('page', 1);
$limit = 20;
$offset = ($page - 1) * $limit;
$total_members = 0;
$total_pages = 1;
$members = [];

// Build query with filters
try {
    // Build WHERE conditions
    $where_conditions = ["role = 'member'"];
    $params = [];
    $param_types = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types[] = 'string';
        $param_types[] = 'string';
    }
    
    if ($status !== 'all') {
        switch ($status) {
            case 'active':
                $where_conditions[] = "is_active = 1";
                break;
            case 'inactive':
                $where_conditions[] = "is_active = 0";
                break;
            case 'locked':
                $where_conditions[] = "login_attempts >= 3";
                break;
        }
    }
    
    if ($verification !== 'all') {
        switch ($verification) {
            case 'verified':
                $where_conditions[] = "email_verified = 1";
                break;
            case 'pending':
                $where_conditions[] = "email_verified = 0";
                break;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Build ORDER BY clause
    $order_by = 'created_at DESC'; // default
    switch ($sort) {
        case 'oldest':
            $order_by = 'created_at ASC';
            break;
        case 'name_asc':
            $order_by = 'username ASC';
            break;
        case 'name_desc':
            $order_by = 'username DESC';
            break;
        case 'last_login':
            $order_by = 'last_login DESC';
            break;
        case 'newest':
        default:
            $order_by = 'created_at DESC';
            break;
    }
    
    // Count total members with filters
    $count_query = "SELECT COUNT(*) FROM users WHERE $where_clause";
    $stmt = $_db->prepare($count_query);
    
    // Bind parameters for count query
    foreach ($params as $index => $param) {
        $type = $param_types[$index] === 'int' ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($index + 1, $param, $type);
    }
    
    $stmt->execute();
    $total_members = $stmt->fetchColumn();
    $total_pages = ceil($total_members / $limit);
    
    // Build main query
    $query = "SELECT 
                user_id,
                username,
                email,
                profile_photo,
                is_active,
                email_verified,
                verification_code,
                verification_code_expires,
                last_login,
                created_at,
                login_attempts,
                last_attempt
              FROM users 
              WHERE $where_clause
              ORDER BY $order_by
              LIMIT ? OFFSET ?";
    
    $stmt = $_db->prepare($query);
    
    // Bind filter parameters
    $param_index = 1;
    foreach ($params as $index => $param) {
        $type = $param_types[$index] === 'int' ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($param_index, $param, $type);
        $param_index++;
    }
    
    // Bind limit and offset
    $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($param_index, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $members = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management - Admin Panel</title>
    <?php require_once '../../includes/header.php'; ?>
    <link rel="stylesheet" href="../../assets/css/notifications.css">
    <link rel="stylesheet" href="../../assets/css/users.css">
    <script src="../../assets/js/notifications.js"></script>
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
                    <i class="fas fa-users"></i>
                    Member Management
                </h1>
                <p class="header-subtitle">Manage all registered members and their accounts</p>
            </div>
            <div class="header-actions">
                <a href="../dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_members; ?></h3>
                    <p>Total Members</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3>
                        <?php
                        $active_count = 0;
                        foreach ($members as $m) {
                            if ($m['is_active']) $active_count++;
                        }
                        echo $active_count;
                        ?>
                    </h3>
                    <p>Active Members</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon verified">
                    <i class="fas fa-envelope-check"></i>
                </div>
                <div class="stat-content">
                    <h3>
                        <?php
                        $verified_count = 0;
                        foreach ($members as $m) {
                            if ($m['email_verified']) $verified_count++;
                        }
                        echo $verified_count;
                        ?>
                    </h3>
                    <p>Verified Emails</p>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <div class="section-header">
                <h3><i class="fas fa-filter"></i> Search & Filter</h3>
            </div>
            
            <form method="GET" class="filter-form">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="search">
                            <i class="fas fa-search"></i>
                            Search Members
                        </label>
                        <input type="text" id="search" name="search" 
                               placeholder="Search by username or email..." 
                               value="<?php echo encode($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">
                            <i class="fas fa-user-circle"></i>
                            Account Status
                        </label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active Accounts</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive Accounts</option>
                            <option value="locked" <?php echo $status === 'locked' ? 'selected' : ''; ?>>Locked Accounts</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="verification">
                            <i class="fas fa-envelope"></i>
                            Email Verification
                        </label>
                        <select id="verification" name="verification">
                            <option value="all" <?php echo $verification === 'all' ? 'selected' : ''; ?>>All Verification Status</option>
                            <option value="verified" <?php echo $verification === 'verified' ? 'selected' : ''; ?>>Verified Only</option>
                            <option value="pending" <?php echo $verification === 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Active Filters Display -->
        <?php if ($search || $status !== 'all' || $verification !== 'all'): ?>
            <div class="active-filters">
                <div class="filters-header">
                    <h4><i class="fas fa-sliders-h"></i> Active Filters</h4>
                </div>
                <div class="filter-tags">
                    <?php if ($search): ?>
                        <span class="filter-tag">
                            <span class="tag-content">
                                <i class="fas fa-search"></i>
                                Search: "<?php echo encode($search); ?>"
                            </span>
                            <a href="<?php echo remove_query_param('search'); ?>" class="tag-remove" title="Remove filter">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    <?php endif; ?>
                    <?php if ($status !== 'all'): ?>
                        <span class="filter-tag">
                            <span class="tag-content">
                                <i class="fas fa-user-circle"></i>
                                Status: <?php echo ucfirst($status); ?>
                            </span>
                            <a href="<?php echo remove_query_param('status'); ?>" class="tag-remove" title="Remove filter">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    <?php endif; ?>
                    <?php if ($verification !== 'all'): ?>
                        <span class="filter-tag">
                            <span class="tag-content">
                                <i class="fas fa-envelope"></i>
                                Verification: <?php echo ucfirst($verification); ?>
                            </span>
                            <a href="<?php echo remove_query_param('verification'); ?>" class="tag-remove" title="Remove filter">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sort Options -->
        <div class="sort-section">
            <div class="sort-header">
                <h4><i class="fas fa-sort-amount-down"></i> Sort Members</h4>
            </div>
            <div class="sort-options">
                <?php
                $sort_options = [
                    'newest' => ['icon' => 'calendar-plus', 'label' => 'Newest First'],
                    'oldest' => ['icon' => 'calendar-minus', 'label' => 'Oldest First'],
                    'name_asc' => ['icon' => 'sort-alpha-down', 'label' => 'Name A-Z'],
                    'name_desc' => ['icon' => 'sort-alpha-up', 'label' => 'Name Z-A'],
                    'last_login' => ['icon' => 'sign-in-alt', 'label' => 'Last Login']
                ];
                
                foreach ($sort_options as $key => $option):
                    $is_active = $sort === $key;
                ?>
                    <a href="?<?php echo build_query(['sort' => $key, 'page' => 1]); ?>"
                       class="sort-option <?php echo $is_active ? 'active' : ''; ?>"
                       title="<?php echo $option['label']; ?>">
                        <i class="fas fa-<?php echo $option['icon']; ?>"></i>
                        <span><?php echo $option['label']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Members Table -->
        <div class="table-section">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Members List</h3>
                <div class="table-actions">
                    <span class="total-count">
                        Showing <?php echo count($members); ?> of <?php echo $total_members; ?> members
                    </span>
                </div>
            </div>

            <?php if (empty($members)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3>No Members Found</h3>
                    <p>
                        <?php if ($search): ?>
                            No results found for "<?php echo encode($search); ?>".
                        <?php else: ?>
                            No members found with the current filters.
                        <?php endif; ?>
                    </p>
                    <?php if ($search || $status !== 'all' || $verification !== 'all'): ?>
                        <a href="users.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> Clear All Filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="members-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Verification</th>
                                <th>Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr id="member-row-<?php echo $member['user_id']; ?>" 
                                    class="<?php echo !$member['is_active'] ? 'inactive' : ''; ?>
                                           <?php echo $member['login_attempts'] >= 3 ? 'locked' : ''; ?>">
                                    <!-- Member Column -->
                                    <td class="member-info-cell">
                                        <div class="member-info">
                                            <div class="member-avatar">
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
                                                <h4 class="member-name"><?php echo encode($member['username']); ?></h4>
                                                <div class="member-meta">
                                                    <span class="member-id">ID: #<?php echo $member['user_id']; ?></span>
                                                    <span class="member-joined">
                                                        Joined: <?php echo format_date($member['created_at'], 'M d, Y'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Contact Column -->
                                    <td class="contact-cell">
                                        <div class="contact-info">
                                            <div class="email-info">
                                                <i class="fas fa-envelope"></i>
                                                <span><?php echo encode($member['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Status Column -->
                                    <td class="status-cell" id="status-<?php echo $member['user_id']; ?>">
                                        <?php if ($member['is_active']): ?>
                                            <span class="status-badge active">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Active</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge inactive">
                                                <i class="fas fa-times-circle"></i>
                                                <span>Inactive</span>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($member['login_attempts'] >= 3): ?>
                                            <div class="status-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span>Account Locked (<?php echo $member['login_attempts']; ?> failed attempts)</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Verification Column -->
                                    <td class="verification-cell">
                                        <?php if ($member['email_verified']): ?>
                                            <span class="verification-badge verified">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Verified</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="verification-badge pending">
                                                <i class="fas fa-clock"></i>
                                                <span>Pending</span>
                                                <?php if ($member['verification_code']): ?>
                                                    <div class="verification-code">
                                                        Code: <?php echo $member['verification_code']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Activity Column -->
                                    <td class="activity-cell">
                                        <div class="activity-info">
                                            <div class="last-login">
                                                <i class="fas fa-sign-in-alt"></i>
                                                <span><?php echo format_date($member['last_login'], 'M d, Y H:i'); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Actions Column -->
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <!-- View Details -->
                                            <a href="member_details.php?id=<?php echo $member['user_id']; ?>" 
                                               class="action-btn view-btn" 
                                               title="View Member Details">
                                                <i class="fas fa-eye"></i>
                                                <span>View</span>
                                            </a>
                                            
                                            <!-- Activate/Deactivate -->
                                            <?php if ($member['is_active']): ?>
                                                <button class="action-btn deactivate-btn ajax-action-btn"
                                                        data-action="deactivate"
                                                        data-user-id="<?php echo $member['user_id']; ?>"
                                                        title="Deactivate Member Account">
                                                    <i class="fas fa-user-slash"></i>
                                                    <span>Deactivate</span>
                                                </button>
                                            <?php else: ?>
                                                <button class="action-btn activate-btn ajax-action-btn"
                                                        data-action="activate"
                                                        data-user-id="<?php echo $member['user_id']; ?>"
                                                        title="Activate Member Account">
                                                    <i class="fas fa-user-check"></i>
                                                    <span>Activate</span>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Resend Verification -->
                                            <?php if (!$member['email_verified']): ?>
                                                <button class="action-btn resend-btn ajax-action-btn"
                                                        data-action="resend_verification"
                                                        data-user-id="<?php echo $member['user_id']; ?>"
                                                        title="Resend Verification Email">
                                                    <i class="fas fa-envelope"></i>
                                                    <span>Resend</span>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Delete (only for inactive) -->
                                            <?php if (!$member['is_active']): ?>
                                                <button class="action-btn delete-btn ajax-action-btn"
                                                        data-action="delete"
                                                        data-user-id="<?php echo $member['user_id']; ?>"
                                                        title="Delete Member Permanently">
                                                    <i class="fas fa-trash"></i>
                                                    <span>Delete</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-section">
                <div class="pagination-info">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo build_query(['page' => 1]); ?>" class="pagination-btn first" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?php echo build_query(['page' => $page - 1]); ?>" class="pagination-btn prev" title="Previous Page">
                            <i class="fas fa-angle-left"></i>
                            <span>Previous</span>
                        </a>
                    <?php endif; ?>
                    
                    <div class="page-numbers">
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?<?php echo build_query(['page' => 1]); ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?<?php echo build_query(['page' => $i]); ?>" 
                               class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                            <a href="?<?php echo build_query(['page' => $total_pages]); ?>"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo build_query(['page' => $page + 1]); ?>" class="pagination-btn next" title="Next Page">
                            <span>Next</span>
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?php echo build_query(['page' => $total_pages]); ?>" class="pagination-btn last" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/users.js"></script>
    <script>
    // Override the existing users.js functionality with a simpler version
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Users page loaded');
        
        // Debug: Log all AJAX buttons found
        const ajaxButtons = document.querySelectorAll('.ajax-action-btn');
        console.log('Found', ajaxButtons.length, 'AJAX buttons');
        
        // Add direct click event listeners to all AJAX buttons
        ajaxButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const action = this.getAttribute('data-action');
                const userId = this.getAttribute('data-user-id');
                
                console.log('Button clicked:', action, 'for user:', userId);
                
                // Get CSRF token from meta tag
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
                
                console.log('CSRF token:', csrfToken ? 'Found' : 'Not found');
                
                if (!action || !userId) {
                    alert('Missing action or user ID');
                    return;
                }
                
                if (!csrfToken) {
                    alert('Security token missing. Please refresh the page.');
                    return;
                }
                
                // Confirm action
                let confirmMessage = '';
                switch(action) {
                    case 'activate':
                        confirmMessage = 'Are you sure you want to activate this member\'s account?';
                        break;
                    case 'deactivate':
                        confirmMessage = 'Are you sure you want to deactivate this member\'s account?';
                        break;
                    case 'delete':
                        confirmMessage = 'WARNING: This will permanently delete the member. This action cannot be undone!\n\nAre you absolutely sure?';
                        break;
                    case 'resend_verification':
                        confirmMessage = 'Resend verification email to this member?';
                        break;
                    default:
                        confirmMessage = 'Are you sure?';
                }
                
                if (!confirm(confirmMessage)) {
                    return;
                }
                
                // Show loading
                const ajaxLoading = document.getElementById('ajaxLoading');
                if (ajaxLoading) {
                    ajaxLoading.style.display = 'flex';
                }
                
                // Disable button
                this.disabled = true;
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Processing...</span>';
                
                // Send AJAX request
                const formData = new FormData();
                formData.append('ajax_action', action);
                formData.append('user_id', userId);
                formData.append('csrf_token', csrfToken);
                
                console.log('Sending AJAX request to users.php');
                
                fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Response received:', data);
                    
                    // Show result
                    if (data.success) {
                        alert(data.message);
                        // Reload the page to see changes
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert('Error: ' + data.message);
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('An error occurred: ' + error.message);
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                })
                .finally(() => {
                    // Hide loading
                    if (ajaxLoading) {
                        ajaxLoading.style.display = 'none';
                    }
                });
            });
        });
    });
    </script>
</body>
</html>