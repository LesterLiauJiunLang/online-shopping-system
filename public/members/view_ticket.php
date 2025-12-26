<?php
// members/view_ticket.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$ticket_id = $_GET['id'] ?? 0;
require_once __DIR__ . '/../../app/config/database.php';
$db = db();

// If no ticket ID is provided, show the user's ticket list with categories and pagination
if ($ticket_id == 0) {
    $user_id = $_SESSION['user_id'];
    
    // Get filter parameters
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $priority = isset($_GET['priority']) ? $_GET['priority'] : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE conditions
    $where_conditions = ["t.user_id = ?"];
    $params = [$user_id];
    
    if ($status !== 'all' && in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
        $where_conditions[] = "t.status = ?";
        $params[] = $status;
    }
    
    if ($priority !== 'all' && in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
        $where_conditions[] = "t.priority = ?";
        $params[] = $priority;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(t.subject LIKE ? OR t.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total tickets
    $count_query = "SELECT COUNT(*) as total FROM support_tickets t WHERE $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_tickets = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_tickets / $limit);
    
    // Get tickets with pagination
    $query = "
        SELECT t.*,
               (SELECT COUNT(*) FROM notifications n 
                WHERE n.user_id = ? 
                AND n.related_id = t.ticket_id 
                AND n.is_read = 0 
                AND n.type = 'support_reply') as unread_count
        FROM support_tickets t
        WHERE $where_clause
        ORDER BY t.updated_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    // Add user_id again for the subquery
    array_unshift($params, $user_id);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
    
    // Get statistics for each status
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count
        FROM support_tickets 
        WHERE user_id = ?
    ";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Support Tickets</title>
        <!-- jQuery as per spec -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- Font Awesome for icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            /* Reset and base styles */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
                color: #333;
                line-height: 1.6;
            }
            
            /* Container */
            .ticket-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            
            /* Header styles */
            .ticket-header {
                background: linear-gradient(135deg, #5a4bff 0%, #3a2ee0 100%);
                color: white;
                padding: 30px;
                border-radius: 15px;
                margin-bottom: 30px;
                box-shadow: 0 10px 30px rgba(227, 225, 240, 0.2);
                position: relative;
                overflow: hidden;
            }
            
            .ticket-header::before {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 300px;
                height: 300px;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                border-radius: 50%;
                transform: translate(30%, -30%);
            }
            
            .ticket-header h1 {
                font-size: 2.5em;
                margin-bottom: 10px;
                position: relative;
                z-index: 1;
            }
            
            .ticket-header p {
                font-size: 1.1em;
                opacity: 0.9;
                position: relative;
                z-index: 1;
            }
            
            .header-actions {
                position: relative;
                z-index: 1;
                margin-top: 20px;
            }
            
            /* Button styles */
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 12px 25px;
                background: rgba(255, 255, 255, 0.2);
                color: white;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 600;
                border: 2px solid rgba(255, 255, 255, 0.3);
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
            }
            
            .btn:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(255, 255, 255, 0.1);
            }
            
            /* Statistics cards */
            .ticket-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                text-align: center;
                transition: all 0.3s ease;
                border: 1px solid rgba(90, 75, 255, 0.1);
            }
            
            .stat-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
                border-color: rgba(90, 75, 255, 0.2);
            }
            
            .stat-card h3 {
                color: #666;
                font-size: 1em;
                margin-bottom: 10px;
                font-weight: 600;
            }
            
            .stat-value {
                font-size: 2.5em;
                font-weight: 700;
                color: #5a4bff;
                margin: 15px 0;
                display: block;
            }
            
            .stat-card p {
                color: #888;
                font-size: 0.9em;
                margin: 0;
            }
            
            /* Filter tabs */
            .filter-tabs {
                display: flex;
                gap: 10px;
                margin-bottom: 30px;
                flex-wrap: wrap;
            }
            
            .filter-tab {
                padding: 12px 25px;
                border-radius: 10px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
                background: white;
                color: #666;
                border: 2px solid #e0e0e0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .filter-tab:hover {
                background: #f8f9fa;
                border-color: #d0d0d0;
                transform: translateY(-2px);
            }
            
            .filter-tab.active {
                background: #5a4bff;
                color: white;
                border-color: #5a4bff;
            }
            
            .tab-badge {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 0.85em;
                font-weight: 600;
            }
            
            .filter-tab.active .tab-badge {
                background: rgba(255, 255, 255, 0.3);
            }
            
            /* Search and filter section */
            .filter-section {
                background: white;
                padding: 30px;
                border-radius: 12px;
                margin-bottom: 30px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            }
            
            .filter-section h3 {
                color: #333;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .filter-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .filter-group {
                display: flex;
                flex-direction: column;
            }
            
            .filter-group label {
                display: flex;
                align-items: center;
                gap: 10px;
                color: #666;
                margin-bottom: 8px;
                font-weight: 600;
            }
            
            .filter-group input,
            .filter-group select {
                padding: 12px 15px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 1em;
                transition: all 0.3s ease;
            }
            
            .filter-group input:focus,
            .filter-group select:focus {
                outline: none;
                border-color: #5a4bff;
                box-shadow: 0 0 0 3px rgba(90, 75, 255, 0.1);
            }
            
            .filter-actions {
                display: flex;
                gap: 15px;
                justify-content: flex-end;
                margin-top: 20px;
            }
            
            .btn-primary {
                background: #5a4bff;
                color: white;
                border: none;
                cursor: pointer;
                font-weight: 600;
            }
            
            .btn-primary:hover {
                background: #4a3bdf;
            }
            
            .btn-secondary {
                background: #6c757d;
                color: white;
                border: none;
                cursor: pointer;
            }
            
            /* Active filters display */
            .active-filters {
                background: linear-gradient(135deg, #5a4bff 0%, #3a2ee0 100%);
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 30px;
                color: white;
            }
            
            .active-filters h4 {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 15px;
                color: white;
            }
            
            .filter-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .filter-tag {
                background: rgba(255, 255, 255, 0.2);
                padding: 8px 15px;
                border-radius: 20px;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.3);
            }
            
            .tag-content {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .tag-remove {
                color: white;
                text-decoration: none;
                opacity: 0.7;
                transition: opacity 0.3s ease;
            }
            
            .tag-remove:hover {
                opacity: 1;
            }
            
            /* Ticket list */
            .ticket-list {
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                margin-bottom: 40px;
            }
            
            .list-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f2f5;
            }
            
            .list-header h2 {
                display: flex;
                align-items: center;
                gap: 10px;
                color: #333;
                margin: 0;
            }
            
            .ticket-item {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 15px;
                border: 2px solid transparent;
                transition: all 0.3s ease;
            }
            
            .ticket-item:hover {
                background: white;
                border-color: #5a4bff;
                transform: translateY(-3px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            }
            
            .ticket-subject {
                font-size: 1.2em;
                font-weight: 600;
                margin-bottom: 10px;
                color: #333;
            }
            
            .ticket-subject a {
                color: #333;
                text-decoration: none;
                transition: color 0.3s ease;
            }
            
            .ticket-subject a:hover {
                color: #5a4bff;
            }
            
            .ticket-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin-bottom: 15px;
                align-items: center;
            }
            
            .status-badge {
                padding: 6px 15px;
                border-radius: 20px;
                font-size: 0.85em;
                font-weight: 600;
                color: white;
            }
            
            .status-open { background: #28a745; }
            .status-in_progress { background: #007bff; }
            .status-resolved { background: #6c757d; }
            .status-closed { background: #343a40; }
            
            .priority-badge {
                padding: 6px 15px;
                border-radius: 20px;
                font-size: 0.85em;
                font-weight: 600;
                color: white;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            
            .priority-low { background: #6c757d; }
            .priority-medium { background: #ffc107; color: #333; }
            .priority-high { background: #fd7e14; }
            .priority-urgent { background: #dc3545; }
            
            .new-messages {
                background: #dc3545;
                color: white;
                padding: 4px 10px;
                border-radius: 10px;
                font-size: 0.8em;
                font-weight: 600;
                margin-left: 10px;
            }
            
            .ticket-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 15px;
                border-top: 1px solid #e0e0e0;
            }
            
            .ticket-actions {
                display: flex;
                gap: 10px;
            }
            
            .btn-small {
                padding: 8px 15px;
                font-size: 0.9em;
            }
            
            /* Empty state */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                background: white;
                border-radius: 12px;
                border: 2px dashed #e0e0e0;
            }
            
            .empty-icon {
                font-size: 4em;
                color: #dee2e6;
                margin-bottom: 20px;
            }
            
            .empty-state h3 {
                color: #666;
                margin-bottom: 10px;
            }
            
            .empty-state p {
                color: #999;
                margin-bottom: 20px;
            }
            
            /* Pagination */
            .pagination-section {
                background: white;
                padding: 30px;
                border-radius: 12px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            }
            
            .pagination-info {
                color: #666;
                font-weight: 600;
            }
            
            .pagination-controls {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .pagination-btn {
                padding: 10px 20px;
                background: #f8f9fa;
                color: #666;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            
            .pagination-btn:hover {
                background: #e9ecef;
                color: #333;
                text-decoration: none;
            }
            
            .page-numbers {
                display: flex;
                gap: 5px;
                margin: 0 15px;
            }
            
            .page-numbers a {
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f8f9fa;
                color: #666;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .page-numbers a:hover {
                background: #e9ecef;
                color: #333;
            }
            
            .page-numbers a.active {
                background: #5a4bff;
                color: white;
                box-shadow: 0 4px 8px rgba(90, 75, 255, 0.2);
            }
            
            /* Responsive design */
            @media (max-width: 768px) {
                .ticket-container {
                    padding: 15px;
                }
                
                .ticket-header {
                    padding: 20px;
                    text-align: center;
                }
                
                .ticket-header h1 {
                    font-size: 2em;
                }
                
                .ticket-stats {
                    grid-template-columns: 1fr;
                }
                
                .filter-tabs {
                    justify-content: center;
                }
                
                .filter-grid {
                    grid-template-columns: 1fr;
                }
                
                .filter-actions {
                    flex-direction: column;
                }
                
                .pagination-section {
                    flex-direction: column;
                    gap: 20px;
                }
                
                .pagination-controls {
                    flex-wrap: wrap;
                    justify-content: center;
                }
                
                .ticket-footer {
                    flex-direction: column;
                    gap: 15px;
                    align-items: flex-start;
                }
                
                .ticket-actions {
                    width: 100%;
                    justify-content: flex-end;
                }
            }
            
            @media (max-width: 480px) {
                .filter-tab {
                    padding: 10px 15px;
                    font-size: 0.9em;
                }
                
                .ticket-meta {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 8px;
                }
                
                .pagination-btn span {
                    display: none;
                }
                
                .pagination-btn i {
                    margin: 0;
                }
            }
        </style>
    </head>
    <body>
        <?php include_once __DIR__ . '/../../includes/header.php'; ?>
        
        <div class="ticket-container">
            <!-- Page Header -->
            <div class="ticket-header">
                <h1><i class="fas fa-ticket-alt"></i> My Support Tickets</h1>
                <p>Manage all your support requests and communications</p>
                <div class="header-actions">
                    <a href="../support/create_ticket.php" class="btn">
                        <i class="fas fa-plus"></i> Create New Ticket
                    </a>
                </div>
            </div>

            <!-- Ticket Statistics -->
            <div class="ticket-stats">
                <div class="stat-card">
                    <h3>Total Tickets</h3>
                    <span class="stat-value"><?php echo $stats['total'] ?? 0; ?></span>
                    <p>All Support Requests</p>
                </div>
                <div class="stat-card">
                    <h3>Open</h3>
                    <span class="stat-value"><?php echo $stats['open_count'] ?? 0; ?></span>
                    <p>Waiting for Response</p>
                </div>
                <div class="stat-card">
                    <h3>In Progress</h3>
                    <span class="stat-value"><?php echo $stats['in_progress_count'] ?? 0; ?></span>
                    <p>Being Worked On</p>
                </div>
                <div class="stat-card">
                    <h3>Resolved</h3>
                    <span class="stat-value"><?php echo $stats['resolved_count'] ?? 0; ?></span>
                    <p>Successfully Completed</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=all&priority=<?php echo $priority; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-bars"></i>
                    All Tickets
                    <span class="tab-badge"><?php echo $stats['total'] ?? 0; ?></span>
                </a>
                <a href="?status=open&priority=<?php echo $priority; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status == 'open' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open"></i>
                    Open
                    <span class="tab-badge"><?php echo $stats['open_count'] ?? 0; ?></span>
                </a>
                <a href="?status=in_progress&priority=<?php echo $priority; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status == 'in_progress' ? 'active' : ''; ?>">
                    <i class="fas fa-spinner"></i>
                    In Progress
                    <span class="tab-badge"><?php echo $stats['in_progress_count'] ?? 0; ?></span>
                </a>
                <a href="?status=resolved&priority=<?php echo $priority; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status == 'resolved' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    Resolved
                    <span class="tab-badge"><?php echo $stats['resolved_count'] ?? 0; ?></span>
                </a>
                <a href="?status=closed&priority=<?php echo $priority; ?>&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $status == 'closed' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i>
                    Closed
                    <span class="tab-badge"><?php echo $stats['closed_count'] ?? 0; ?></span>
                </a>
            </div>

            <!-- Search and Filter Section -->
            <div class="filter-section">
                <h3><i class="fas fa-filter"></i> Search & Filter Tickets</h3>
                
                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="search">
                                <i class="fas fa-search"></i>
                                Search Tickets
                            </label>
                            <input type="text" id="search" name="search" 
                                   placeholder="Search by subject or description..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="priority">
                                <i class="fas fa-flag"></i>
                                Priority Filter
                            </label>
                            <select id="priority" name="priority">
                                <option value="all" <?php echo $priority === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                                <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low Priority</option>
                                <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Medium Priority</option>
                                <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High Priority</option>
                                <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>Urgent Priority</option>
                            </select>
                        </div>
                    </div>
                    
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="view_ticket.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Active Filters Display -->
            <?php if ($search || $status !== 'all' || $priority !== 'all'): ?>
                <div class="active-filters">
                    <h4><i class="fas fa-sliders-h"></i> Active Filters</h4>
                    <div class="filter-tags">
                        <?php if ($search): ?>
                            <span class="filter-tag">
                                <span class="tag-content">
                                    <i class="fas fa-search"></i>
                                    Search: "<?php echo htmlspecialchars($search); ?>"
                                </span>
                                <a href="?status=<?php echo $status; ?>&priority=<?php echo $priority; ?>" 
                                   class="tag-remove" 
                                   title="Remove filter">
                                    <i class="fas fa-times"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        <?php if ($status !== 'all'): ?>
                            <span class="filter-tag">
                                <span class="tag-content">
                                    <i class="fas fa-filter"></i>
                                    Status: <?php 
                                        $status_display = [
                                            'open' => 'Open',
                                            'in_progress' => 'In Progress',
                                            'resolved' => 'Resolved',
                                            'closed' => 'Closed'
                                        ];
                                        echo $status_display[$status] ?? ucfirst($status); 
                                    ?>
                                </span>
                                <a href="?priority=<?php echo $priority; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="tag-remove" 
                                   title="Remove filter">
                                    <i class="fas fa-times"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        <?php if ($priority !== 'all'): ?>
                            <span class="filter-tag">
                                <span class="tag-content">
                                    <i class="fas fa-flag"></i>
                                    Priority: <?php echo ucfirst($priority); ?>
                                </span>
                                <a href="?status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="tag-remove" 
                                   title="Remove filter">
                                    <i class="fas fa-times"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tickets List -->
            <div class="ticket-list">
                <div class="list-header">
                    <h2>
                        <i class="fas fa-ticket-alt"></i>
                        <?php 
                        $category_titles = [
                            'all' => 'All Support Tickets',
                            'open' => 'Open Tickets',
                            'in_progress' => 'Tickets In Progress',
                            'resolved' => 'Resolved Tickets',
                            'closed' => 'Closed Tickets'
                        ];
                        echo $category_titles[$status] ?? 'Support Tickets';
                        ?>
                    </h2>
                </div>
                
                <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <h3>No Tickets Found</h3>
                        <p>
                            <?php if ($search || $status !== 'all' || $priority !== 'all'): ?>
                                No tickets match your current filters.
                            <?php else: ?>
                                You haven't created any support tickets yet.
                            <?php endif; ?>
                        </p>
                        <a href="../support/create_ticket.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Your First Ticket
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): 
                        // Define status display
                        $status_display = [
                            'open' => 'Open',
                            'in_progress' => 'In Progress',
                            'resolved' => 'Resolved',
                            'closed' => 'Closed'
                        ];
                    ?>
                        <div class="ticket-item">
                            <div class="ticket-subject">
                                <a href="view_ticket.php?id=<?php echo $ticket['ticket_id']; ?>">
                                    #<?php echo $ticket['ticket_id']; ?>: <?php echo htmlspecialchars($ticket['subject']); ?>
                                </a>
                                <?php if ($ticket['unread_count'] > 0): ?>
                                    <span class="new-messages"><?php echo $ticket['unread_count']; ?> new</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ticket-meta">
                                <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                    <?php echo $status_display[$ticket['status']] ?? ucfirst($ticket['status']); ?>
                                </span>
                                <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                                    <i class="fas fa-flag"></i> <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                                <span style="color: #666; font-size: 0.9em;">
                                    <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                </span>
                            </div>
                            
                            <div style="color: #666; margin-bottom: 15px; line-height: 1.5;">
                                <?php echo strlen($ticket['description']) > 150 ? substr($ticket['description'], 0, 150) . '...' : $ticket['description']; ?>
                            </div>
                            
                            <div class="ticket-footer">
                                <div style="color: #999; font-size: 0.85em;">
                                    Last updated: <?php echo date('M d, Y', strtotime($ticket['updated_at'])); ?>
                                </div>
                                <div class="ticket-actions">
                                    <a href="view_ticket.php?id=<?php echo $ticket['ticket_id']; ?>" 
                                       class="btn btn-primary btn-small">
                                        <i class="fas fa-eye"></i> View Ticket
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-section">
                    <div class="pagination-info">
                        Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                        (<?php echo $total_tickets; ?> total tickets)
                    </div>
                    
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(['status' => $status, 'priority' => $priority, 'search' => $search, 'page' => 1]); ?>" 
                               class="pagination-btn">
                                <i class="fas fa-angle-double-left"></i>
                                <span>First</span>
                            </a>
                            <a href="?<?php echo http_build_query(['status' => $status, 'priority' => $priority, 'search' => $search, 'page' => $page - 1]); ?>" 
                               class="pagination-btn">
                                <i class="fas fa-angle-left"></i>
                                <span>Previous</span>
                            </a>
                        <?php endif; ?>
                        
                        <div class="page-numbers">
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                                <a href="?<?php echo http_build_query(['status' => $status, 'priority' => $priority, 'search' => $search, 'page' => 1]); ?>">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span style="color: #666; display: flex; align-items: center; padding: 0 10px;">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?<?php echo http_build_query(['status' => $status, 'priority' => $priority, 'search' => $search, 'page' => $i]); ?>" 
                                   class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span style="color: #666; display: flex; align-items: center; padding: 0 10px;">...</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(['status' => $status, 'priority' => $priority, 'search' => $search, 'page' => $total_pages]); ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(['status' => $status, 'priority' => $priority, 'search' => $search, 'page' => $page + 1]); ?>" 
                               class="pagination-btn">
                                <span>Next</span>
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(['status' => $status, 'priority' => $priority, 'search' => $search, 'page' => $total_pages]); ?>" 
                               class="pagination-btn">
                                <span>Last</span>
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
            // Add jQuery animations for better UX
            $(document).ready(function() {
                // Animate stat cards on load
                $('.stat-card').each(function(i) {
                    $(this).delay(i * 100).animate({
                        opacity: 1,
                        top: 0
                    }, 500);
                });
                
                // Smooth scroll for filter tabs
                $('.filter-tab').on('click', function(e) {
                    e.preventDefault();
                    const href = $(this).attr('href');
                    $('html, body').animate({
                        scrollTop: $('.ticket-list').offset().top - 100
                    }, 300);
                    setTimeout(() => {
                        window.location.href = href;
                    }, 350);
                });
                
                // Highlight active filter inputs
                $('.filter-group input, .filter-group select').on('focus', function() {
                    $(this).parent().addClass('focused');
                }).on('blur', function() {
                    $(this).parent().removeClass('focused');
                });
                
                // Add loading animation to filter form submit
                $('.filter-form').on('submit', function() {
                    $('.filter-actions button').html('<i class="fas fa-spinner fa-spin"></i> Applying Filters...');
                });
            });
        </script>
    </body>
    </html>
    <?php
    exit(); // Stop here, don't show single ticket
}

// MARK NOTIFICATIONS AS READ FOR THIS TICKET
if (isset($_SESSION['user_id'])) {
    $markReadStmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? 
        AND type = 'support_reply' 
        AND related_id = ?
        AND is_read = 0
    ");
    $markReadStmt->execute([$_SESSION['user_id'], $ticket_id]);
}

// Get ticket info
$stmt = $db->prepare("
    SELECT t.*, u.username, u.email, u.profile_photo,
           o.order_id as order_number
    FROM support_tickets t
    JOIN users u ON t.user_id = u.user_id
    LEFT JOIN orders o ON t.order_id = o.order_id
    WHERE t.ticket_id = ? AND t.user_id = ?
");
$stmt->execute([$ticket_id, $_SESSION['user_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: dashboard.php');
    exit();
}

// Get ticket messages
$stmt = $db->prepare("
    SELECT m.*, u.username, u.profile_photo, u.role
    FROM ticket_messages m
    JOIN users u ON m.user_id = u.user_id
    WHERE m.ticket_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$ticket_id]);
$messages = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ticket_id, $_SESSION['user_id'], $message]);
        
        // Update ticket timestamp
        $stmt = $db->prepare("UPDATE support_tickets SET updated_at = NOW() WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        
        header("Location: view_ticket.php?id=$ticket_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticket_id; ?> - Support</title>
    <!-- jQuery as per spec -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Single ticket view styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .ticket-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Ticket header */
        .ticket-header {
            background: linear-gradient(135deg, #5a4bff 0%, #3a2ee0 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(90, 75, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .ticket-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .ticket-header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .ticket-header h2 {
            font-size: 1.3em;
            opacity: 0.9;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            font-weight: 400;
        }
        
        .ticket-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            position: relative;
            z-index: 1;
        }
        
        .meta-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Ticket body */
        .ticket-body {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .ticket-section {
            padding: 30px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .ticket-section:last-child {
            border-bottom: none;
        }
        
        .ticket-section h3 {
            color: #5a4bff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3em;
        }
        
        .ticket-description {
            color: #333;
            line-height: 1.7;
            font-size: 1.05em;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #5a4bff;
        }
        
        /* Messages container */
        .messages-container {
            max-height: 500px;
            overflow-y: auto;
            padding: 20px;
            background: #fafafa;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        
        .message {
            margin-bottom: 25px;
            padding: 20px;
            border-radius: 12px;
            position: relative;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.customer {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            margin-right: auto;
            max-width: 85%;
        }
        
        .message.support {
            background: linear-gradient(135deg, #f1f8e9 0%, #dcedc8 100%);
            margin-left: auto;
            max-width: 85%;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5a4bff 0%, #3a2ee0 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .message-info {
            flex: 1;
        }
        
        .username {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .timestamp {
            color: #666;
            font-size: 0.85em;
        }
        
        .message-content {
            color: #333;
            line-height: 1.6;
        }
        
        /* Reply form */
        .reply-form {
            background: white;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            padding: 30px;
            margin-top: 30px;
        }
        
        .reply-form h3 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reply-form textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            resize: vertical;
            margin-bottom: 20px;
            font-family: inherit;
            transition: all 0.3s ease;
            color: #333;
        }
        
        .reply-form textarea:focus {
            outline: none;
            border-color: #5a4bff;
            box-shadow: 0 0 0 3px rgba(90, 75, 255, 0.1);
        }
        
        .reply-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: #5a4bff;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1em;
        }
        
        .btn:hover {
            background: #4a3bdf;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90, 75, 255, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Status badges */
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            color: white;
        }
        
        .status-open { background: #28a745; }
        .status-in_progress { background: #007bff; }
        .status-resolved { background: #6c757d; }
        .status-closed { background: #343a40; }
        
        .priority-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            color: white;
        }
        
        .priority-low { background: #6c757d; }
        .priority-medium { background: #ffc107; color: #333; }
        .priority-high { background: #fd7e14; }
        .priority-urgent { background: #dc3545; }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        /* Closed ticket state */
        .ticket-closed {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            margin-top: 30px;
            border: 2px dashed #dee2e6;
        }
        
        .ticket-closed i {
            font-size: 3em;
            color: #6c757d;
            margin-bottom: 20px;
            display: block;
        }
        
        .ticket-closed h3 {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .ticket-closed p {
            color: #666;
            margin-bottom: 20px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .ticket-container {
                padding: 15px;
            }
            
            .ticket-header {
                padding: 20px;
                text-align: center;
            }
            
            .ticket-header h1 {
                font-size: 1.8em;
            }
            
            .ticket-meta {
                justify-content: center;
            }
            
            .ticket-section {
                padding: 20px;
            }
            
            .message {
                max-width: 95% !important;
            }
            
            .reply-actions {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .ticket-meta {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .meta-item {
                width: 100%;
                justify-content: center;
            }
            
            .message-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1em;
            }
        }
        
        /* Scrollbar styling */
        .messages-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .messages-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .messages-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="ticket-container">
        <!-- Ticket Header -->
        <div class="ticket-header">
            <h1><i class="fas fa-ticket-alt"></i> Ticket #<?php echo $ticket_id; ?></h1>
            <h2><?php echo htmlspecialchars($ticket['subject']); ?></h2>
            
            <div class="ticket-meta">
                <div class="meta-item">
                    Status: <span class="status-badge status-<?php echo $ticket['status']; ?>">
                        <?php echo ucfirst($ticket['status']); ?>
                    </span>
                </div>
                <div class="meta-item">
                    Priority: <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                        <?php echo ucfirst($ticket['priority']); ?>
                    </span>
                </div>
                <?php if ($ticket['order_number']): ?>
                    <div class="meta-item">
                        <i class="fas fa-box"></i> Order #<?php echo $ticket['order_number']; ?>
                    </div>
                <?php endif; ?>
                <div class="meta-item">
                    <i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                </div>
            </div>
        </div>
        
        <div class="ticket-body">
            <!-- Ticket Description -->
            <div class="ticket-section">
                <h3><i class="fas fa-file-alt"></i> Original Request</h3>
                <div class="ticket-description">
                    <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                </div>
            </div>
            
            <!-- Conversation -->
            <div class="ticket-section">
                <h3><i class="fas fa-comments"></i> Conversation</h3>
                <div class="messages-container">
                    <?php if (empty($messages)): ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-comment-slash" style="font-size: 3em; margin-bottom: 20px; display: block; color: #dee2e6;"></i>
                            <h3 style="color: #666; margin-bottom: 10px;">No messages yet</h3>
                            <p>Start the conversation by sending a message below.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['role'] === 'member' ? 'customer' : 'support'; ?>">
                                <div class="message-header">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($message['username'], 0, 1)); ?>
                                    </div>
                                    <div class="message-info">
                                        <div class="username">
                                            <?php echo htmlspecialchars($message['username']); ?>
                                            <?php if ($message['role'] === 'support' || $message['role'] === 'admin'): ?>
                                                <span style="color: #5a4bff; font-size: 0.9em;">(Support Team)</span>
                                            <?php else: ?>
                                                <span style="color: #666; font-size: 0.9em;">(You)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timestamp">
                                            <?php echo date('M d, Y g:i A', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reply Form or Closed State -->
            <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved'): ?>
                <div class="reply-form">
                    <h3><i class="fas fa-reply"></i> Send Reply</h3>
                    <form method="POST">
                        <textarea name="message" placeholder="Type your reply here..." required></textarea>
                        <div class="reply-actions">
                            <button type="submit" class="btn">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                            <small style="color: #666;">
                                <i class="fas fa-info-circle"></i> Our support team will respond within 24 hours
                            </small>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="ticket-closed">
                    <i class="fas fa-lock"></i>
                    <h3>Ticket <?php echo ucfirst($ticket['status']); ?></h3>
                    <p>This ticket has been <?php echo $ticket['status']; ?>. You can no longer reply to it.</p>
                    <a href="../support/create_ticket.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create New Ticket
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Navigation -->
            <div class="action-buttons">
                <a href="view_ticket.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Tickets
                </a>
                <a href="../memberDashboard.php" class="btn">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved'): ?>
                    <a href="../support/create_ticket.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Ticket
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // jQuery for enhanced UX
        $(document).ready(function() {
            // Auto-scroll to bottom of messages container
            const messagesContainer = $('.messages-container');
            if (messagesContainer.length) {
                messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
            }
            
            // Auto-resize textarea
            $('textarea').on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Form submission loading state
            $('form').on('submit', function() {
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Sending...');
                submitBtn.prop('disabled', true);
                
                // Re-enable button after 5 seconds (safety)
                setTimeout(() => {
                    submitBtn.html(originalText);
                    submitBtn.prop('disabled', false);
                }, 5000);
            });
            
            // Smooth scroll for anchor links
            $('a[href^="#"]').on('click', function(e) {
                e.preventDefault();
                const target = $(this.getAttribute('href'));
                if (target.length) {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 500);
                }
            });
        });
    </script>
</body>
</html>
