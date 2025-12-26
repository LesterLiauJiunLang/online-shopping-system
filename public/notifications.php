<?php
// notifications.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set base URL for consistent navigation
$base_url = '/online-shopping-system';

// Check if user is logged in (using session check instead of Auth class)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: {$base_url}/public/login.php");
    exit();
}

require_once __DIR__ . '/../app/config/database.php';
$db = db();
$user_id = $_SESSION['user_id'];

// Get current tab (read or unread)
$current_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['read', 'unread']) ? $_GET['tab'] : 'all';

// Mark all notifications as read when visiting the page
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Redirect to avoid resubmission
    header("Location: notifications.php?tab=read");
    exit();
}

// Mark single notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    
    // Redirect to avoid resubmission
    header("Location: notifications.php");
    exit();
}

// Get unique notifications based on current tab (group by related_id and type to avoid duplicates)
if ($current_tab === 'read') {
    $stmt = $db->prepare("
        SELECT n1.* 
        FROM notifications n1
        INNER JOIN (
            SELECT MAX(notification_id) as max_id, related_id, type
            FROM notifications 
            WHERE user_id = ? AND is_read = 1
            GROUP BY related_id, type
        ) n2 ON n1.notification_id = n2.max_id
        WHERE n1.user_id = ? AND n1.is_read = 1
        ORDER BY n1.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $notifications = $stmt->fetchAll();
    $notification_count = count($notifications);
} elseif ($current_tab === 'unread') {
    $stmt = $db->prepare("
        SELECT n1.* 
        FROM notifications n1
        INNER JOIN (
            SELECT MAX(notification_id) as max_id, related_id, type
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
            GROUP BY related_id, type
        ) n2 ON n1.notification_id = n2.max_id
        WHERE n1.user_id = ? AND n1.is_read = 0
        ORDER BY n1.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $notifications = $stmt->fetchAll();
    $notification_count = count($notifications);
} else {
    // All notifications - get only the latest for each unique (related_id, type) combination
    $stmt = $db->prepare("
        SELECT n1.* 
        FROM notifications n1
        INNER JOIN (
            SELECT MAX(notification_id) as max_id, related_id, type
            FROM notifications 
            WHERE user_id = ?
            GROUP BY related_id, type
        ) n2 ON n1.notification_id = n2.max_id
        WHERE n1.user_id = ?
        ORDER BY n1.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $notifications = $stmt->fetchAll();
    $notification_count = count($notifications);
}

// Get counts for tabs (using DISTINCT to count unique notifications)
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN is_read = 0 THEN CONCAT(related_id, '-', type) END) as unread_count,
        COUNT(DISTINCT CASE WHEN is_read = 1 THEN CONCAT(related_id, '-', type) END) as read_count,
        COUNT(DISTINCT CONCAT(related_id, '-', type)) as total_count
    FROM notifications 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$counts = $stmt->fetch();
$unread_count = $counts['unread_count'] ?? 0;
$read_count = $counts['read_count'] ?? 0;
$total_count = $counts['total_count'] ?? 0;

// Check which tickets have been rated by this user
$rated_tickets = [];
$ticket_details = []; // Store ticket details for display
if (count($notifications) > 0) {
    // Get all ticket_resolved notifications
    $ticket_ids = [];
    foreach ($notifications as $notif) {
        if ($notif['type'] === 'ticket_resolved' && $notif['related_id'] > 0) {
            $ticket_ids[] = (int)$notif['related_id'];
        }
    }
    
    // Get ticket details for these tickets
    if (!empty($ticket_ids)) {
        $placeholders = implode(',', array_fill(0, count($ticket_ids), '?'));
        
        // Get ticket details
        $stmt = $db->prepare("
            SELECT ticket_id, subject, status, created_at, updated_at 
            FROM support_tickets 
            WHERE ticket_id IN ({$placeholders})
        ");
        $stmt->execute($ticket_ids);
        
        while ($row = $stmt->fetch()) {
            $ticket_details[(int)$row['ticket_id']] = $row;
        }
        
        // Check which tickets have been rated
        $stmt = $db->prepare("
            SELECT ticket_id, rating, feedback, created_at as rating_date
            FROM ticket_ratings 
            WHERE ticket_id IN ({$placeholders}) AND user_id = ?
        ");
        
        // Combine ticket_ids and user_id for execute
        $params = array_merge($ticket_ids, [$user_id]);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch()) {
            $rated_tickets[(int)$row['ticket_id']] = $row;
        }
    }
}

$page_title = "Notifications";

// Include header (use the same one from your header.php)
require_once __DIR__ . '/../includes/header.php';

// Check for rating success message
$rating_success = isset($_GET['rating_success']) && $_GET['rating_success'] == 1;

// Determine the correct path for view_ticket.php based on the current file location
// Since notifications.php is in the root, and view_ticket.php is in members folder
$view_ticket_path = 'members/view_ticket.php';
?>

<div class="container">
    <h1>Notifications</h1>
    
    <?php if ($rating_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> Thank you for your rating! Your feedback has been submitted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Debug info (remove in production) -->
    <?php if (false): // Set to true for debugging ?>
    <div class="alert alert-info">
        <strong>Debug Info:</strong><br>
        Total Notifications (Unique): <?php echo $total_count; ?><br>
        Showing: <?php echo count($notifications); ?> notifications<br>
        Ticket IDs in notifications: <?php echo implode(', ', $ticket_ids ?? []); ?>
    </div>
    <?php endif; ?>
    
    <!-- Notification Tabs -->
    <div class="notification-tabs" style="margin-bottom: 25px;">
        <div class="nav nav-tabs" id="notificationTab" role="tablist" style="border-bottom: 2px solid #dee2e6; position: relative;">
            <a href="notifications.php?tab=all" 
               class="nav-link <?php echo $current_tab === 'all' ? 'active' : ''; ?>" 
               style="border: none; background: none; padding: 10px 20px; font-weight: 500; color: #666; border-radius: 0; position: relative; text-decoration: none; display: inline-block;">
                <i class="fas fa-bell"></i> All Notifications
                <?php if ($total_count > 0): ?>
                    <span class="badge" style="background: #6c757d; margin-left: 5px;"><?php echo $total_count; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="notifications.php?tab=unread" 
               class="nav-link <?php echo $current_tab === 'unread' ? 'active' : ''; ?>" 
               style="border: none; background: none; padding: 10px 20px; font-weight: 500; color: #666; border-radius: 0; position: relative; text-decoration: none; display: inline-block;">
                <i class="fas fa-envelope"></i> Unread
                <?php if ($unread_count > 0): ?>
                    <span class="badge" style="background: #e74c3c; margin-left: 5px;"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="notifications.php?tab=read" 
               class="nav-link <?php echo $current_tab === 'read' ? 'active' : ''; ?>" 
               style="border: none; background: none; padding: 10px 20px; font-weight: 500; color: #666; border-radius: 0; position: relative; text-decoration: none; display: inline-block;">
                <i class="fas fa-check-circle"></i> Read
                <?php if ($read_count > 0): ?>
                    <span class="badge" style="background: #28a745; margin-left: 5px;"><?php echo $read_count; ?></span>
                <?php endif; ?>
            </a>
            
            <!-- Active tab indicator -->
            <div id="tabIndicator" style="position: absolute; bottom: -2px; height: 3px; background: #5a4bff; transition: all 0.3s ease; border-radius: 3px 3px 0 0; z-index: 1;"></div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="notification-actions" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <?php if ($current_tab === 'unread' && $unread_count > 0): ?>
            <a href="?mark_all_read=1&tab=<?php echo $current_tab; ?>" class="btn btn-primary">
                <i class="fas fa-check-double"></i> Mark All as Read
            </a>
        <?php else: ?>
            <div></div> <!-- Empty div for spacing -->
        <?php endif; ?>
        
        <div style="color: #666; font-size: 0.9em;">
            Showing <?php echo $notification_count; ?> notification<?php echo $notification_count != 1 ? 's' : ''; ?> 
            (<?php echo $unread_count; ?> unread, <?php echo $read_count; ?> read)
        </div>
    </div>
    
    <!-- Notifications List -->
    <?php if (count($notifications) > 0): ?>
        <div class="notifications-list">
            <?php 
            // Track displayed notifications to avoid duplicates in display
            $displayed_notifications = [];
            
            foreach ($notifications as $notif): 
                // Create a unique key for this notification
                $unique_key = $notif['related_id'] . '-' . $notif['type'];
                
                // Skip if we've already displayed this notification
                if (in_array($unique_key, $displayed_notifications)) {
                    continue;
                }
                
                // Add to displayed list
                $displayed_notifications[] = $unique_key;
                
                $ticket_id = (int)$notif['related_id'];
                $is_rated = isset($rated_tickets[$ticket_id]);
                $ticket_info = isset($ticket_details[$ticket_id]) ? $ticket_details[$ticket_id] : null;
            ?>
                <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
                     style="padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px; background-color: <?php echo $notif['is_read'] ? '#f9f9f9' : '#f0f7ff'; ?>; position: relative; z-index: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                <?php if (!$notif['is_read']): ?>
                                    <span class="unread-indicator" style="display: inline-block; width: 10px; height: 10px; background: #e74c3c; border-radius: 50%; margin-right: 10px;"></span>
                                <?php else: ?>
                                    <span class="read-indicator" style="display: inline-block; width: 10px; height: 10px; background: #28a745; border-radius: 50%; margin-right: 10px;"></span>
                                <?php endif; ?>
                                
                                <h4 style="margin: 0; color: #333; flex: 1;">
                                    <?php echo htmlspecialchars($notif['title']); ?>
                                </h4>
                                
                                <?php if ($current_tab === 'all' && !$notif['is_read']): ?>
                                    <a href="?mark_read=<?php echo $notif['notification_id']; ?>&tab=<?php echo $current_tab; ?>" 
                                       class="btn btn-sm btn-success" 
                                       style="margin-left: 10px; padding: 3px 8px; font-size: 0.8em;">
                                        <i class="fas fa-check"></i> Mark Read
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <p style="margin: 5px 0 5px 20px; color: #666;"><?php echo htmlspecialchars($notif['message']); ?></p>
                            
                            <!-- ★★★ Ticket Information & Actions ★★★ -->
                            <?php if ($notif['type'] === 'ticket_resolved' && $ticket_info): ?>
                                <div style="margin: 10px 0 5px 20px; padding: 10px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #28a745;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
                                        <div style="flex: 1; min-width: 200px;">
                                            <h5 style="margin: 0 0 8px 0; color: #28a745; font-size: 0.95em;">
                                                <i class="fas fa-ticket-alt"></i> Ticket #<?php echo $ticket_id; ?>
                                            </h5>
                                            <p style="margin: 0 0 5px 0; color: #555; font-size: 0.9em;">
                                                <strong>Subject:</strong> <?php echo htmlspecialchars($ticket_info['subject']); ?>
                                            </p>
                                            <p style="margin: 0 0 5px 0; color: #555; font-size: 0.9em;">
                                                <strong>Status:</strong> 
                                                <span class="badge" style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8em;">
                                                    <?php echo htmlspecialchars($ticket_info['status']); ?>
                                                </span>
                                            </p>
                                            <p style="margin: 0 0 5px 0; color: #777; font-size: 0.85em;">
                                                <strong>Resolved:</strong> <?php echo date('M d, Y h:i A', strtotime($ticket_info['updated_at'])); ?>
                                            </p>
                                        </div>
                                        
                                        <div style="margin-top: 5px; display: flex; gap: 10px; flex-wrap: wrap;">
                                            <!-- View Ticket Button - FIXED PATH -->
                                            <a href="<?php echo $view_ticket_path; ?>?id=<?php echo $ticket_id; ?>" 
                                               class="btn btn-info btn-sm"
                                               style="background: #17a2b8; color: white; padding: 5px 12px; border-radius: 4px; text-decoration: none; border: none; display: inline-flex; align-items: center; gap: 5px; font-size: 0.85em;">
                                                <i class="fas fa-eye"></i> View Ticket
                                            </a>
                                            
                                            <!-- Rating Section -->
                                            <?php if ($is_rated): ?>
                                                <div style="display: flex; flex-direction: column; gap: 5px; min-width: 180px;">
                                                    <div class="rated-message" style="background: #28a745; color: white; padding: 6px 10px; border-radius: 4px; font-size: 0.85em; display: inline-flex; align-items: center; gap: 5px; justify-content: center;">
                                                        <i class="fas fa-check-circle"></i> Thank You For Rating!
                                                    </div>
                                                    
                                                    <!-- Rating Details -->
                                                    <?php if (isset($rated_tickets[$ticket_id]['rating'])): 
                                                        $rating_data = $rated_tickets[$ticket_id];
                                                    ?>
                                                        <div style="font-size: 0.8em; color: #666; background: white; padding: 6px; border-radius: 4px; border: 1px solid #e0e0e0;">
                                                            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                                                <strong style="color: #333; font-size: 0.9em;">Rating:</strong> 
                                                                <div style="color: #FFD700; font-size: 1.1em;">
                                                                    <?php 
                                                                    $stars = str_repeat('★', $rating_data['rating']) . str_repeat('☆', 5 - $rating_data['rating']);
                                                                    echo $stars;
                                                                    ?>
                                                                </div>
                                                                <span style="background: #f8f9fa; padding: 2px 6px; border-radius: 8px; font-weight: bold; color: #333; font-size: 0.85em;">
                                                                    <?php echo $rating_data['rating']; ?>/5
                                                                </span>
                                                            </div>
                                                            <?php if (!empty($rating_data['feedback'])): ?>
                                                                <div style="margin-top: 4px; padding-top: 4px; border-top: 1px dashed #eee;">
                                                                    <strong style="color: #333; display: block; margin-bottom: 2px; font-size: 0.9em;">Feedback:</strong> 
                                                                    <div style="color: #555; font-style: italic; background: #f9f9f9; padding: 4px; border-radius: 3px; font-size: 0.85em;">
                                                                        "<?php echo htmlspecialchars(substr($rating_data['feedback'], 0, 80)); ?>"
                                                                        <?php if (strlen($rating_data['feedback']) > 80): ?>...<?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div style="margin-top: 4px; color: #888; font-size: 0.75em; text-align: right;">
                                                                <i class="far fa-clock"></i> <?php echo date('M d, Y', strtotime($rating_data['rating_date'])); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <!-- Rate Button - FIXED PATH -->
                                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                                    <a href="members/ticket_rating.php?ticket_id=<?php echo $ticket_id; ?>"
                                                       class="btn btn-warning rate-btn" 
                                                       style="background: linear-gradient(135deg, #ffc107, #ff9800); color: #212529; padding: 6px 12px; border-radius: 4px; text-decoration: none; border: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 500; font-size: 0.85em;">
                                                        <i class="fas fa-star"></i> Rate Support
                                                    </a>
                                                    <small style="color: #666; font-size: 0.75em; max-width: 150px;">
                                                        <i class="fas fa-info-circle"></i> Rate your support experience
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <small style="color: #999; display: block; margin-top: 5px; margin-left: 20px;">
                                <i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- No notifications message for specific tabs -->
        <?php if ($notification_count === 0): ?>
            <div class="empty-state" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px; margin-top: 20px;">
                <?php if ($current_tab === 'unread'): ?>
                    <i class="fas fa-check-circle" style="font-size: 3em; color: #28a745; margin-bottom: 15px;"></i>
                    <h3>No Unread Notifications</h3>
                    <p style="color: #666;">You're all caught up! No new notifications.</p>
                    <a href="notifications.php?tab=read" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-history"></i> View Read Notifications
                    </a>
                <?php elseif ($current_tab === 'read'): ?>
                    <i class="fas fa-inbox" style="font-size: 3em; color: #6c757d; margin-bottom: 15px;"></i>
                    <h3>No Read Notifications</h3>
                    <p style="color: #666;">You haven't read any notifications yet.</p>
                    <a href="notifications.php?tab=unread" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-envelope"></i> View Unread Notifications
                    </a>
                <?php else: ?>
                    <i class="fas fa-bell-slash" style="font-size: 3em; color: #6c757d; margin-bottom: 15px;"></i>
                    <h3>No Notifications</h3>
                    <p style="color: #666;">You don't have any notifications yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- No notifications at all -->
        <div class="empty-state" style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px; margin-top: 20px;">
            <i class="fas fa-bell-slash" style="font-size: 4em; color: #6c757d; margin-bottom: 20px; opacity: 0.5;"></i>
            <h3 style="color: #333; margin-bottom: 10px;">No Notifications</h3>
            <p style="color: #666; max-width: 400px; margin: 0 auto 20px;">
                You don't have any notifications yet. Notifications will appear here when you have updates about your tickets, orders, or account.
            </p>
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo $view_ticket_path; ?>" class="btn btn-primary">
                    <i class="fas fa-ticket-alt"></i> View My Tickets
                </a>
                <a href="members/dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .rate-btn:hover {
        background: linear-gradient(135deg, #ffb300, #f57c00) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(255, 193, 7, 0.2);
        transition: all 0.3s ease;
    }
    
    .btn-info:hover {
        background: #138496 !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2);
        transition: all 0.3s ease;
    }
    
    .rated-message {
        background: linear-gradient(135deg, #28a745, #20c997) !important;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
    }
    
    .notification-item {
        transition: all 0.3s ease;
    }
    
    .notification-item:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        border: 1px solid #c3e6cb;
        color: #155724;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.1);
    }
    
    .badge {
        display: inline-block;
        padding: 3px 8px;
        font-size: 0.75em;
        font-weight: bold;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }
    
    /* Tab styles - FIXED */
    .nav-tabs {
        display: flex;
        position: relative;
        background: white;
        border-radius: 8px 8px 0 0;
        padding: 0 5px;
    }
    
    .nav-tabs .nav-link {
        border: none;
        color: #666;
        font-weight: 500;
        position: relative;
        transition: all 0.3s ease;
        padding: 12px 24px !important;
        margin: 0 2px;
        border-radius: 8px 8px 0 0;
        z-index: 2;
        background: transparent;
    }
    
    .nav-tabs .nav-link:hover {
        color: #5a4bff;
        background-color: rgba(90, 75, 255, 0.05);
    }
    
    .nav-tabs .nav-link.active {
        color: #5a4bff !important;
        background-color: white;
        font-weight: 600;
        box-shadow: 0 -2px 4px rgba(0,0,0,0.05);
    }
    
    #tabIndicator {
        z-index: 1;
        pointer-events: none;
    }
    
    .btn-outline-primary {
        border: 1px solid #5a4bff;
        color: #5a4bff;
        background: transparent;
    }
    
    .btn-outline-primary:hover {
        background: #5a4bff;
        color: white;
    }
    
    .unread-indicator {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
</style>

<script>
    // Add animation for rating success message
    document.addEventListener('DOMContentLoaded', function() {
        // Check if URL has rating_success parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('rating_success')) {
            // Scroll to top to show success message
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Remove parameter from URL without reload
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
        
        // Add click animation to rate buttons
        document.querySelectorAll('.rate-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                // Add loading effect
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                this.style.opacity = '0.7';
                this.disabled = true;
                
                // Restore button after 3 seconds if page doesn't navigate
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.opacity = '1';
                    this.disabled = false;
                }, 3000);
            });
        });
        
        // Position tab indicator - FIXED VERSION
        function positionTabIndicator() {
            const activeTab = document.querySelector('.nav-link.active');
            const tabIndicator = document.getElementById('tabIndicator');
            
            if (activeTab && tabIndicator) {
                const rect = activeTab.getBoundingClientRect();
                const container = activeTab.parentElement;
                const containerRect = container.getBoundingClientRect();
                
                // Calculate position relative to container
                const left = rect.left - containerRect.left;
                const width = rect.width;
                
                tabIndicator.style.left = left + 'px';
                tabIndicator.style.width = width + 'px';
                tabIndicator.style.display = 'block';
            }
        }
        
        // Position indicator on load with a small delay to ensure rendering
        setTimeout(positionTabIndicator, 10);
        window.addEventListener('resize', positionTabIndicator);
        
        // Re-position indicator when images load
        window.addEventListener('load', positionTabIndicator);
        
        // Tab switching animation
        document.querySelectorAll('.nav-link').forEach(tab => {
            tab.addEventListener('click', function() {
                // Add loading state
                const container = document.querySelector('.notifications-list');
                if (container) {
                    container.style.opacity = '0.5';
                    container.style.transition = 'opacity 0.3s';
                }
                
                // Remove loading state after delay (will be replaced by page load)
                setTimeout(() => {
                    if (container) {
                        container.style.opacity = '1';
                    }
                }, 500);
            });
        });
        
        // Fix for iOS Safari scrolling issue
        let isScrolling;
        window.addEventListener('scroll', function() {
            window.clearTimeout(isScrolling);
            positionTabIndicator();
            
            isScrolling = setTimeout(function() {
                positionTabIndicator();
            }, 66); // Run every 66ms for smoother updates
        }, false);
    });
</script>

<?php 
// Check if footer.php exists before including
$footer_path = __DIR__ . '/../includes/footer.php';
if (file_exists($footer_path)) {
    include $footer_path;
} else {
    // Fallback minimal footer
    echo '</body></html>';
}
