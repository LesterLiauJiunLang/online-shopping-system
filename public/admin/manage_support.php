<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include base.php which contains all our functions
require_once __DIR__ . '/../../lib/base.php';
require_once __DIR__ . '/../../lib/auth.php';

// Check if user is admin
require_login();
$user_role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'member';

if ($user_role !== 'admin') {
    redirect('../memberDashboard.php');
}

// Get current user
$currentUser = get_user_by_id($_SESSION['user_id']);

// Filter setup
$limit = 5; // Changed to match users.php
$page = get('page', 1);
$search = get('search', '');
$status = get('status', 'all');
$sort = get('sort', 'newest');

// Calculate offset for pagination
$offset = ($page - 1) * $limit;

// Get total number of support agents with filters
$totalAgents = get_support_agents_count_filtered($search, $status);
$totalPages = ceil($totalAgents / $limit);

// Get support agents for current page with filters and sorting
$supportAgents = get_support_agents_filtered($limit, $offset, $search, $status, $sort);

// Handle support agent actions
if (isset($_GET['action'])) {
    $agentId = (int)($_GET['id'] ?? 0);
    
    // CSRF validation for actions that modify data
    if (isset($_GET['csrf_token']) && !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }
    
    switch ($_GET['action']) {
        case 'toggle_status':
            $agent = get_user_by_id($agentId);
            if ($agent && $agent['role'] === 'support') {
                $newStatus = $agent['is_active'] ? 0 : 1;
                update_user_status($agentId, $newStatus);
                
                // Log activity
                $activityType = $newStatus ? 'activate_support' : 'deactivate_support';
                $description = $newStatus ? 
                    "Activated support agent: {$agent['username']}" : 
                    "Deactivated support agent: {$agent['username']}";
                log_user_activity($_SESSION['user_id'], $activityType, $description);
                
                $message = "Support agent " . ($newStatus ? "activated" : "deactivated") . " successfully";
                redirect("manage_support.php?page=$page&search=" . urlencode($search) . "&status=$status&sort=$sort&message=" . urlencode($message));
            }
            break;
    }
}

// Generate CSRF token for security
$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Support Agents - Admin Panel</title>
    
    <!-- Include CSS files - NO BOOTSTRAP ALLOWED -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/users.css">
    <link rel="stylesheet" href="../../assets/css/notifications.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    
    <style>
    /* Custom CSS to replace Bootstrap styles */
    .admin-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .admin-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .header-content h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
    }
    
    .header-subtitle {
        margin: 5px 0 0;
        opacity: 0.9;
        font-size: 16px;
    }
    
    .header-actions {
        display: flex;
        gap: 15px;
        margin-top: 10px;
    }
    
    .btn, .btn-back {
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        font-size: 16px;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        border: none;
    }
    
    .btn-primary {
        background: #007bff;
        color: white;
    }
    
    .btn-primary:hover {
        background: #0056b3;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #545b62;
        transform: translateY(-2px);
    }
    
    .btn-back {
        background: #f8f9fa;
        color: #495057;
        border: 1px solid #dee2e6;
    }
    
    .btn-back:hover {
        background: #e9ecef;
        color: #212529;
    }
    
    .stats-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }
    
    .stat-icon.active { background: #28a745; }
    .stat-icon.verified { background: #17a2b8; }
    
    .stat-content h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
    }
    
    .stat-content p {
        margin: 5px 0 0;
        color: #718096;
        font-size: 14px;
    }
    
    .search-filter-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .section-header {
        margin-bottom: 20px;
    }
    
    .section-header h3 {
        margin: 0;
        color: #2d3748;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .filter-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        color: #4a5568;
        font-weight: 500;
    }
    
    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }
    
    .filter-group input:focus,
    .filter-group select:focus {
        outline: none;
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    }
    
    .filter-actions {
        display: flex;
        gap: 15px;
        margin-top: 10px;
    }
    
    .active-filters {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .filters-header h4 {
        margin: 0 0 15px;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filter-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .filter-tag {
        background: #edf2f7;
        padding: 8px 15px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    
    .tag-content {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .tag-remove {
        color: #718096;
        text-decoration: none;
    }
    
    .tag-remove:hover {
        color: #e53e3e;
    }
    
    .sort-section {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .sort-header h4 {
        margin: 0 0 15px;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .sort-options {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .sort-option {
        background: #f7fafc;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        color: #4a5568;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .sort-option:hover {
        background: #edf2f7;
        color: #2d3748;
    }
    
    .sort-option.active {
        background: #4299e1;
        color: white;
        border-color: #4299e1;
    }
    
    .table-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .table-header h3 {
        margin: 0;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .total-count {
        color: #718096;
        font-size: 14px;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .members-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .members-table th {
        background: #f7fafc;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .members-table td {
        padding: 15px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    
    .members-table tr:hover {
        background: #f7fafc;
    }
    
    .members-table tr.inactive {
        opacity: 0.7;
        background: #f8f9fa;
    }
    
    .member-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .member-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
    }
    
    .member-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #a0aec0;
        font-size: 20px;
    }
    
    .member-details {
        flex: 1;
    }
    
    .member-name {
        margin: 0 0 5px;
        color: #2d3748;
        font-weight: 600;
    }
    
    .member-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 13px;
        color: #718096;
    }
    
    .email-info {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #4a5568;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
    }
    
    .status-badge.active {
        background: #c6f6d5;
        color: #22543d;
    }
    
    .status-badge.inactive {
        background: #fed7d7;
        color: #742a2a;
    }
    
    .activity-cell {
        color: #4a5568;
    }
    
    .last-login {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .actions-cell {
        min-width: 200px;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .action-btn {
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .action-btn.view-btn {
        background: #e6fffa;
        color: #285e61;
        border: 1px solid #b2f5ea;
    }
    
    .action-btn.view-btn:hover {
        background: #b2f5ea;
    }
    
    .action-btn.deactivate-btn {
        background: #fff5f5;
        color: #c53030;
        border: 1px solid #fed7d7;
    }
    
    .action-btn.deactivate-btn:hover {
        background: #fed7d7;
    }
    
    .action-btn.activate-btn {
        background: #f0fff4;
        color: #276749;
        border: 1px solid #c6f6d5;
    }
    
    .action-btn.activate-btn:hover {
        background: #c6f6d5;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
    }
    
    .empty-icon {
        font-size: 60px;
        color: #cbd5e0;
        margin-bottom: 20px;
    }
    
    .empty-state h3 {
        margin: 0 0 10px;
        color: #2d3748;
    }
    
    .empty-state p {
        color: #718096;
        margin-bottom: 20px;
    }
    
    .pagination-section {
        background: white;
        border-radius: 8px;
        padding: 25px 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        margin-top: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .pagination-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #f7fafc;
        color: #4a5568;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .pagination-btn:hover {
        background: #edf2f7;
        color: #2d3748;
        text-decoration: none;
    }
    
    .page-numbers {
        display: flex;
        gap: 6px;
        margin: 0 15px;
    }
    
    .page-numbers a {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f7fafc;
        color: #4a5568;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .page-numbers a:hover {
        background: #edf2f7;
        color: #2d3748;
        text-decoration: none;
    }
    
    .page-numbers a.active {
        background: #007bff;
        color: white;
        box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);
    }
    
    .page-dots {
        color: #a0aec0;
        display: flex;
        align-items: center;
        padding: 0 10px;
    }
    
    .pagination-info {
        color: #4a5568;
        font-weight: 500;
    }
    
    .ajax-loading {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    
    .spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    <div class="ajax-loading" id="ajaxLoading">
        <div class="spinner"></div>
    </div>
    
    <div class="admin-container">
        <!-- Page Header -->
        <div class="admin-header">
            <div class="header-content">
                <h1>
                    <i class="fas fa-headset"></i>
                    Manage Support Agents
                </h1>
                <p class="header-subtitle">Manage all support agents in the system</p>
            </div>
            <div class="header-actions">
                <a href="create_support.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add New Agent
                </a>
                <a href="../adminDashboard.php" class="btn-back">
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
                    <h3><?php echo $totalAgents; ?></h3>
                    <p>Total Agents</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count_active_support_agents_filtered($search); ?></h3>
                    <p>Active Agents</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo get_total_assigned_tickets(); ?></h3>
                    <p>Assigned Tickets</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon verified">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo get_open_tickets_count(); ?></h3>
                    <p>Open Tickets</p>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <div class="section-header">
                <h3><i class="fas fa-filter"></i> Search & Filter</h3>
            </div>
            
            <form method="GET" class="filter-form">
                <input type="hidden" name="page" value="1">
                
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="search">
                            <i class="fas fa-search"></i>
                            Search Agents
                        </label>
                        <input type="text" id="search" name="search" 
                               placeholder="Search by username, email or employee ID..." 
                               value="<?php echo encode($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">
                            <i class="fas fa-user-circle"></i>
                            Account Status
                        </label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="manage_support.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Active Filters Display -->
        <?php if ($search || $status !== 'all'): ?>
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
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])); ?>" 
                               class="tag-remove" 
                               title="Remove filter">
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
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'all', 'page' => 1])); ?>" 
                               class="tag-remove" 
                               title="Remove filter">
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
                <h4><i class="fas fa-sort-amount-down"></i> Sort Agents</h4>
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
                    $query_params = array_merge($_GET, ['sort' => $key, 'page' => 1]);
                ?>
                    <a href="?<?php echo http_build_query($query_params); ?>"
                       class="sort-option <?php echo $is_active ? 'active' : ''; ?>"
                       title="<?php echo $option['label']; ?>">
                        <i class="fas fa-<?php echo $option['icon']; ?>"></i>
                        <span><?php echo $option['label']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Agents Table -->
        <div class="table-section">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Support Agents List</h3>
                <div class="table-actions">
                    <span class="total-count">
                        Showing <?php echo min($offset + 1, $totalAgents); ?> - <?php echo min($offset + $limit, $totalAgents); ?> of <?php echo $totalAgents; ?> agents
                    </span>
                </div>
            </div>

            <?php if (empty($supportAgents)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3>No Support Agents Found</h3>
                    <p>
                        <?php if ($search || $status !== 'all'): ?>
                            No results found with the current filters.
                        <?php else: ?>
                            No support agents found in the system.
                        <?php endif; ?>
                    </p>
                    <?php if ($search || $status !== 'all'): ?>
                        <a href="manage_support.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> Clear All Filters
                        </a>
                    <?php endif; ?>
                    <a href="create_support.php" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-user-plus"></i> Add First Support Agent
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="members-table">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Employee ID</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supportAgents as $agent): ?>
                                <tr id="agent-row-<?php echo $agent['user_id']; ?>" 
                                    class="<?php echo !$agent['is_active'] ? 'inactive' : ''; ?>">
                                    <!-- Agent Column -->
                                    <td class="member-info-cell">
                                        <div class="member-info">
                                            <div class="member-avatar">
                                                <?php if ($agent['profile_photo']): ?>
                                                    <img src="../<?php echo encode($agent['profile_photo']); ?>" 
                                                         alt="<?php echo encode($agent['username']); ?>">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="member-details">
                                                <h4 class="member-name"><?php echo encode($agent['username']); ?></h4>
                                                <div class="member-meta">
                                                    <span class="member-id">ID: #<?php echo $agent['user_id']; ?></span>
                                                    <span class="member-joined">
                                                        Last login: <?php echo $agent['last_login'] ? format_date($agent['last_login'], 'M d, Y H:i') : 'Never'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Employee ID Column -->
                                    <td class="contact-cell">
                                        <div class="email-info">
                                            <i class="fas fa-id-card"></i>
                                            <span><?php echo encode($agent['employee_id'] ?? 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    
                                    <!-- Contact Column -->
                                    <td class="contact-cell">
                                        <div class="email-info">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo encode($agent['email']); ?></span>
                                        </div>
                                    </td>
                                    
                                    <!-- Status Column -->
                                    <td class="status-cell">
                                        <?php if ($agent['is_active']): ?>
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
                                    </td>
                                    
                                    <!-- Joined Column -->
                                    <td class="activity-cell">
                                        <div class="last-login">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span><?php echo format_date($agent['created_at'], 'M d, Y'); ?></span>
                                        </div>
                                    </td>
                                    
                                    <!-- Actions Column -->
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <!-- View Details -->
                                            <a href="support_details.php?id=<?php echo $agent['user_id']; ?>" 
                                               class="action-btn view-btn" 
                                               title="View Agent Details">
                                                <i class="fas fa-eye"></i>
                                                <span>View</span>
                                            </a>
                                            
                                            <!-- Activate/Deactivate with data attributes for jQuery -->
                                            <?php if ($agent['is_active']): ?>
                                                <a href="?action=toggle_status&id=<?php echo $agent['user_id']; ?>&csrf_token=<?php echo $csrf_token; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>"
                                                   class="action-btn deactivate-btn confirm-action"
                                                   title="Deactivate Agent"
                                                   data-confirm-message="Are you sure you want to deactivate this agent?">
                                                    <i class="fas fa-user-slash"></i>
                                                    <span>Deactivate</span>
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=toggle_status&id=<?php echo $agent['user_id']; ?>&csrf_token=<?php echo $csrf_token; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>"
                                                   class="action-btn activate-btn confirm-action"
                                                   title="Activate Agent"
                                                   data-confirm-message="Are you sure you want to activate this agent?">
                                                    <i class="fas fa-user-check"></i>
                                                    <span>Activate</span>
                                                </a>
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

        <!-- PAGINATION SECTION - This should be visible -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-section">
                <div class="pagination-info">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </div>
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                           class="pagination-btn first" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="pagination-btn prev" title="Previous Page">
                            <i class="fas fa-angle-left"></i>
                            <span>Previous</span>
                        </a>
                    <?php endif; ?>
                    
                    <div class="page-numbers">
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($totalPages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $totalPages): ?>
                            <?php if ($end_page < $totalPages - 1): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">
                                <?php echo $totalPages; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="pagination-btn next" title="Next Page">
                            <span>Next</span>
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" 
                           class="pagination-btn last" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- jQuery Library -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    
    <script src="../../assets/js/notifications.js"></script>
    
    <script>
    // jQuery event handling (REQUIRED by assignment)
    $(document).ready(function() {
        console.log('Manage Support Agents page loaded');
        
        // Handle confirmations with jQuery (replaces inline onclick)
        $(document).on('click', '.confirm-action', function(e) {
            e.preventDefault();
            var message = $(this).data('confirm-message');
            var url = $(this).attr('href');
            
            if (confirm(message)) {
                window.location.href = url;
            }
            return false;
        });
        
        // Handle filter form submission with loading indicator
        $('.filter-form').on('submit', function() {
            $('#ajaxLoading').fadeIn();
        });
        
        // Check if pagination exists
        const paginationSection = $('.pagination-section');
        if (paginationSection.length) {
            console.log('Pagination section found');
        } else {
            console.log('No pagination section found');
        }
        
        // Handle sort option clicks
        $('.sort-option').click(function() {
            $('#ajaxLoading').fadeIn();
        });
    });
    </script>
</body>
</html>