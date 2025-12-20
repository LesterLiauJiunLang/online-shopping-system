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

// Initialize variables
$search = get('search', '');
$page = get('page', 1);
$limit = 20;
$offset = ($page - 1) * $limit;
$total_members = 0;
$total_pages = 1;
$members = [];
$error = '';
$success = '';

// Handle member actions
if (is_post()) {
    $action = post('action');
    $user_id = post('user_id');
    
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $error = "Security token invalid. Please try again.";
    } elseif ($action && $user_id) {
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $_db->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $success = "Member activated successfully.";
                    break;
                    
                case 'deactivate':
                    $stmt = $_db->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $success = "Member deactivated successfully.";
                    break;
                    
                case 'delete':
                    $order_check = $_db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                    $order_check->execute([$user_id]);
                    $order_count = $order_check->fetchColumn();
                    
                    if ($order_count > 0) {
                        $error = "Cannot delete member with existing orders. Deactivate instead.";
                    } else {
                        $stmt = $_db->prepare("DELETE FROM users WHERE user_id = ? AND role = 'member'");
                        $stmt->execute([$user_id]);
                        if ($stmt->rowCount() > 0) {
                            $success = "Member deleted successfully.";
                        } else {
                            $error = "Cannot delete admin users.";
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
                            $success = "Verification email resent successfully.";
                        } else {
                            $error = "Failed to send verification email.";
                        }
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle search and fetch members
try {
    $count_query = "SELECT COUNT(*) FROM users WHERE role = 'member'";
    $params = [];
    
    if (!empty($search)) {
        $count_query .= " AND (username LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param];
    }
    
    $stmt = $_db->prepare($count_query);
    $stmt->execute($params);
    $total_members = $stmt->fetchColumn();
    
    $total_pages = ceil($total_members / $limit);
    
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
              WHERE role = 'member'";
    
    if (!empty($search)) {
        $query .= " AND (username LIKE ? OR email LIKE ?)";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    
    $stmt = $_db->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindValue(1, "%$search%");
        $stmt->bindValue(2, "%$search%");
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $members = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Member Management - Admin Panel</title>
    <?php require_once '../../includes/header.php'; ?>
</head>
<body>
    <div class="container">
        <h2>Member Management</h2>
        
        <p><a href="../dashboard.php">&larr; Back to Dashboard</a></p>
        
        <?php if ($success): ?>
            <div class="success"><?php echo encode($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo encode($error); ?></div>
        <?php endif; ?>
        
        <form method="GET">
            <input type="text" name="search" placeholder="Search by username or email..." 
                   value="<?php echo encode($search); ?>">
            <button type="submit">Search</button>
            <?php if ($search): ?>
                <a href="users.php">Clear</a>
            <?php endif; ?>
        </form>
        
        <p>Total Members: <?php echo $total_members; ?></p>
        
        <?php if (empty($members)): ?>
            <p>No members found<?php echo $search ? ' for "' . encode($search) . '"' : ''; ?>.</p>
        <?php else: ?>
            <table border="1" cellpadding="10" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Last Login</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td>#<?php echo $member['user_id']; ?></td>
                            <td><?php echo encode($member['username']); ?></td>
                            <td><?php echo encode($member['email']); ?></td>
                            <td>
                                <?php echo $member['is_active'] ? 'Active' : 'Inactive'; ?>
                                <?php if ($member['login_attempts'] >= 3): ?>
                                    <br>Locked (<?php echo $member['login_attempts']; ?> attempts)
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $member['email_verified'] ? 'Verified' : 'Pending'; ?>
                                <?php if (!$member['email_verified'] && $member['verification_code']): ?>
                                    <br>Code: <?php echo $member['verification_code']; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo format_date($member['last_login'], 'M d, Y H:i'); ?></td>
                            <td><?php echo format_date($member['created_at'], 'M d, Y'); ?></td>
                            <td>
                                <a href="member_details.php?id=<?php echo $member['user_id']; ?>">View</a>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                                    <?php if ($member['is_active']): ?>
                                        <button type="submit" name="action" value="deactivate">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate">Activate</button>
                                    <?php endif; ?>
                                </form>
                                
                                <?php if (!$member['email_verified']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                                        <button type="submit" name="action" value="resend_verification">Resend</button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if (!$member['is_active']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                                        <button type="submit" name="action" value="delete" onclick="return confirm('Delete this member permanently?');">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if ($total_pages > 1): ?>
            <div>
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php require_once '../../includes/footer.php'; ?>
</body>
</html>