<?php
// ratings.php - Support agents view their ratings
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../app/config/database.php';
include_once __DIR__ . '/../../includes/header.php';
$db = db();

// Check if user is logged in and has proper role
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Check if user is support agent
$user_role = $_SESSION['role'] ?? '';
if ($user_role !== 'support') {
    header('Location: ../../dashboard.php');
    exit();
}

// Get support agent ID
$support_id = $_SESSION['user_id'];

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Default to start of month
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Default to end of month
$min_rating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;
$sort = $_GET['sort'] ?? 'recent'; // recent, highest, lowest

// Build query to get ratings for tickets assigned to this support agent
$query = "SELECT 
            tr.rating_id,
            tr.rating,
            tr.feedback,
            tr.created_at as rating_date,
            t.ticket_id,
            t.subject as ticket_subject,
            t.status as ticket_status,
            u.username as customer_name,
            u.email as customer_email
          FROM ticket_ratings tr
          JOIN support_tickets t ON tr.ticket_id = t.ticket_id
          JOIN users u ON tr.user_id = u.user_id
          WHERE t.assigned_to = ?";

$params = [$support_id];

// Date filtering
if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(tr.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

// Rating filtering
if ($min_rating > 0) {
    $query .= " AND tr.rating >= ?";
    $params[] = $min_rating;
}

// Sorting
switch ($sort) {
    case 'highest':
        $query .= " ORDER BY tr.rating DESC, tr.created_at DESC";
        break;
    case 'lowest':
        $query .= " ORDER BY tr.rating ASC, tr.created_at DESC";
        break;
    case 'recent':
    default:
        $query .= " ORDER BY tr.created_at DESC";
        break;
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$ratings = $stmt->fetchAll();

// Calculate statistics
$statsQuery = "SELECT 
                COUNT(*) as total_ratings,
                AVG(rating) as average_rating,
                MIN(rating) as min_rating,
                MAX(rating) as max_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
               FROM ticket_ratings tr
               JOIN support_tickets t ON tr.ticket_id = t.ticket_id
               WHERE t.assigned_to = ?";

if (!empty($date_from) && !empty($date_to)) {
    $statsQuery .= " AND DATE(tr.created_at) BETWEEN ? AND ?";
    $statsParams = [$support_id, $date_from, $date_to];
} else {
    $statsParams = [$support_id];
}

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch();

// Calculate percentages
if ($stats['total_ratings'] > 0) {
    $stats['five_star_percent'] = round(($stats['five_star'] / $stats['total_ratings']) * 100);
    $stats['four_star_percent'] = round(($stats['four_star'] / $stats['total_ratings']) * 100);
    $stats['three_star_percent'] = round(($stats['three_star'] / $stats['total_ratings']) * 100);
    $stats['two_star_percent'] = round(($stats['two_star'] / $stats['total_ratings']) * 100);
    $stats['one_star_percent'] = round(($stats['one_star'] / $stats['total_ratings']) * 100);
} else {
    $stats['five_star_percent'] = $stats['four_star_percent'] = $stats['three_star_percent'] = 
    $stats['two_star_percent'] = $stats['one_star_percent'] = 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Support Ratings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .ratings-container {
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
        }
        
        /* Stats Cards */
        .stats-grid {
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
            transform: translateY(-3px);
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
        
        .stat-average { background: #4299e1; }
        .stat-total { background: #38a169; }
        .stat-high { background: #ed8936; }
        .stat-low { background: #e53e3e; }
        
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
        
        /* Rating Distribution */
        .distribution-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .distribution-card h3 {
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }
        
        .rating-bars {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .rating-bar {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .rating-stars {
            width: 100px;
            display: flex;
            align-items: center;
            gap: 5px;
            color: #ecc94b;
        }
        
        .bar-container {
            flex: 1;
            height: 12px;
            background: #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .bar-fill {
            height: 100%;
            background: #38a169;
            border-radius: 6px;
            transition: width 0.5s ease;
        }
        
        .rating-count {
            width: 60px;
            text-align: right;
            color: #4a5568;
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
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            color: #4a5568;
            font-size: 14px;
            font-weight: 500;
        }
        
        .filter-input, .filter-select {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            color: #2d3748;
            background: white;
            transition: border-color 0.3s;
        }
        
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .filter-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .filter-btn {
            padding: 10px 24px;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .filter-btn:hover {
            background: #3182ce;
        }
        
        .clear-btn {
            padding: 10px 20px;
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .clear-btn:hover {
            background: #edf2f7;
            text-decoration: none;
        }
        
        /* Ratings Grid */
        .ratings-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .rating-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #4299e1;
        }
        
        .rating-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .rating-card.excellent { border-left-color: #38a169; }
        .rating-card.good { border-left-color: #4299e1; }
        .rating-card.average { border-left-color: #ed8936; }
        .rating-card.poor { border-left-color: #e53e3e; }
        
        .rating-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .rating-info h3 {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .rating-stars-display {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
        }
        
        .star {
            color: #e2e8f0;
            font-size: 18px;
        }
        
        .star.filled {
            color: #ecc94b;
        }
        
        .rating-meta {
            display: flex;
            gap: 20px;
            color: #718096;
            font-size: 14px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .rating-content {
            margin: 15px 0;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        
        .rating-feedback {
            color: #4a5568;
            line-height: 1.6;
            font-style: italic;
        }
        
        .rating-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .ticket-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ticket-icon {
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
        
        .ticket-details h4 {
            margin: 0;
            font-size: 14px;
            color: #2d3748;
        }
        
        .ticket-details p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #718096;
        }
        
        .rating-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-open { background: #fed7d7; color: #742a2a; }
        .status-progress { background: #bee3f8; color: #2c5282; }
        .status-resolved { background: #c6f6d5; color: #22543d; }
        .status-closed { background: #e2e8f0; color: #4a5568; }
        
        /* No Ratings */
        .no-ratings {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .no-ratings-icon {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .no-ratings h3 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .no-ratings p {
            color: #718096;
            margin-bottom: 20px;
        }
        
        /* Loading Spinner */
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
    </style>
</head>
<body>
    <div class="ratings-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-star"></i>
                My Support Ratings
            </h1>
            <p class="page-subtitle">
                View feedback and ratings from customers you've assisted
            </p>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-average">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['average_rating'] ?? 0, 1); ?>/5</h3>
                    <p>Average Rating</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-total">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_ratings'] ?? 0; ?></h3>
                    <p>Total Ratings</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-high">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['max_rating'] ?? 0; ?>/5</h3>
                    <p>Highest Rating</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-low">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['min_rating'] ?? 0; ?>/5</h3>
                    <p>Lowest Rating</p>
                </div>
            </div>
        </div>

        <!-- Rating Distribution -->
        <?php if ($stats['total_ratings'] > 0): ?>
        <div class="distribution-card">
            <h3><i class="fas fa-chart-bar"></i> Rating Distribution</h3>
            <div class="rating-bars">
                <!-- 5 Stars -->
                <div class="rating-bar">
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: <?php echo $stats['five_star_percent']; ?>%"></div>
                    </div>
                    <div class="rating-count">
                        <?php echo $stats['five_star']; ?> (<?php echo $stats['five_star_percent']; ?>%)
                    </div>
                </div>
                <!-- 4 Stars -->
                <div class="rating-bar">
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <i class="fas fa-star"></i>
                        <?php endfor; ?>
                        <i class="far fa-star"></i>
                    </div>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: <?php echo $stats['four_star_percent']; ?>%"></div>
                    </div>
                    <div class="rating-count">
                        <?php echo $stats['four_star']; ?> (<?php echo $stats['four_star_percent']; ?>%)
                    </div>
                </div>
                <!-- 3 Stars -->
                <div class="rating-bar">
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <i class="fas fa-star"></i>
                        <?php endfor; ?>
                        <?php for ($i = 1; $i <= 2; $i++): ?>
                            <i class="far fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: <?php echo $stats['three_star_percent']; ?>%"></div>
                    </div>
                    <div class="rating-count">
                        <?php echo $stats['three_star']; ?> (<?php echo $stats['three_star_percent']; ?>%)
                    </div>
                </div>
                <!-- 2 Stars -->
                <div class="rating-bar">
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 2; $i++): ?>
                            <i class="fas fa-star"></i>
                        <?php endfor; ?>
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <i class="far fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: <?php echo $stats['two_star_percent']; ?>%"></div>
                    </div>
                    <div class="rating-count">
                        <?php echo $stats['two_star']; ?> (<?php echo $stats['two_star_percent']; ?>%)
                    </div>
                </div>
                <!-- 1 Star -->
                <div class="rating-bar">
                    <div class="rating-stars">
                        <i class="fas fa-star"></i>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <i class="far fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: <?php echo $stats['one_star_percent']; ?>%"></div>
                    </div>
                    <div class="rating-count">
                        <?php echo $stats['one_star']; ?> (<?php echo $stats['one_star_percent']; ?>%)
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-header">
                <h3><i class="fas fa-filter"></i> Filter Ratings</h3>
            </div>
            
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <!-- Date Range -->
                    <div class="filter-group">
                        <label class="filter-label">From Date</label>
                        <input type="date" name="date_from" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">To Date</label>
                        <input type="date" name="date_to" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <!-- Minimum Rating -->
                    <div class="filter-group">
                        <label class="filter-label">Minimum Rating</label>
                        <select name="min_rating" class="filter-select">
                            <option value="0">All Ratings</option>
                            <option value="5" <?php echo $min_rating == 5 ? 'selected' : ''; ?>>5 Stars Only</option>
                            <option value="4" <?php echo $min_rating == 4 ? 'selected' : ''; ?>>4+ Stars</option>
                            <option value="3" <?php echo $min_rating == 3 ? 'selected' : ''; ?>>3+ Stars</option>
                            <option value="2" <?php echo $min_rating == 2 ? 'selected' : ''; ?>>2+ Stars</option>
                            <option value="1" <?php echo $min_rating == 1 ? 'selected' : ''; ?>>1+ Stars</option>
                        </select>
                    </div>
                    
                    <!-- Sort Order -->
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <select name="sort" class="filter-select">
                            <option value="recent" <?php echo $sort == 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                            <option value="highest" <?php echo $sort == 'highest' ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="lowest" <?php echo $sort == 'lowest' ? 'selected' : ''; ?>>Lowest Rating</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <?php if (!empty($_GET)): ?>
                        <a href="ratings.php" class="clear-btn">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Ratings List -->
        <?php if (empty($ratings)): ?>
            <div class="no-ratings">
                <div class="no-ratings-icon">
                    <i class="far fa-star"></i>
                </div>
                <h3>No Ratings Found</h3>
                <p>
                    <?php if (!empty($_GET)): ?>
                        No ratings match your filter criteria.
                    <?php else: ?>
                        You haven't received any ratings yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="ratings-grid">
                <?php foreach ($ratings as $rating): 
                    // Determine rating class for styling
                    $rating_class = '';
                    if ($rating['rating'] >= 4.5) {
                        $rating_class = 'excellent';
                    } elseif ($rating['rating'] >= 3.5) {
                        $rating_class = 'good';
                    } elseif ($rating['rating'] >= 2.5) {
                        $rating_class = 'average';
                    } else {
                        $rating_class = 'poor';
                    }
                    
                    // Determine status class
                    $status_class = 'status-' . $rating['ticket_status'];
                ?>
                    <div class="rating-card <?php echo $rating_class; ?>">
                        <div class="rating-header">
                            <div class="rating-info">
                                <h3>Rating #<?php echo $rating['rating_id']; ?></h3>
                                <div class="rating-stars-display">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star star <?php echo $i <= $rating['rating'] ? 'filled' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span style="margin-left: 10px; font-weight: 600; color: #4a5568;">
                                        <?php echo $rating['rating']; ?>/5
                                    </span>
                                </div>
                                <div class="rating-meta">
                                    <div class="meta-item">
                                        <i class="far fa-calendar"></i>
                                        <span><?php echo date('M d, Y', strtotime($rating['rating_date'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="far fa-clock"></i>
                                        <span><?php echo date('h:i A', strtotime($rating['rating_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <span class="rating-status <?php echo $status_class; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $rating['ticket_status'])); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($rating['feedback'])): ?>
                        <div class="rating-content">
                            <p class="rating-feedback">
                                <i class="fas fa-quote-left" style="opacity: 0.5; margin-right: 10px;"></i>
                                <?php echo htmlspecialchars($rating['feedback']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="rating-footer">
                            <div class="ticket-info">
                                <div class="ticket-icon">
                                    <i class="fas fa-ticket-alt"></i>
                                </div>
                                <div class="ticket-details">
                                    <h4>Ticket #<?php echo $rating['ticket_id']; ?></h4>
                                    <p><?php echo htmlspecialchars($rating['ticket_subject']); ?></p>
                                </div>
                            </div>
                            
                            <div class="customer-info">
                                <div style="text-align: right;">
                                    <h4 style="font-size: 14px; color: #2d3748; margin: 0;">
                                        <?php echo htmlspecialchars($rating['customer_name']); ?>
                                    </h4>
                                    <p style="font-size: 12px; color: #718096; margin: 2px 0 0;">
                                        <?php echo htmlspecialchars($rating['customer_email']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        console.log('Ratings page loaded');
        
        // Handle form submission
        $('#filterForm').submit(function() {
            $('.filter-btn').html('<i class="fas fa-spinner fa-spin"></i> Loading...');
        });
        
        // Set max date for date_to to today
        const today = new Date().toISOString().split('T')[0];
        $('input[name="date_to"]').attr('max', today);
        $('input[name="date_from"]').attr('max', today);
        
        // Validate date range
        $('input[name="date_from"]').change(function() {
            const fromDate = $(this).val();
            const toDateInput = $('input[name="date_to"]');
            
            if (fromDate && toDateInput.val() && fromDate > toDateInput.val()) {
                alert('From date cannot be after To date');
                $(this).val('');
            }
        });
        
        $('input[name="date_to"]').change(function() {
            const toDate = $(this).val();
            const fromDateInput = $('input[name="date_from"]');
            
            if (toDate && fromDateInput.val() && toDate < fromDateInput.val()) {
                alert('To date cannot be before From date');
                $(this).val('');
            }
        });
    });
    </script>
</body>
</html>