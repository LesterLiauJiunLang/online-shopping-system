<?php
// tickets.php - View tickets (for both admin and support)
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../app/config/database.php';
include_once __DIR__ . '/../../includes/header.php';
$db = db();

// Check if user is logged in and has proper role
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Check if user is either support or admin
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['support', 'admin'])) {
    header('Location: ../../dashboard.php');
    exit();
}

// Get user ID and role
$user_id = $_SESSION['user_id'];
$is_admin = ($user_role === 'admin');

// Get filter parameters
$status = $_GET['status'] ?? 'active';
$search = $_GET['search'] ?? '';
$agent_id = isset($_GET['agent']) ? (int)$_GET['agent'] : null;

// Build base query
$query = "SELECT DISTINCT t.*, 
                 u.username as customer_name, 
                 u.email as customer_email,
                 o.order_id as order_number,
                 a.username as assigned_agent_name,
                 a.user_id as assigned_agent_id
          FROM support_tickets t 
          JOIN users u ON t.user_id = u.user_id 
          LEFT JOIN orders o ON t.order_id = o.order_id 
          LEFT JOIN users a ON t.assigned_to = a.user_id 
          WHERE 1=1";

// Parameters array
$params = [];

// If admin is viewing specific agent's tickets
if ($is_admin && $agent_id) {
    $query .= " AND t.assigned_to = ?";
    $params[] = $agent_id;
} 
// If support agent (only see their own tickets)
elseif (!$is_admin) {
    $query .= " AND (t.assigned_to = ? OR t.assigned_to IS NULL OR t.assigned_to = 0)";
    $params[] = $user_id;
}

// Status filtering logic
if ($status === 'active') {
    $query .= " AND t.status IN ('open', 'in_progress')";
} elseif ($status !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $status;
}

// Search filtering
if (!empty($search)) {
    $query .= " AND (t.subject LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR t.ticket_id = ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    
    // Check if search is a numeric ticket ID
    if (is_numeric($search)) {
        $params[] = $search;
    } else {
        $params[] = 0; // Not a valid ticket ID
    }
}

// Order by priority and date
$query .= " ORDER BY 
    CASE t.priority 
        WHEN 'urgent' THEN 1
        WHEN 'high' THEN 2
        WHEN 'medium' THEN 3
        WHEN 'low' THEN 4
    END, t.created_at DESC";

// Prepare and execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Get ticket statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM support_tickets WHERE 1=1";

$statsParams = [];
if ($is_admin && $agent_id) {
    $statsQuery .= " AND assigned_to = ?";
    $statsParams[] = $agent_id;
} elseif (!$is_admin) {
    $statsQuery .= " AND (assigned_to = ? OR assigned_to IS NULL OR assigned_to = 0)";
    $statsParams[] = $user_id;
}

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch();

// Get agent info if viewing specific agent
$agent_info = null;
if ($is_admin && $agent_id) {
    $agentStmt = $db->prepare("SELECT username, email, employee_id FROM users WHERE user_id = ?");
    $agentStmt->execute([$agent_id]);
    $agent_info = $agentStmt->fetch();
}

// Get all support agents for reassignment dropdown (admin only)
$support_agents = [];
if ($is_admin) {
    $agentsStmt = $db->prepare("SELECT user_id, username, email, employee_id FROM users WHERE role = 'support' AND is_active = 1 ORDER BY username");
    $agentsStmt->execute();
    $support_agents = $agentsStmt->fetchAll();
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Set base URL for notification scripts
$base_url = '/online-shopping-system';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $is_admin && $agent_id ? htmlspecialchars($agent_info['username'] ?? 'Agent') . "'s Tickets" : 'Support Tickets'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .ticket-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        /* Agent Info Box (only for admin viewing specific agent) */
        .agent-info-box {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .agent-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .agent-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 24px;
        }
        
        .agent-text h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        
        .agent-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .stat-total { background: #4299e1; }
        .stat-active { background: #ed8936; }
        .stat-open { background: #e53e3e; }
        .stat-progress { background: #3182ce; }
        .stat-resolved { background: #38a169; }
        .stat-closed { background: #718096; }
        
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
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filter-header h3 {
            margin: 0;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            padding: 12px 24px;
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid transparent;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-tab:hover {
            background: #edf2f7;
            color: #2d3748;
            text-decoration: none;
        }
        
        .filter-tab.active {
            background: #4299e1;
            color: white;
            border-color: #4299e1;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-form input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .search-form input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .search-form button {
            padding: 12px 24px;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .search-form button:hover {
            background: #3182ce;
        }
        
        .clear-search {
            padding: 12px 20px;
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .clear-search:hover {
            background: #edf2f7;
            color: #2d3748;
            text-decoration: none;
        }
        
        /* Tickets Grid */
        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1000px) {
            .tickets-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .ticket-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 5px solid #4299e1;
            position: relative;
            overflow: hidden;
        }
        
        .ticket-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .ticket-card.urgent { border-left-color: #e53e3e; }
        .ticket-card.high { border-left-color: #ed8936; }
        .ticket-card.medium { border-left-color: #ecc94b; }
        .ticket-card.low { border-left-color: #a0aec0; }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .ticket-id {
            font-size: 14px;
            color: #718096;
            margin-bottom: 5px;
        }
        
        .ticket-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ticket-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-priority { color: white; }
        .badge-urgent { background: #e53e3e; }
        .badge-high { background: #ed8936; }
        .badge-medium { background: #ecc94b; color: #744210; }
        .badge-low { background: #a0aec0; }
        
        .badge-status { background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0; }
        .badge-open { background: #fed7d7; color: #742a2a; }
        .badge-progress { background: #bee3f8; color: #2c5282; }
        .badge-resolved { background: #c6f6d5; color: #22543d; }
        .badge-closed { background: #e2e8f0; color: #4a5568; }
        
        .ticket-content {
            margin-bottom: 20px;
        }
        
        .ticket-description {
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .ticket-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 14px;
            color: #718096;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .ticket-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .ticket-customer {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .customer-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #4299e1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .customer-info h4 {
            margin: 0;
            font-size: 14px;
            color: #2d3748;
        }
        
        .customer-info p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #718096;
        }
        
        .ticket-actions {
            display: flex;
            gap: 10px;
            position: relative;
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
            cursor: pointer;
            border: none;
        }
        
        .action-btn.view {
            background: #e6fffa;
            color: #285e61;
            border: 1px solid #b2f5ea;
            text-decoration: none;
        }
        
        .action-btn.view:hover {
            background: #b2f5ea;
            text-decoration: none;
        }
        
        .action-btn.assign {
            background: #ebf8ff;
            color: #2c5282;
            border: 1px solid #bee3f8;
        }
        
        .action-btn.assign:hover {
            background: #bee3f8;
        }
        
        /* Assign Modal */
        .assign-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .assign-modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            background: #4299e1;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s;
        }
        
        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .current-assignment {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #4299e1;
        }
        
        .current-assignment h4 {
            margin: 0 0 10px 0;
            color: #2d3748;
            font-size: 16px;
        }
        
        .current-assignment p {
            margin: 0;
            color: #718096;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .agent-search {
            margin-bottom: 20px;
        }
        
        .agent-search input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .agent-search input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .agents-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .agent-item {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .agent-item:hover {
            background: #f7fafc;
        }
        
        .agent-item.selected {
            background: #ebf8ff;
            border-left: 4px solid #4299e1;
        }
        
        .agent-item:last-child {
            border-bottom: none;
        }
        
        .agent-item-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4299e1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .agent-item-info {
            flex: 1;
            min-width: 0;
        }
        
        .agent-item-info h5 {
            margin: 0 0 5px 0;
            color: #2d3748;
            font-size: 16px;
        }
        
        .agent-item-info p {
            margin: 0;
            color: #718096;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .agent-item-check {
            color: #38a169;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .pagination {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
        }

        .pagination-top {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        .pagination-bottom {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            width: 100%;
        }

        .page-btn {
            padding: 8px 16px;
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .page-btn:hover:not(:disabled) {
            background: #edf2f7;
            color: #2d3748;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-numbers {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            justify-content: center;
            padding: 10px 0;
        }

        .page-number {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .page-number:hover {
            background: #edf2f7;
            color: #2d3748;
        }

        .page-number.active {
            background: #4299e1;
            color: white;
            border-color: #4299e1;
        }

        .page-info {
            color: #718096;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
        }
        
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: #f7fafc;
            border-top: 1px solid #e2e8f0;
        }
        
        .unassign-btn {
            padding: 10px 20px;
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #feb2b2;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .unassign-btn:hover {
            background: #feb2b2;
        }
        
        .assign-confirm-btn {
            padding: 10px 24px;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .assign-confirm-btn:hover {
            background: #3182ce;
        }
        
        .assign-confirm-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .no-agents {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }
        
        .no-tickets {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .no-tickets-icon {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .no-tickets h3 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .no-tickets p {
            color: #718096;
            margin-bottom: 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: #edf2f7;
            color: #2d3748;
            text-decoration: none;
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4299e1;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Notification toast */
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            animation: slideInRight 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .notification-toast .toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }
        
        .notification-toast .fa-bell {
            font-size: 1.2em;
            color: #FFD700;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Pulse animation for notification bell */
        .pulse {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-ticket-alt"></i>
                <?php if ($is_admin && $agent_id): ?>
                    <?php echo htmlspecialchars($agent_info['username'] ?? 'Agent'); ?>'s Assigned Tickets
                <?php else: ?>
                    Support Tickets
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <?php if ($is_admin && $agent_id): ?>
                    Viewing tickets assigned to <?php echo htmlspecialchars($agent_info['username'] ?? 'this agent'); ?>
                    <?php if ($agent_info['employee_id']): ?>
                        (ID: <?php echo htmlspecialchars($agent_info['employee_id']); ?>)
                    <?php endif; ?>
                <?php elseif ($is_admin): ?>
                    Manage all support tickets in the system
                <?php else: ?>
                    Manage your assigned support tickets
                <?php endif; ?>
            </p>
            
            <?php if ($is_admin && $agent_id): ?>
                <div class="agent-info-box">
                    <div class="agent-details">
                        <div class="agent-avatar">
                            <i class="fas fa-user-headset"></i>
                        </div>
                        <div class="agent-text">
                            <h3><?php echo htmlspecialchars($agent_info['username'] ?? 'Unknown Agent'); ?></h3>
                            <p><?php echo htmlspecialchars($agent_info['email'] ?? 'No email'); ?></p>
                        </div>
                    </div>
                    <a href="../admin/support_details.php?id=<?php echo $agent_id; ?>" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Agent Details
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-total">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Tickets</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-active">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['active'] ?? 0; ?></h3>
                    <p>Active Tickets</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-open">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['open'] ?? 0; ?></h3>
                    <p>Open Tickets</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-resolved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['resolved'] ?? 0; ?></h3>
                    <p>Resolved Tickets</p>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-header">
                <h3><i class="fas fa-filter"></i> Filter Tickets</h3>
            </div>
            
            <!-- Status Tabs -->
            <div class="filter-tabs">
                <a href="?<?php echo ($agent_id ? "agent=$agent_id&" : '') . 'status=all'; ?>" 
                   class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> All
                </a>
                <a href="?<?php echo ($agent_id ? "agent=$agent_id&" : '') . 'status=active'; ?>" 
                   class="filter-tab <?php echo $status === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Active
                </a>
                <a href="?<?php echo ($agent_id ? "agent=$agent_id&" : '') . 'status=open'; ?>" 
                   class="filter-tab <?php echo $status === 'open' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-circle"></i> Open
                </a>
                <a href="?<?php echo ($agent_id ? "agent=$agent_id&" : '') . 'status=in_progress'; ?>" 
                   class="filter-tab <?php echo $status === 'in_progress' ? 'active' : ''; ?>">
                    <i class="fas fa-spinner"></i> In Progress
                </a>
                <a href="?<?php echo ($agent_id ? "agent=$agent_id&" : '') . 'status=resolved'; ?>" 
                   class="filter-tab <?php echo $status === 'resolved' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Resolved
                </a>
                <a href="?<?php echo ($agent_id ? "agent=$agent_id&" : '') . 'status=closed'; ?>" 
                   class="filter-tab <?php echo $status === 'closed' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Closed
                </a>
            </div>
            
            <!-- Search Form -->
            <form method="GET" class="search-form">
                <?php if ($agent_id): ?>
                    <input type="hidden" name="agent" value="<?php echo $agent_id; ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search by ticket ID, subject, customer name, or email..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search) || $status !== 'active'): ?>
                    <a href="?<?php echo $agent_id ? "agent=$agent_id" : ''; ?>" class="clear-search">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tickets Grid -->
        <?php if (empty($tickets)): ?>
            <div class="no-tickets">
                <div class="no-tickets-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <h3>No Tickets Found</h3>
                <p>
                    <?php if (!empty($search)): ?>
                        No tickets match your search criteria.
                    <?php else: ?>
                        No tickets found with the current filters.
                    <?php endif; ?>
                </p>
                <a href="?<?php echo $agent_id ? "agent=$agent_id" : ''; ?>" class="btn" style="background: #4299e1; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none;">
                    <i class="fas fa-times"></i> Clear All Filters
                </a>
            </div>
        <?php else: ?>
            <div class="tickets-grid">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card <?php echo $ticket['priority']; ?>">
                        <div class="ticket-header">
                            <div>
                                <div class="ticket-id">TICKET #<?php echo $ticket['ticket_id']; ?></div>
                                <h3 class="ticket-title">
                                    <?php echo htmlspecialchars($ticket['subject']); ?>
                                </h3>
                            </div>
                            <div class="ticket-badges">
                                <span class="badge badge-priority badge-<?php echo $ticket['priority']; ?>">
                                    <i class="fas fa-<?php echo $ticket['priority'] === 'urgent' ? 'fire' : 'flag'; ?>"></i>
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                                <span class="badge badge-status badge-<?php echo $ticket['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="ticket-content">
                            <p class="ticket-description">
                                <?php echo htmlspecialchars(substr($ticket['description'], 0, 200)); ?>
                                <?php if (strlen($ticket['description']) > 200): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="ticket-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Created: <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-history"></i>
                                    <span>Updated: <?php echo date('M d, Y', strtotime($ticket['updated_at'])); ?></span>
                                </div>
                                <?php if ($ticket['order_number']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span>Order: #<?php echo $ticket['order_number']; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="ticket-footer">
                            <div class="ticket-customer">
                                <div class="customer-avatar">
                                    <?php echo strtoupper(substr($ticket['customer_name'], 0, 1)); ?>
                                </div>
                                <div class="customer-info">
                                    <h4><?php echo htmlspecialchars($ticket['customer_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($ticket['customer_email']); ?></p>
                                </div>
                            </div>
                            
                            <div class="ticket-actions">
                                <?php if ($is_admin): ?>
                                    <button type="button" class="action-btn assign" 
                                            onclick="openAssignModal(<?php echo $ticket['ticket_id']; ?>, <?php echo $ticket['assigned_agent_id'] ?? 0; ?>, '<?php echo $ticket['assigned_agent_name'] ?? ''; ?>')">
                                        <i class="fas fa-user-tag"></i>
                                        <?php echo $ticket['assigned_agent_name'] ? 'Reassign' : 'Assign'; ?>
                                    </button>
                                <?php endif; ?>
                                
                                <a href="ticket_view.php?id=<?php echo $ticket['ticket_id']; ?>" class="action-btn view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($is_admin && !$agent_id): ?>
            <div style="text-align: center; margin-top: 30px;">
                <a href="../admin/manage_support.php" class="back-link">
                    <i class="fas fa-users"></i> Manage Support Agents
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Assign Modal -->
    <div class="assign-modal" id="assignModal">
        <div class="assign-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-tag"></i> Assign Ticket</h3>
                <button type="button" class="close-modal" onclick="closeAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="current-assignment">
                    <h4>Current Assignment</h4>
                    <p id="currentAgentInfo">
                        <i class="fas fa-user"></i>
                        <span id="currentAgentName">No agent assigned</span>
                    </p>
                </div>
                
                <div class="agent-search">
                    <input type="text" id="agentSearch" placeholder="Search agents by name, email, or ID..." 
                           onkeyup="searchAgents()">
                </div>
                
                <div class="agents-list" id="agentsList">
                    <!-- Agents will be loaded here -->
                </div>
                
                <div class="pagination" id="pagination">
                    <!-- Pagination will be generated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="unassign-btn" onclick="unassignTicket()">
                    <i class="fas fa-user-slash"></i> Unassign
                </button>
                <button type="button" class="assign-confirm-btn" id="assignConfirmBtn" onclick="assignSelectedAgent()" disabled>
                    <i class="fas fa-check"></i> Assign Selected
                </button>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Global variables
    let currentTicketId = 0;
    let currentAssignedAgentId = 0;
    let currentSelectedAgentId = 0;
    let allAgents = <?php echo json_encode($support_agents); ?>;
    let filteredAgents = [];
    let currentPage = 1;
    const agentsPerPage = 5;
    
    $(document).ready(function() {
        console.log('Tickets page loaded');
        
        // Handle filter tab clicks
        $('.filter-tab').click(function() {
            $('.filter-tab').removeClass('active');
            $(this).addClass('active');
        });
        
        // Handle search form submission
        $('.search-form').submit(function() {
            $('.search-form button').html('<i class="fas fa-spinner fa-spin"></i> Searching...');
        });
        
        // Close modal when clicking outside
        $(document).click(function(event) {
            if ($(event.target).hasClass('assign-modal')) {
                closeAssignModal();
            }
        });
        
        // Close modal with Escape key
        $(document).keyup(function(e) {
            if (e.key === "Escape") {
                closeAssignModal();
            }
        });
        
        // Initialize notification polling
        initNotificationPolling();
    });
    
    // Open assign modal
    function openAssignModal(ticketId, assignedAgentId, assignedAgentName) {
        currentTicketId = ticketId;
        currentAssignedAgentId = assignedAgentId;
        currentSelectedAgentId = assignedAgentId;
        
        // Update current assignment info
        if (assignedAgentId && assignedAgentName) {
            $('#currentAgentName').text(assignedAgentName + ' (ID: ' + assignedAgentId + ')');
        } else {
            $('#currentAgentName').text('No agent assigned');
        }
        
        // Reset search
        $('#agentSearch').val('');
        
        // Load agents
        filteredAgents = [...allAgents];
        renderAgents();
        
        // Show modal
        $('#assignModal').fadeIn();
    }
    
    // Close assign modal
    function closeAssignModal() {
        $('#assignModal').fadeOut();
        currentSelectedAgentId = currentAssignedAgentId;
    }
    
    // Search agents
    function searchAgents() {
        const searchTerm = $('#agentSearch').val().toLowerCase();
        
        if (!searchTerm) {
            filteredAgents = [...allAgents];
        } else {
            filteredAgents = allAgents.filter(agent => {
                return agent.username.toLowerCase().includes(searchTerm) ||
                       agent.email.toLowerCase().includes(searchTerm) ||
                       agent.employee_id?.toLowerCase().includes(searchTerm) ||
                       agent.user_id.toString().includes(searchTerm);
            });
        }
        
        currentPage = 1;
        renderAgents();
    }
    
    // Render agents list with pagination - UPDATED VERSION
    function renderAgents() {
        const agentsList = $('#agentsList');
        const pagination = $('#pagination');
        
        // Clear current content
        agentsList.empty();
        pagination.empty();
        
        if (filteredAgents.length === 0) {
            agentsList.html('<div class="no-agents"><p>No agents found</p></div>');
            return;
        }
        
        // Calculate pagination
        const totalPages = Math.ceil(filteredAgents.length / agentsPerPage);
        const startIndex = (currentPage - 1) * agentsPerPage;
        const endIndex = startIndex + agentsPerPage;
        const pageAgents = filteredAgents.slice(startIndex, endIndex);
        const totalAgents = filteredAgents.length;
        
        // Render agents
        pageAgents.forEach(agent => {
            const isSelected = agent.user_id === currentSelectedAgentId;
            const isCurrent = agent.user_id === currentAssignedAgentId;
            
            const agentItem = $(`
                <div class="agent-item ${isSelected ? 'selected' : ''}" onclick="selectAgent(${agent.user_id})">
                    <div class="agent-item-avatar">
                        ${agent.username.charAt(0).toUpperCase()}
                    </div>
                    <div class="agent-item-info">
                        <h5>${escapeHtml(agent.username)} ${isCurrent ? '(Currently Assigned)' : ''}</h5>
                        <p>
                            <i class="fas fa-envelope"></i> ${escapeHtml(agent.email)}
                            ${agent.employee_id ? `<span style="margin-left: 10px;"><i class="fas fa-id-card"></i> ${escapeHtml(agent.employee_id)}</span>` : ''}
                        </p>
                    </div>
                    ${isSelected ? '<div class="agent-item-check"><i class="fas fa-check"></i></div>' : ''}
                </div>
            `);
            
            agentsList.append(agentItem);
        });
        
        // Render pagination - UPDATED STACKED LAYOUT
        if (totalPages > 1) {
            // Create pagination container
            const paginationDiv = $('<div class="pagination"></div>');
            
            // Top row: Previous button + Page numbers
            const topRow = $('<div class="pagination-top"></div>');
            
            // Previous button
            const prevBtn = $(`<button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
                <i class="fas fa-chevron-left"></i> Previous
            </button>`);
            topRow.append(prevBtn);
            
            // Page numbers
            const pageNumbers = $('<div class="page-numbers"></div>');
            
            // Show limited page numbers (max 5)
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            
            // Adjust if we're at the end
            if (endPage - startPage < 4 && startPage > 1) {
                startPage = Math.max(1, endPage - 4);
            }
            
            // First page
            if (startPage > 1) {
                pageNumbers.append($(`<button class="page-number ${1 === currentPage ? 'active' : ''}" onclick="changePage(1)">1</button>`));
                if (startPage > 2) {
                    pageNumbers.append($('<span style="padding: 0 5px; color: #718096;">...</span>'));
                }
            }
            
            // Page numbers
            for (let i = startPage; i <= endPage; i++) {
                pageNumbers.append($(`<button class="page-number ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`));
            }
            
            // Last page
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    pageNumbers.append($('<span style="padding: 0 5px; color: #718096;">...</span>'));
                }
                pageNumbers.append($(`<button class="page-number ${totalPages === currentPage ? 'active' : ''}" onclick="changePage(${totalPages})">${totalPages}</button>`));
            }
            
            topRow.append(pageNumbers);
            
            // Next button
            const nextBtn = $(`<button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
                Next <i class="fas fa-chevron-right"></i>
            </button>`);
            topRow.append(nextBtn);
            
            paginationDiv.append(topRow);
            
            // Bottom row: Page info
            const bottomRow = $('<div class="pagination-bottom"></div>');
            const pageInfo = $(`<div class="page-info">
                Showing ${startIndex + 1} - ${Math.min(endIndex, totalAgents)} of ${totalAgents} agents
                (Page ${currentPage} of ${totalPages})
            </div>`);
            bottomRow.append(pageInfo);
            
            paginationDiv.append(bottomRow);
            
            pagination.append(paginationDiv);
        } else {
            // Show simple info if only one page
            pagination.append($(`<div class="page-info">Showing all ${totalAgents} agents</div>`));
        }
        
        // Update assign button state
        $('#assignConfirmBtn').prop('disabled', currentSelectedAgentId === currentAssignedAgentId);
    }
    
    // Change page
    function changePage(page) {
        currentPage = page;
        renderAgents();
        $('#agentsList').scrollTop(0);
        
        // Also scroll the modal body to top
        $('.modal-body').scrollTop(0);
    }
    
    // Select agent
    function selectAgent(agentId) {
        currentSelectedAgentId = agentId;
        renderAgents();
        $('#assignConfirmBtn').prop('disabled', currentSelectedAgentId === currentAssignedAgentId);
    }
    
    // Unassign ticket
    function unassignTicket() {
        if (confirm('Are you sure you want to unassign this ticket?')) {
            assignTicket(0);
        }
    }
    
    // Assign selected agent
    function assignSelectedAgent() {
        if (currentSelectedAgentId !== currentAssignedAgentId) {
            const agent = allAgents.find(a => a.user_id === currentSelectedAgentId);
            const agentName = agent ? agent.username : 'Unassigned';
            
            if (confirm(`Assign ticket to ${agentName}?`)) {
                assignTicket(currentSelectedAgentId);
            }
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // === NOTIFICATION FUNCTIONS ===
    
    // Function to update notification badge in header
    function updateNotificationBadge() {
        $.ajax({
            url: '<?php echo $base_url; ?>/public/get_notification_count.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update the notification badge in the header
                    let notificationCount = $('.notification-count');
                    let notificationBell = $('.notification-bell');
                    
                    if (response.count > 0) {
                        if (notificationCount.length) {
                            notificationCount.text(response.count);
                        } else {
                            // Create badge if it doesn't exist
                            notificationBell.after('<span class="notification-count">' + response.count + '</span>');
                        }
                        
                        // Add pulse animation for new notifications
                        if (response.new_notifications) {
                            notificationBell.addClass('pulse');
                            setTimeout(() => {
                                notificationBell.removeClass('pulse');
                            }, 3000);
                        }
                    } else {
                        // Remove badge if no notifications
                        notificationCount.remove();
                    }
                }
            },
            error: function() {
                console.log('Failed to update notification count');
            }
        });
    }
    
    // Function to show notification toast
    function showNotificationToast(message) {
        // Remove existing toast if any
        $('.notification-toast').remove();
        
        // Create toast
        const toast = $(`
            <div class="notification-toast">
                <div class="toast-content">
                    <i class="fas fa-bell"></i>
                    <span>${message}</span>
                </div>
            </div>
        `);
        
        // Add to page
        $('body').append(toast);
        
        // Remove after 5 seconds
        setTimeout(() => {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Function to play notification sound
    function playNotificationSound() {
        // Create audio element for notification sound
        const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3');
        audio.volume = 0.3;
        audio.play().catch(e => console.log('Audio play failed:', e));
    }
    
    // Updated assign ticket function with notifications
    function assignTicket(agentId) {
        // Show loading
        const btn = $('#assignConfirmBtn');
        const originalHtml = btn.html();
        btn.html('<span class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></span> Assigning...');
        btn.prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: 'assign_ticket.php',
            method: 'POST',
            data: {
                ticket_id: currentTicketId,
                agent_id: agentId,
                csrf_token: '<?php echo $csrf_token; ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message with notification info
                    const action = agentId > 0 ? 'assigned' : 'unassigned';
                    const message = `Ticket ${action} successfully! ${response.notification_created ? 'Notification sent.' : ''}`;
                    
                    // Show toast notification
                    showNotificationToast(message);
                    
                    // Play notification sound
                    playNotificationSound();
                    
                    // Update notification badge
                    setTimeout(updateNotificationBadge, 1000);
                    
                    // Close modal and reload page after delay
                    setTimeout(() => {
                        closeAssignModal();
                        location.reload();
                    }, 1500);
                } else {
                    alert('Error: ' + response.message);
                    btn.html(originalHtml);
                    btn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error: Unable to assign ticket. Please try again.');
                btn.html(originalHtml);
                btn.prop('disabled', false);
            }
        });
    }
    
    // Initialize notification polling (optional - for real-time updates)
    function initNotificationPolling() {
        // Update notification count every 30 seconds
        setInterval(updateNotificationBadge, 30000);
        
        // Also update immediately when page loads
        setTimeout(updateNotificationBadge, 2000);
    }
    </script>
</body>
</html>
