<?php
// support/faq_manage.php - FAQ Management for Support Agents
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is support
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'support') {
    header('Location: ../../login.php');
    exit();
}

// Include base files
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../lib/base.php';

$db = db();
$message = '';
$error = '';
$show_success_modal = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_faq'])) {
        // Add new FAQ
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $category = trim($_POST['category']);
        
        // If new category is provided, use it
        if (empty($category) && !empty(trim($_POST['new_category']))) {
            $category = trim($_POST['new_category']);
        }
        
        if (empty($question) || empty($answer)) {
            $error = "Please fill in both question and answer.";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO faqs (question, answer, category, created_by) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$question, $answer, $category, $_SESSION['user_id']]);
                $message = "FAQ added successfully!";
            } catch (Exception $e) {
                $error = "Failed to add FAQ: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_faq'])) {
        // Update FAQ via modal
        $faq_id = $_POST['faq_id'];
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $category = trim($_POST['category']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($question) || empty($answer)) {
            $error = "Please fill in both question and answer.";
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE faqs 
                    SET question = ?, answer = ?, category = ?, is_active = ?, updated_at = NOW()
                    WHERE faq_id = ?
                ");
                $stmt->execute([$question, $answer, $category, $is_active, $faq_id]);
                $message = "FAQ updated successfully!";
                $show_success_modal = true;
            } catch (Exception $e) {
                $error = "Failed to update FAQ: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_faq'])) {
        // Delete FAQ
        $faq_id = $_POST['faq_id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM faqs WHERE faq_id = ?");
            $stmt->execute([$faq_id]);
            $message = "FAQ deleted successfully!";
        } catch (Exception $e) {
            $error = "Failed to delete FAQ: " . $e->getMessage();
        }
    } elseif (isset($_POST['toggle_faq'])) {
        // Toggle FAQ status
        $faq_id = $_POST['faq_id'];
        $stmt = $db->prepare("SELECT is_active FROM faqs WHERE faq_id = ?");
        $stmt->execute([$faq_id]);
        $faq = $stmt->fetch();
        
        $new_status = $faq['is_active'] ? 0 : 1;
        
        try {
            $stmt = $db->prepare("UPDATE faqs SET is_active = ? WHERE faq_id = ?");
            $stmt->execute([$new_status, $faq_id]);
            $message = "FAQ status updated!";
        } catch (Exception $e) {
            $error = "Failed to update FAQ status: " . $e->getMessage();
        }
    }
}

// ============================================================================
// PAGINATION SETUP
// ============================================================================
$items_per_page = 5; // 5 items per page
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE conditions for FAQs
$where_conditions = [];
$params = [];

// Category filter for Categories tab
$viewing_category = isset($_GET['view_category']) ? $_GET['view_category'] : '';

if ($viewing_category) {
    $where_conditions[] = "f.category = ?";
    $params[] = $viewing_category;
} elseif ($category_filter && $category_filter !== 'all') {
    $where_conditions[] = "f.category = ?";
    $params[] = $category_filter;
}

// Search filter
if ($search_filter) {
    $where_conditions[] = "(f.question LIKE ? OR f.answer LIKE ? OR f.category LIKE ?)";
    $search_param = "%$search_filter%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter
if ($status_filter && $status_filter !== 'all') {
    if ($status_filter === 'active') {
        $where_conditions[] = "f.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "f.is_active = 0";
    }
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM faqs f $where_clause";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Get FAQs with pagination INCLUDING HELPFUL COUNT
$sql = "SELECT f.*, u.username as created_by_name 
        FROM faqs f 
        LEFT JOIN users u ON f.created_by = u.user_id 
        $where_clause 
        ORDER BY f.created_at DESC 
        LIMIT ? OFFSET ?";

$faq_stmt = $db->prepare($sql);
// Bind all parameters for the main query
$param_index = 1;
foreach ($params as $param) {
    $faq_stmt->bindValue($param_index, $param, PDO::PARAM_STR);
    $param_index++;
}
$faq_stmt->bindValue($param_index++, $items_per_page, PDO::PARAM_INT);
$faq_stmt->bindValue($param_index, $offset, PDO::PARAM_INT);
$faq_stmt->execute();
$faqs = $faq_stmt->fetchAll();

// ============================================================================
// CATEGORIES PAGINATION
// ============================================================================
$cat_items_per_page = 5; // 5 categories per page
$cat_current_page = isset($_GET['cat_page']) ? max(1, intval($_GET['cat_page'])) : 1;
$cat_offset = ($cat_current_page - 1) * $cat_items_per_page;

// Get distinct categories with counts
$cat_sql = "SELECT category, 
                   COUNT(*) as faq_count,
                   SUM(is_active = 1) as active_count,
                   SUM(is_active = 0) as inactive_count,
                   SUM(helpful_count) as total_helpful
            FROM faqs 
            WHERE category IS NOT NULL AND category != ''
            GROUP BY category 
            ORDER BY category ASC
            LIMIT ? OFFSET ?";

$cat_stmt = $db->prepare($cat_sql);
$cat_stmt->bindValue(1, $cat_items_per_page, PDO::PARAM_INT);
$cat_stmt->bindValue(2, $cat_offset, PDO::PARAM_INT);
$cat_stmt->execute();
$categories_data = $cat_stmt->fetchAll();

// Get total categories count
$total_categories_stmt = $db->query("
    SELECT COUNT(DISTINCT category) as total 
    FROM faqs 
    WHERE category IS NOT NULL AND category != ''
");
$total_categories = $total_categories_stmt->fetchColumn();
$cat_total_pages = ceil($total_categories / $cat_items_per_page);

// Get all categories for filter dropdown
$all_categories_stmt = $db->query("
    SELECT DISTINCT category 
    FROM faqs 
    WHERE category IS NOT NULL AND category != ''
    ORDER BY category
");
$all_categories = $all_categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get stats
$stats_stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(is_active = 1) as active,
        SUM(is_active = 0) as inactive,
        SUM(helpful_count) as total_helpful
    FROM faqs
");
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage FAQ - Support Dashboard</title>
    <style>
        /* ============================================
           HEADER ISOLATION - Keep original header styles
           ============================================ */
        /* Target only the main header element */
        body > header {
            background: #4a6fa5 !important;
            color: white !important;
            padding: 15px 20px !important;
            margin: 0 !important;
            position: relative !important;
            z-index: 1000 !important;
            border-bottom: 3px solid #3a5988 !important;
        }
        
        /* Reset any inherited FAQ styles in header */
        body > header *:not(.faq-content *) {
            background: transparent !important;
            color: white !important;
            border: none !important;
            box-shadow: none !important;
            text-shadow: none !important;
        }
        
        /* Ensure header links are visible */
        body > header a {
            color: white !important;
            text-decoration: none !important;
        }
        
        /* Make sure header elements don't use FAQ styles */
        body > header .container,
        body > header .tabs,
        body > header .tab,
        body > header .tab-content,
        body > header .header,
        body > header .category-badge,
        body > header .stat-card {
            all: revert !important;
            background: transparent !important;
            color: white !important;
        }
        
        /* ============================================
           FAQ PAGE SPECIFIC STYLES 
           These styles only apply within .faq-content
           ============================================ */
        body {
            background: #f5f7fa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .faq-content {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        /* FAQ Container */
        .faq-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* FAQ Header */
        .faq-header {
            background: linear-gradient(135deg, #4a6fa5, #3a5988);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .faq-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .faq-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .back-btn {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 15px;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 25px;
            background: #f8f9ff;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: bold;
            color: #4a6fa5;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .faq-body {
            padding: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: #666;
            position: relative;
            white-space: nowrap;
        }
        
        .tab.active {
            color: #4a6fa5;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #4a6fa5;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        .form-control:focus {
            border-color: #4a6fa5;
            outline: none;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4a6fa5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a5988;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #444;
        }
        
        tr:hover {
            background: #f8f9ff;
        }
        
        .status-active {
            display: inline-block;
            padding: 5px 10px;
            background: #d4edda;
            color: #155724;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-inactive {
            display: inline-block;
            padding: 5px 10px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .category-badge {
            display: inline-block;
            padding: 5px 12px;
            background: #e9ecef;
            color: #495057;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 2px;
            text-decoration: none;
        }
        
        .category-badge:hover {
            background: #dee2e6;
        }
        
        .category-badge-large {
            display: inline-block;
            padding: 8px 15px;
            background: #4a6fa5;
            color: white;
            border-radius: 20px;
            font-size: 1rem;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .category-badge-large:hover {
            background: #3a5988;
            transform: translateY(-2px);
        }
        
        .category-stats {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .stat-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 15px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .helpful-count {
            display: inline-block;
            padding: 6px 12px;
            background: #d1ecf1;
            color: #0c5460;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .helpful-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #d1ecf1;
            color: #0c5460;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px;
            flex-wrap: wrap;
        }
        
        .page-link {
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #4a6fa5;
            transition: all 0.3s;
        }
        
        .page-link:hover, .page-link.active {
            background: #4a6fa5;
            color: white;
            border-color: #4a6fa5;
        }
        
        .page-disabled {
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            color: #adb5bd;
            cursor: not-allowed;
        }
        
        .page-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-box input, .search-box select {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-tags {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .filter-tag .remove {
            cursor: pointer;
            color: #dc3545;
            font-weight: bold;
        }
        
        /* ============================================
           MODAL STYLES
           ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #4a6fa5, #3a5988);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .modal-close {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .modal-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #28a745;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .toggle-label {
            font-weight: 600;
            color: #444;
        }
        
        @media (max-width: 768px) {
            .faq-container {
                border-radius: 10px;
            }
            
            .faq-header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-container {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 10px 8px;
            }
            
            .actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .tabs {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .faq-content {
                padding: 0 10px;
            }
            
            .modal-content {
                margin: 10px;
                max-height: 85vh;
            }
            
            .modal-header, .modal-body, .modal-footer {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="faq-content">
        <!-- FAQ Container -->
        <div class="faq-container">
            <!-- Header -->
            <div class="faq-header">
                <h1>📚 FAQ Management</h1>
                <p>Manage Frequently Asked Questions for Gadget Tech Store</p>
                <a href="../supportDashboard.php" class="back-btn">← Back to Dashboard</a>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total FAQs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active FAQs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_helpful']; ?></div>
                    <div class="stat-label">Total Helpful Votes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['inactive']; ?></div>
                    <div class="stat-label">Inactive FAQs</div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Main Content -->
            <div class="faq-body">
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('view-faqs')">📋 View FAQs (<?php echo $total_items; ?>)</button>
                    <button class="tab" onclick="switchTab('add-faq')">➕ Add New FAQ</button>
                    <button class="tab" onclick="switchTab('categories')">🏷️ Categories (<?php echo $total_categories; ?>)</button>
                </div>
                
                <!-- View FAQs Tab -->
                <div id="view-faqs" class="tab-content active">
                    <!-- Filters -->
                    <div class="filter-tags">
                        <?php if ($viewing_category): ?>
                            <div class="filter-tag">
                                Category: <?php echo htmlspecialchars($viewing_category); ?>
                                <span class="remove" onclick="removeFilter('view_category')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($category_filter && $category_filter !== 'all'): ?>
                            <div class="filter-tag">
                                Category: <?php echo htmlspecialchars($category_filter); ?>
                                <span class="remove" onclick="removeFilter('category')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($search_filter): ?>
                            <div class="filter-tag">
                                Search: "<?php echo htmlspecialchars($search_filter); ?>"
                                <span class="remove" onclick="removeFilter('search')">×</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($status_filter && $status_filter !== 'all'): ?>
                            <div class="filter-tag">
                                Status: <?php echo htmlspecialchars(ucfirst($status_filter)); ?>
                                <span class="remove" onclick="removeFilter('status')">×</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Search and Filter Box -->
                    <form method="GET" action="" class="search-box">
                        <input type="hidden" name="tab" value="view-faqs">
                        <input type="hidden" name="page" value="1">
                        <input type="text" name="search" class="form-control" placeholder="Search FAQs..." value="<?php echo htmlspecialchars($search_filter); ?>">
                        <select name="category" class="form-control" style="width: 200px;">
                            <option value="all">All Categories</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category_filter == $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="form-control" style="width: 200px;">
                            <option value="all">All Status</option>
                            <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <button type="button" class="btn" onclick="clearFilters()">Clear All</button>
                    </form>
                    
                    <!-- FAQs Table -->
                    <div class="table-container">
                        <?php if (empty($faqs)): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <h3>No FAQs found</h3>
                                <p>Try changing your filters or add new FAQs.</p>
                            </div>
                        <?php else: ?>
                            <table id="faqsTable">
                                <thead>
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th width="30%">Question</th>
                                        <th width="15%">Category</th>
                                        <th width="10%">Helpful</th>
                                        <th width="10%">Status</th>
                                        <th width="20%">Created</th>
                                        <th width="10%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($faqs as $faq): ?>
                                    <tr>
                                        <td><?php echo $faq['faq_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($faq['question']); ?></strong><br>
                                            <small class="text-muted">By: <?php echo htmlspecialchars($faq['created_by_name'] ?? 'System'); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($faq['category'])): ?>
                                                <a href="?tab=view-faqs&view_category=<?php echo urlencode($faq['category']); ?>&page=1" class="category-badge">
                                                    <?php echo htmlspecialchars($faq['category']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No category</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="helpful-count-badge">
                                                👍 <?php echo $faq['helpful_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($faq['is_active']): ?>
                                                <span class="status-active">Active</span>
                                            <?php else: ?>
                                                <span class="status-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($faq['created_at'])); ?><br>
                                            <small><?php echo date('h:i A', strtotime($faq['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button class="btn btn-sm btn-primary" onclick="openEditModal(<?php echo $faq['faq_id']; ?>)">Edit</button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                                    <button type="submit" name="toggle_faq" class="btn btn-sm btn-<?php echo $faq['is_active'] ? 'warning' : 'success'; ?>">
                                                        <?php echo $faq['is_active'] ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                                    <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                                    <button type="submit" name="delete_faq" class="btn btn-sm btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- FAQ Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?<?php 
                                echo build_query(['page' => $current_page - 1, 'view_category' => $viewing_category, 
                                                 'category' => $category_filter, 'search' => $search_filter, 
                                                 'status' => $status_filter]);
                            ?>" class="page-link">← Previous</a>
                        <?php else: ?>
                            <span class="page-disabled">← Previous</span>
                        <?php endif; ?>
                        
                        <span class="page-info">
                            Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                            (Showing <?php echo min($items_per_page, $total_items - $offset); ?> of <?php echo $total_items; ?> FAQs)
                        </span>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?<?php 
                                echo build_query(['page' => $i, 'view_category' => $viewing_category, 
                                                 'category' => $category_filter, 'search' => $search_filter, 
                                                 'status' => $status_filter]);
                            ?>" class="page-link <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?<?php 
                                echo build_query(['page' => $current_page + 1, 'view_category' => $viewing_category, 
                                                 'category' => $category_filter, 'search' => $search_filter, 
                                                 'status' => $status_filter]);
                            ?>" class="page-link">Next →</a>
                        <?php else: ?>
                            <span class="page-disabled">Next →</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Add FAQ Tab -->
                <div id="add-faq" class="tab-content">
                    <form method="POST">
                        <div class="form-group">
                            <label for="question">Question *</label>
                            <input type="text" id="question" name="question" class="form-control" required placeholder="e.g., How long does shipping take?">
                        </div>
                        
                        <div class="form-group">
                            <label for="answer">Answer *</label>
                            <textarea id="answer" name="answer" class="form-control" required placeholder="Provide a clear and helpful answer..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">Select Category</option>
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Or enter a new category</small>
                            <input type="text" id="new_category" name="new_category" class="form-control" style="margin-top: 5px;" placeholder="Enter new category...">
                        </div>
                        
                        <button type="submit" name="add_faq" class="btn btn-primary">➕ Add FAQ</button>
                    </form>
                </div>
                
                <!-- Categories Tab -->
                <div id="categories" class="tab-content">
                    <div class="form-group">
                        <h3>FAQ Categories</h3>
                        <p>Browse and manage FAQ categories. Click any category to view FAQs in that category.</p>
                        
                        <div id="categoriesList" style="margin-top: 20px;">
                            <?php if (empty($categories_data)): ?>
                                <div style="text-align: center; padding: 20px; color: #666;">
                                    <p>No categories found.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($categories_data as $cat_data): ?>
                                    <div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                                        <a href="?tab=view-faqs&view_category=<?php echo urlencode($cat_data['category']); ?>&page=1" 
                                           class="category-badge-large">
                                            <?php echo htmlspecialchars($cat_data['category']); ?>
                                        </a>
                                        
                                        <div class="category-stats">
                                            <span class="stat-badge">📋 <?php echo $cat_data['faq_count']; ?> FAQs</span>
                                            <span class="stat-badge">✅ <?php echo $cat_data['active_count']; ?> Active</span>
                                            <span class="stat-badge">⏸️ <?php echo $cat_data['inactive_count']; ?> Inactive</span>
                                            <span class="stat-badge">👍 <?php echo $cat_data['total_helpful']; ?> Helpful</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Categories Pagination -->
                    <?php if ($cat_total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($cat_current_page > 1): ?>
                            <a href="?tab=categories&cat_page=<?php echo $cat_current_page - 1; ?>" class="page-link">← Previous</a>
                        <?php else: ?>
                            <span class="page-disabled">← Previous</span>
                        <?php endif; ?>
                        
                        <span class="page-info">
                            Page <?php echo $cat_current_page; ?> of <?php echo $cat_total_pages; ?>
                            (Showing <?php echo count($categories_data); ?> of <?php echo $total_categories; ?> categories)
                        </span>
                        
                        <?php for ($i = max(1, $cat_current_page - 2); $i <= min($cat_total_pages, $cat_current_page + 2); $i++): ?>
                            <a href="?tab=categories&cat_page=<?php echo $i; ?>" class="page-link <?php echo ($i == $cat_current_page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($cat_current_page < $cat_total_pages): ?>
                            <a href="?tab=categories&cat_page=<?php echo $cat_current_page + 1; ?>" class="page-link">Next →</a>
                        <?php else: ?>
                            <span class="page-disabled">Next →</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit FAQ Modal -->
    <div id="editFaqModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit FAQ</h2>
                <button type="button" class="modal-close" onclick="closeEditModal()">×</button>
            </div>
            <form id="editFaqForm" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_faq_id" name="faq_id">
                    
                    <div class="status-toggle">
                        <label class="toggle-label">FAQ Status:</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="edit_is_active" name="is_active" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span id="statusText">Active</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_question">Question *</label>
                        <input type="text" id="edit_question" name="question" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_answer">Answer *</label>
                        <textarea id="edit_answer" name="answer" class="form-control" required rows="6"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_category">Category</label>
                        <select id="edit_category" name="category" class="form-control">
                            <option value="">No Category</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select an existing category or leave empty</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_faq" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal-overlay" style="display: <?php echo $show_success_modal ? 'flex' : 'none'; ?>;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                <h2>✅ Success!</h2>
                <button type="button" class="modal-close" onclick="closeSuccessModalAndRedirect()">×</button>
            </div>
            <div class="modal-body" style="text-align: center; padding: 40px;">
                <div style="font-size: 4rem; margin-bottom: 20px;">🎉</div>
                <h3 style="color: #28a745; margin-bottom: 15px;">FAQ Updated Successfully!</h3>
                <p style="color: #666; font-size: 1.1rem;">Your changes have been saved successfully.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeSuccessModalAndRedirect()">OK</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script>
        // Tab switching
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Update URL without reloading page
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId.replace('view-faqs', 'view-faqs'));
            history.pushState({}, '', url);
        }
        
        // Remove filter
        function removeFilter(filterName) {
            const url = new URL(window.location);
            url.searchParams.delete(filterName);
            url.searchParams.set('page', '1');
            if (filterName === 'view_category') {
                url.searchParams.delete('category');
            }
            window.location.href = url.toString();
        }
        
        // Clear all filters
        function clearFilters() {
            const url = new URL(window.location);
            // Remove filter parameters
            url.searchParams.delete('view_category');
            url.searchParams.delete('category');
            url.searchParams.delete('search');
            url.searchParams.delete('status');
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        
        // Open edit modal with FAQ data
        function openEditModal(faqId) {
            // Show loading state
            document.getElementById('edit_question').value = 'Loading...';
            document.getElementById('edit_answer').value = 'Loading...';
            document.getElementById('editFaqModal').style.display = 'flex';
            
            // Fetch FAQ data via AJAX
            $.ajax({
                url: 'get_faq.php',
                type: 'GET',
                data: { faq_id: faqId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const faq = response.data;
                        document.getElementById('edit_faq_id').value = faq.faq_id;
                        document.getElementById('edit_question').value = faq.question;
                        document.getElementById('edit_answer').value = faq.answer;
                        document.getElementById('edit_category').value = faq.category || '';
                        
                        // Set status toggle
                        const statusToggle = document.getElementById('edit_is_active');
                        statusToggle.checked = faq.is_active == 1;
                        document.getElementById('statusText').textContent = faq.is_active == 1 ? 'Active' : 'Inactive';
                    } else {
                        alert('Error loading FAQ data: ' + response.message);
                        closeEditModal();
                    }
                },
                error: function() {
                    alert('Error loading FAQ data. Please try again.');
                    closeEditModal();
                }
            });
        }
        
        // Close edit modal
        function closeEditModal() {
            document.getElementById('editFaqModal').style.display = 'none';
        }
        
        // Show success modal
        function showSuccessModal() {
            document.getElementById('successModal').style.display = 'flex';
        }
        
        // Close success modal AND redirect to clear POST data
        function closeSuccessModalAndRedirect() {
            // Redirect to same page without POST data to prevent modal from showing again
            const url = new URL(window.location);
            url.searchParams.set('success', 'true'); // Add a success parameter
            window.location.href = url.toString();
        }
        
        // Update status text when toggle changes
        document.getElementById('edit_is_active').addEventListener('change', function() {
            document.getElementById('statusText').textContent = this.checked ? 'Active' : 'Inactive';
        });
        
        // Combine category fields in add form
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('category');
            const newCategoryInput = document.getElementById('new_category');
            
            if (categorySelect && newCategoryInput) {
                categorySelect.addEventListener('change', function() {
                    if (this.value) {
                        newCategoryInput.value = '';
                    }
                });
                
                newCategoryInput.addEventListener('input', function() {
                    if (this.value) {
                        categorySelect.value = '';
                    }
                });
            }
            
            // Check if we need to show a specific tab from URL
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam === 'categories') {
                switchTab('categories');
            } else if (tabParam === 'add-faq') {
                switchTab('add-faq');
            }
        });
        
        // Close modal when clicking outside - ONLY for edit modal
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editFaqModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
        });
        
        // Escape key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
        
        // Build query helper function
        function buildQuery(params) {
            const url = new URL(window.location);
            Object.keys(params).forEach(key => {
                if (params[key]) {
                    url.searchParams.set(key, params[key]);
                } else {
                    url.searchParams.delete(key);
                }
            });
            return '?' + url.searchParams.toString();
        }
    </script>
</body>
</html>
