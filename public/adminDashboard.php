
<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include auth functions
require_once __DIR__ . '/../lib/auth.php';

// Use auth() function to check login and role
require_login(); // Check if user is logged in

// Check if user is admin
// First check new system (Practical 7 style), then old system
$user = $_SESSION['user'] ?? null;
$user_role = $user['role'] ?? $_SESSION['role'] ?? 'member';

if ($user_role !== 'admin') {
    // Redirect non-admins to member dashboard
    header("Location: memberDashboard.php");
    exit();
}

/* ========================
   Include Dependencies
======================== */
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/UserModel.php';
require_once __DIR__ . '/../app/models/OrderModel.php';
require_once __DIR__ . '/../app/models/ProductModel.php';
require_once __DIR__ . '/../lib/base.php';

include_once __DIR__ . '/../includes/header.php';

/* ========================
   Initialize Database Connection
======================== */
$db = db();
$userModel = new User($db);
$currentUser = $userModel->getUserById($_SESSION['user_id']);

/* ========================
   Profile Update Handling
======================== */
$profileMessage = '';
$profileError = '';
$passwordMessage = '';
$passwordError = '';

// Handle Profile Information Update
if (isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    
    // Validate inputs
    if (empty($newUsername) || empty($newEmail)) {
        $profileError = "Username and email are required.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $profileError = "Please enter a valid email address.";
    } else {
        try {
            // Check if username already exists (excluding current user)
            $usernameCheck = $userModel->getUserByUsername($newUsername);
            if ($usernameCheck && $usernameCheck['user_id'] != $_SESSION['user_id']) {
                $profileError = "Username already exists.";
            }
            
            // Check if email already exists (excluding current user)
            $emailCheck = $userModel->getUserByEmail($newEmail);
            if ($emailCheck && $emailCheck['user_id'] != $_SESSION['user_id']) {
                $profileError = "Email already exists.";
            }
            
            // If no errors, update profile
            if (empty($profileError)) {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE user_id = ?");
                if ($stmt->execute([$newUsername, $newEmail, $_SESSION['user_id']])) {
                    // Update session variables
                    $_SESSION['username'] = $newUsername;
                    // Refresh current user data
                    $currentUser = $userModel->getUserById($_SESSION['user_id']);
                    $profileMessage = "Profile updated successfully!";
                } else {
                    $profileError = "Failed to update profile. Please try again.";
                }
            }
        } catch (Exception $e) {
            $profileError = "Database error: " . $e->getMessage();
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordError = "All password fields are required.";
    } elseif (!password_verify($currentPassword, $currentUser['password_hash'])) {
        $passwordError = "Current password is incorrect.";
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = "New passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $passwordError = "New password must be at least 6 characters long.";
    } else {
        try {
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password in database
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
            if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                $passwordMessage = "Password changed successfully!";
                // Clear the password fields
                $_POST['current_password'] = $_POST['new_password'] = $_POST['confirm_password'] = '';
            } else {
                $passwordError = "Failed to change password. Please try again.";
            }
        } catch (Exception $e) {
            $passwordError = "Database error: " . $e->getMessage();
            error_log("Password change error: " . $e->getMessage());
        }
    }
}

/* ========================
   Fetch Data for Admin Dashboard
======================== */
// Get filter parameters from GET request
$reportType = $_GET['report_type'] ?? 'sales';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Initialize report data
$reportData = [];
$reportTitle = '';

// Process report generation if form is submitted
if (isset($_GET['report_type'])) {
    try {
        switch ($reportType) {
            case 'sales':
                $reportTitle = 'Sales Report';
                $reportData = generateSalesReport($db, $dateFrom, $dateTo);
                break;
                
            case 'inventory':
                $reportTitle = 'Inventory Report';
                $reportData = generateInventoryReport($db);
                break;
                
            case 'customer':
                $reportTitle = 'Customer Report';
                $reportData = generateCustomerReport($db, $dateFrom, $dateTo);
                break;
                
            case 'financial':
                $reportTitle = 'Financial Report';
                $reportData = generateFinancialReport($db, $dateFrom, $dateTo);
                break;
                
            default:
                $reportTitle = 'Sales Report';
                $reportData = generateSalesReport($db, $dateFrom, $dateTo);
        }
    } catch (Exception $e) {
        error_log("Report generation error: " . $e->getMessage());
        $reportError = "Error generating report: " . $e->getMessage();
    }
}

// Admin Dashboard Data
$orderModel = new Order($db);
$productModel = new Product($db);

// Try to get data, handle errors gracefully
try {
     $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    $totalOrders = $orderModel->getTotalOrdersCount();
    $totalRevenue = $orderModel->getTotalRevenue();
    $totalProducts = $productModel->getTotalProductsCount();
    $totalCustomers = $userModel->getTotalCustomersCount();
    $recentOrders = $orderModel->getRecentOrders(10);
    $lowStockProducts = $productModel->getLowStockProducts();
    
    // CHART DATA: Get current month revenue data
    $currentMonth = date('Y-m');
    $revenueStmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as order_count,
            SUM(total_amount) as revenue
        FROM orders 
        WHERE DATE_FORMAT(order_date, '%Y-%m') = ?
        GROUP BY status
        ORDER BY 
            CASE status
                WHEN 'pending' THEN 1
                WHEN 'delivered' THEN 2
                WHEN 'cancelled' THEN 3
                ELSE 4
            END
    ");
    $revenueStmt->execute([$currentMonth]);
    $revenueData = $revenueStmt->fetchAll();
    
    // Calculate total revenue for current month
    $currentMonthTotal = 0;
    $currentMonthLabels = [];
    $currentMonthRevenue = [];
    $currentMonthCounts = [];
    
    foreach ($revenueData as $row) {
        $currentMonthTotal += (float)$row['revenue'];
        $currentMonthLabels[] = ucfirst($row['status']);
        $currentMonthRevenue[] = (float)$row['revenue'];
        $currentMonthCounts[] = (int)$row['order_count'];
    }
    
    // CHART DATA: Get order status distribution (all time)
    $statusStmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM orders 
        GROUP BY status
        ORDER BY 
            CASE status
                WHEN 'pending' THEN 1
                WHEN 'delivered' THEN 2
                WHEN 'cancelled' THEN 3
                ELSE 4
            END
    ");
    $statusStmt->execute();
    $statusData = $statusStmt->fetchAll();
    
    // Prepare chart data for JavaScript
    $statusLabels = [];
    $statusCounts = [];
    
    // Process status data
    foreach ($statusData as $row) {
        $statusLabels[] = ucfirst($row['status']);
        $statusCounts[] = (int)$row['count'];
    }
    
    // Get previous month revenue for comparison
    $prevMonth = date('Y-m', strtotime('-1 month'));
    $prevMonthStmt = $db->prepare("
        SELECT SUM(total_amount) as revenue 
        FROM orders 
        WHERE DATE_FORMAT(order_date, '%Y-%m') = ?
        AND status IN ('delivered')
    ");
    $prevMonthStmt->execute([$prevMonth]);
    $prevMonthResult = $prevMonthStmt->fetch();
    $prevMonthTotal = (float)($prevMonthResult['revenue'] ?? 0);
    
    // Calculate growth percentage
    $growthPercentage = 0;
    if ($prevMonthTotal > 0) {
        $growthPercentage = (($currentMonthTotal - $prevMonthTotal) / $prevMonthTotal) * 100;
    } else if ($currentMonthTotal > 0) {
        $growthPercentage = 100; // First month with sales
    }
    
    // Get Top Products Data
    $topProductsStmt = $db->prepare("
        SELECT p.name, p.price, SUM(oi.quantity) as total_sold
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        GROUP BY p.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $topProductsStmt->execute();
    $topProducts = $topProductsStmt->fetchAll();
    
} catch (Exception $e) {
    // Set default values if tables don't exist yet
    $totalOrders = 0;
    $totalRevenue = 0;
    $totalProducts = 0;
    $totalCustomers = 0;
    $recentOrders = [];
    $lowStockProducts = [];
    $currentMonthTotal = 0;
    $currentMonthLabels = [];
    $currentMonthRevenue = [];
    $currentMonthCounts = [];
    $statusLabels = [];
    $statusCounts = [];
    $growthPercentage = 0;
    $topProducts = [];
    error_log("Dashboard error: " . $e->getMessage());
    echo "Database connection error. Please try again later.";
    exit();
}

// Report Generation Functions
function generateSalesReport($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT 
            o.order_id,
            o.order_date,
            u.username as customer_name,
            o.total_amount,
            o.status,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll();
}

function generateInventoryReport($db) {
    $stmt = $db->prepare("
        SELECT 
            p.product_id,
            p.name,
            p.price,
            p.stock_quantity,
            c.name as category_name,
            p.created_at
        FROM products p
        LEFT JOIN category c ON p.category_id = c.category_id
        ORDER BY p.stock_quantity ASC, p.name ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function generateCustomerReport($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.email,
            u.created_at as join_date,
            COUNT(o.order_id) as total_orders,
            SUM(o.total_amount) as total_spent,
            MAX(o.order_date) as last_order_date
        FROM users u
        LEFT JOIN orders o ON u.user_id = o.user_id 
            AND DATE(o.order_date) BETWEEN ? AND ?
        WHERE u.role = 'member'
        GROUP BY u.user_id
        ORDER BY total_spent DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll();
}

function generateFinancialReport($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT 
            DATE(o.order_date) as order_date,
            COUNT(o.order_id) as total_orders,
            SUM(o.total_amount) as daily_revenue,
            AVG(o.total_amount) as avg_order_value,
            SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as delivered_revenue,
            SUM(CASE WHEN o.status = 'cancelled' THEN o.total_amount ELSE 0 END) as cancelled_revenue
        FROM orders o
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY DATE(o.order_date)
        ORDER BY order_date DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll();
}
?>

<div class="container">
    <!-- Add these in the head section or before the closing body tag -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <div class="dashboard-header">
        <h2 class="dashboard-title">Admin Dashboard 👑</h2>
    </div>

    <div class="dashboard-info">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($currentUser['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($currentUser['email']); ?></p>
                <p><strong>Account Type:</strong> 
                    <span class="badge badge-admin">
                        <?php echo htmlspecialchars(ucfirst($currentUser['role'])); ?>
                    </span>
                </p>
                <p><strong>Joined:</strong> <?php echo date('F d, Y', strtotime($currentUser['created_at'] ?? 'now')); ?></p>
            </div>
            <div class="glass-buttons">
    <button type="button" class="btn-glass-edit" onclick="toggleProfileEdit()">
        <i>✏️</i> Edit Profile
    </button>
    <button type="button" class="btn-glass-password" onclick="togglePasswordChange()">
        <i>🔒</i> Change Password
    </button>
</div>
        </div>
    </div>

    <hr>

    <!-- ========================
           PROFILE EDITING SECTIONS
    ======================== -->
    
    <!-- Edit Profile Form -->
    <div id="profileEditSection" style="display: none; margin: 2rem 0; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
        <h3>✏️ Edit Profile Information</h3>
        
        <?php if ($profileMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($profileMessage); ?></div>
        <?php endif; ?>
        
        <?php if ($profileError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($profileError); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="profile-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($currentUser['username']); ?>" 
                       class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($currentUser['email']); ?>" 
                       class="form-control" required>
            </div>
            
            <div class="form-buttons">
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                <button type="button" onclick="toggleProfileEdit()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
    
    <!-- Change Password Form -->
    <div id="passwordChangeSection" style="display: none; margin: 2rem 0; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
        <h3>🔒 Change Password</h3>
        
        <?php if ($passwordMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($passwordMessage); ?></div>
        <?php endif; ?>
        
        <?php if ($passwordError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($passwordError); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="password-form">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" 
                       class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" 
                       class="form-control" required minlength="6">
                <small class="form-text text-muted">Minimum 6 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       class="form-control" required minlength="6">
            </div>
            
            <div class="form-buttons">
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                <button type="button" onclick="togglePasswordChange()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>

    <!-- ========================
           ADMIN DASHBOARD WITH REPORT FUNCTIONALITY
    ======================== -->
    <!-- Admin Navigation -->
    <div class="dashboard-actions" style="margin: 2rem 0;">
        <h3>Quick Actions</h3>
        <div class="action-buttons">
            <a href="admin/products.php" class="btn btn-primary">Manage Products</a>
            <a href="admin/product_add.php" class="btn btn-success">Add New Product</a>
            <a href="admin/orders.php" class="btn btn-orange">View All Orders</a>
            <a href="admin/categories.php" class="btn btn-info">🏷️ Edit Categories</a>
        </div>
    </div>
    
    <!-- Report Filters Section -->
    <div class="filter-section" style="margin-bottom: 2rem;">
        <h3>📊 Report Parameters</h3>
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Report Type</label>
                <select name="report_type">
                    <option value="sales" <?php echo ($reportType == 'sales') ? 'selected' : ''; ?>>Sales Report</option>
                    <option value="inventory" <?php echo ($reportType == 'inventory') ? 'selected' : ''; ?>>Inventory Report</option>
                    <option value="customer" <?php echo ($reportType == 'customer') ? 'selected' : ''; ?>>Customer Report</option>
                    <option value="financial" <?php echo ($reportType == 'financial') ? 'selected' : ''; ?>>Financial Report</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </form>
    </div>
    
    <!-- Display Generated Report -->
    <?php if (isset($_GET['report_type']) && !empty($reportData)): ?>
    <div class="report-results" style="margin: 2rem 0; padding: 1rem; background: #f8f9fa; border-radius: 8px;">

        <div class="report-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h3>📋 <?php echo htmlspecialchars($reportTitle); ?> (<?php echo date('M d, Y', strtotime($dateFrom)); ?> - <?php echo date('M d, Y', strtotime($dateTo)); ?>)</h3>
    <div>
        <button onclick="exportToCSV()" class="btn btn-success btn-sm">📥 Export CSV</button>
        <button onclick="exportToPDF()" class="btn btn-pdf btn-sm">📄 Export PDF</button>
    </div>
</div>
        
        <div class="report-summary" style="margin-bottom: 1rem; padding: 1rem; background: white; border-radius: 4px;">
            <p><strong>Period:</strong> <?php echo date('F d, Y', strtotime($dateFrom)); ?> to <?php echo date('F d, Y', strtotime($dateTo)); ?></p>
            <p><strong>Total Records:</strong> <?php echo count($reportData); ?></p>
            <p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <div class="report-table" style="overflow-x: auto;">
            <table class="dashboard-table" style="width: 100%;">
                <thead>
                    <?php if ($reportType == 'sales'): ?>
                    <tr>
                        <th>No.</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total (RM)</th>
                        <th>Status</th>
                    </tr>
                    <?php elseif ($reportType == 'inventory'): ?>
                    <tr>
                        <th>No.</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price (RM)</th>
                        <th>Stock</th>
                        <th>Created Date</th>
                    </tr>
                    <?php elseif ($reportType == 'customer'): ?>
                    <tr>
                        <th>No.</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Join Date</th>
                        <th>Total Orders</th>
                        <th>Total Spent (RM)</th>
                        <th>Last Order</th>
                    </tr>
                    <?php elseif ($reportType == 'financial'): ?>
                    <tr>
                        <th>No.</th>
                        <th>Date</th>
                        <th>Orders</th>
                        <th>Revenue (RM)</th>
                        <th>Avg Order (RM)</th>
                        <th>Delivered (RM)</th>
                        <th>Cancelled (RM)</th>
                    </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($reportData as $row): 
                    ?>
                    <tr>
                        <td><?php echo $counter; ?></td>
                        <?php if ($reportType == 'sales'): ?>
                        <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['customer_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $row['items_count']; ?></td>
                        <td><?php echo number_format($row['total_amount'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <?php elseif ($reportType == 'inventory'): ?>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo number_format($row['price'], 2); ?></td>
                        <td>
                            <?php if ($row['stock_quantity'] <= 10): ?>
                            <span class="badge badge-warning"><?php echo $row['stock_quantity']; ?> (Low)</span>
                            <?php elseif ($row['stock_quantity'] == 0): ?>
                            <span class="badge badge-danger"><?php echo $row['stock_quantity']; ?> (Out of Stock)</span>
                            <?php else: ?>
                            <span class="badge badge-success"><?php echo $row['stock_quantity']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <?php elseif ($reportType == 'customer'): ?>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['join_date'])); ?></td>
                        <td><?php echo $row['total_orders']; ?></td>
                        <td><?php echo number_format($row['total_spent'] ?? 0, 2); ?></td>
                        <td>
                            <?php if ($row['last_order_date']): ?>
                            <?php echo date('M d, Y', strtotime($row['last_order_date'])); ?>
                            <?php else: ?>
                            <span class="text-muted">No orders</span>
                            <?php endif; ?>
                        </td>
                        <?php elseif ($reportType == 'financial'): ?>
                        <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                        <td><?php echo $row['total_orders']; ?></td>
                        <td><?php echo number_format($row['daily_revenue'] ?? 0, 2); ?></td>
                        <td><?php echo number_format($row['avg_order_value'] ?? 0, 2); ?></td>
                        <td><?php echo number_format($row['delivered_revenue'] ?? 0, 2); ?></td>
                        <td><?php echo number_format($row['cancelled_revenue'] ?? 0, 2); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php 
                    $counter++;
                    endforeach; 
                    
                    if (empty($reportData)): 
                    ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem;">
                            <p class="empty-state">No data found for the selected criteria.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Report Summary Stats -->
        <?php if (!empty($reportData)): ?>
        <div class="report-stats">
            <h4>Summary Statistics</h4>
            <div class="report-stats-grid">
                <?php if ($reportType == 'sales'): ?>
                <div class="stat-box">
                    <small>Total Revenue</small>
                    <h5>RM <?php 
                        $total = array_sum(array_column($reportData, 'total_amount'));
                        echo number_format($total, 2);
                    ?></h5>
                </div>
                <div class="stat-box">
                    <small>Total Orders</small>
                    <h5><?php echo count($reportData); ?></h5>
                </div>
                <div class="stat-box">
                    <small>Avg Order Value</small>
                    <h5>RM <?php 
                        $avg = count($reportData) > 0 ? $total / count($reportData) : 0;
                        echo number_format($avg, 2);
                    ?></h5>
                </div>
                <?php elseif ($reportType == 'inventory'): ?>
                <div class="stat-box">
                    <small>Total Products</small>
                    <h5><?php echo count($reportData); ?></h5>
                </div>
                <div class="stat-box">
                    <small>Total Stock Value</small>
                    <h5>RM <?php 
                        $totalValue = 0;
                        foreach ($reportData as $product) {
                            $totalValue += $product['price'] * $product['stock_quantity'];
                        }
                        echo number_format($totalValue, 2);
                    ?></h5>
                </div>
                <?php elseif ($reportType == 'customer'): ?>
                <div class="stat-box">
                    <small>Total Customers</small>
                    <h5><?php echo count($reportData); ?></h5>
                </div>
                <div class="stat-box">
                    <small>Total Revenue</small>
                    <h5>RM <?php 
                        $totalRevenue = array_sum(array_column($reportData, 'total_spent'));
                        echo number_format($totalRevenue, 2);
                    ?></h5>
                </div>
                <?php elseif ($reportType == 'financial'): ?>
                <div class="stat-box">
                    <small>Total Revenue</small>
                    <h5>RM <?php 
                        $totalRevenue = array_sum(array_column($reportData, 'daily_revenue'));
                        echo number_format($totalRevenue, 2);
                    ?></h5>
                </div>
                <div class="stat-box">
                    <small>Total Orders</small>
                    <h5><?php 
                        $totalOrders = array_sum(array_column($reportData, 'total_orders'));
                        echo $totalOrders;
                    ?></h5>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif (isset($_GET['report_type']) && empty($reportData)): ?>
    <div class="alert alert-info">
        <h4>No Data Found</h4>
        <p>No records were found for the selected report criteria. Please try different dates or parameters.</p>
        <p><small>Tips: Try using a wider date range or check if you have any orders in the system.</small></p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($reportError)): ?>
    <div class="alert alert-danger">
        <h4>Error Generating Report</h4>
        <p><?php echo htmlspecialchars($reportError); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Admin Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Revenue</h3>
            <p class="stat-value">RM <?php echo number_format($totalRevenue, 2); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Orders</h3>
            <p class="stat-value"><?php echo $totalOrders; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Products</h3>
            <p class="stat-value"><?php echo $totalProducts; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Customers</h3>
            <p class="stat-value"><?php echo $totalCustomers; ?></p>
        </div>
    </div>

    <!-- Charts Section -->
<div class="charts-section" style="margin: 2rem 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">📈 Sales Analytics</h3>
        <button type="button" class="btn-green" onclick="printSalesAnalytics()">🖨️ Print Sales Analytics</button>
    </div>
    
    <!-- Wrap the content that should be printed -->
    <div id="salesAnalyticsContent">
        <div class="charts-row" style="display: flex; flex-wrap: wrap; gap: 2rem;">
            <!-- Monthly Revenue Chart -->
            <div class="chart-container" style="flex: 1; min-width: 300px;">
                <h4>Monthly Revenue (RM)</h4>
                <div class="chart-wrapper" style="height: 300px;">
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-summary">
                    <div class="chart-total">
                        <strong>RM <?php echo number_format($currentMonthTotal, 2); ?></strong>
                        <span class="chart-growth <?php echo $growthPercentage >= 0 ? 'positive' : 'negative'; ?>">
                            <?php if ($growthPercentage >= 0): ?>↗<?php else: ?>↘<?php endif; ?>
                            <?php echo number_format(abs($growthPercentage), 1); ?>%
                        </span>
                    </div>
                    <small>Current month vs previous</small>
                </div>
            </div>
            
            <!-- Order Status Distribution Chart -->
            <div class="chart-container" style="flex: 1; min-width: 300px;">
                <div class="chart-header">
                    <h4>Order Status Distribution</h4>
                    <span class="chart-info">All Orders</span>
                </div>
                <div class="total-summary">
                    <div class="total-orders">
                        <h3><?php echo $totalOrders; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                <div class="chart-canvas-container" style="height: 250px;">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="chart-footer">
                    <small>Distribution of all orders by status</small>
                </div>
            </div>
        </div>
    </div>
</div>

   <!-- Additional Reports Data -->
<div class="report-details" style="margin: 2rem 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">📋 Detailed Report Data</h3>
        <button type="button" class="btn-green" onclick="printDetailedReports()">🖨️ Print Detailed Report</button>
    </div>
    
    <!-- Wrap the content that should be printed -->
    <div id="detailedReportsContent">
        <!-- Two Column Layout for Reports -->
        <div style="display: flex; flex-wrap: wrap; gap: 2rem;">
            <!-- Recent Orders Table -->
            <div class="recent-orders" style="flex: 1; min-width: 300px;">
                <h4>Recent Orders</h4>
                <?php if (!empty($recentOrders)): ?>
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total (RM)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?php echo $counter; ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name'] ?? $order['username'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'] ?? $order['created_at'])); ?></td>
                            <td><?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php 
                        $counter++;
                        endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="empty-state">No recent orders found.</p>
                <?php endif; ?>
            </div>
            
            <!-- Top Products -->
            <div class="top-products" style="flex: 1; min-width: 300px;">
                <h4>Top Selling Products</h4>
                <?php if (!empty($topProducts)): ?>
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Product Name</th>
                            <th>Price (RM)</th>
                            <th>Units Sold</th>
                            <th>Revenue (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($topProducts as $product): 
                            $revenue = $product['price'] * $product['total_sold'];
                        ?>
                        <tr>
                            <td><?php echo $counter; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['total_sold']; ?></td>
                            <td><?php echo number_format($revenue, 2); ?></td>
                        </tr>
                        <?php 
                        $counter++;
                        endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="empty-state">No product sales data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if (!empty($lowStockProducts)): ?>
        <div class="alert alert-warning">
            <h3>⚠️ Low Stock Alert</h3>
            <ul>
                <?php 
                $counter = 1;
                foreach ($lowStockProducts as $product): ?>
                    <li>
                        <strong><?php echo $counter; ?>. <?php echo htmlspecialchars($product['name']); ?></strong> - 
                        Only <?php echo $product['stock_quantity']; ?> left
                    </li>
                <?php 
                $counter++;
                endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

        <!-- Low Stock Alert -->
        <?php if (!empty($lowStockProducts)): ?>
            <div class="alert alert-warning">
                <h3>⚠️ Low Stock Alert</h3>
                <ul>
                    <?php 
                    $counter = 1;
                    foreach ($lowStockProducts as $product): ?>
                        <li>
                            <strong><?php echo $counter; ?>. <?php echo htmlspecialchars($product['name']); ?></strong> - 
                            Only <?php echo $product['stock_quantity']; ?> left
                        </li>
                    <?php 
                    $counter++;
                    endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Simple color palette
const colors = {
    primary: '#5a4bff',
    success: '#28a745',
    warning: '#ffc107',
    danger: '#dc3545',
    info: '#17a2b8',
    light: '#f8f9fa',
    dark: '#343a40'
};

// Status colors mapping
const statusColors = {
    'pending': '#ffc107',
    'confirmed': '#17a2b8',
    'shipped': '#5a4bff',
    'delivered': '#28a745',
    'cancelled': '#dc3545'
};

// Profile editing functions
function toggleProfileEdit() {
    const profileSection = document.getElementById('profileEditSection');
    const passwordSection = document.getElementById('passwordChangeSection');
    
    if (profileSection.style.display === 'none' || profileSection.style.display === '') {
        // Hide password section if open
        passwordSection.style.display = 'none';
        // Show profile section
        profileSection.style.display = 'block';
        // Scroll to profile section
        profileSection.scrollIntoView({ behavior: 'smooth' });
    } else {
        profileSection.style.display = 'none';
    }
}

function togglePasswordChange() {
    const profileSection = document.getElementById('profileEditSection');
    const passwordSection = document.getElementById('passwordChangeSection');
    
    if (passwordSection.style.display === 'none' || passwordSection.style.display === '') {
        // Hide profile section if open
        profileSection.style.display = 'none';
        // Show password section
        passwordSection.style.display = 'block';
        // Scroll to password section
        passwordSection.scrollIntoView({ behavior: 'smooth' });
    } else {
        passwordSection.style.display = 'none';
    }
}

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing charts...');
    
    // ========== REVENUE CHART ==========
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        console.log('Creating revenue chart...');
        
        // Get data from PHP
        const monthLabels = <?php echo json_encode($currentMonthLabels ?? []); ?>;
        const monthRevenue = <?php echo json_encode($currentMonthRevenue ?? []); ?>;
        
        // Create chart
        const revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: monthLabels.length > 0 ? monthLabels : ['No Data'],
                datasets: [{
                    label: 'Revenue (RM)',
                    data: monthRevenue.length > 0 ? monthRevenue : [0],
                    backgroundColor: monthLabels.map(label => {
                        const status = label.toLowerCase();
                        return statusColors[status] || colors.primary;
                    }),
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `RM ${context.parsed.y.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'RM ' + value;
                            }
                        }
                    }
                }
            }
        });
    } else {
        console.error('Revenue chart canvas not found!');
    }
    
    // ========== STATUS CHART ==========
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        console.log('Creating status chart...');
        
        // Get data from PHP
        const statusLabels = <?php echo json_encode($statusLabels ?? []); ?>;
        const statusCounts = <?php echo json_encode($statusCounts ?? []); ?>;
        
        // Create chart
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels.length > 0 ? statusLabels : ['No Data'],
                datasets: [{
                    data: statusCounts.length > 0 ? statusCounts : [1],
                    backgroundColor: statusLabels.map(label => {
                        const status = label.toLowerCase();
                        return statusColors[status] || colors.primary;
                    }),
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 10,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} orders (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    } else {
        console.error('Status chart canvas not found!');
    }
    
    console.log('Charts initialization complete!');
});

// Handle window resize
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        console.log('Window resized, charts should auto-adjust');
    }, 250);
});

// Improved Print Sales Analytics Function with Chart Capture
function printSalesAnalytics() {
    // Show loading indicator
    const originalButtonText = document.querySelector('.btn-green[onclick="printSalesAnalytics()"]').innerHTML;
    document.querySelector('.btn-green[onclick="printSalesAnalytics()"]').innerHTML = '⏳ Generating Report...';
    document.querySelector('.btn-green[onclick="printSalesAnalytics()"]').disabled = true;
    
    // Get the sales analytics content
    const salesAnalyticsContent = document.getElementById('salesAnalyticsContent');
    
    // Create a clone of the content to avoid affecting the original
    const contentClone = salesAnalyticsContent.cloneNode(true);
    
    // Remove any existing canvas elements from the clone
    const canvases = contentClone.querySelectorAll('canvas');
    canvases.forEach(canvas => canvas.remove());
    
    // Create image placeholders for charts
    const chartWrappers = contentClone.querySelectorAll('.chart-wrapper, .chart-canvas-container');
    
    // Create a promise array to capture all charts
    const chartPromises = [];
    
    // Get the original canvases
    const originalCanvases = salesAnalyticsContent.querySelectorAll('canvas');
    
    // Capture each chart as an image
    originalCanvases.forEach((canvas, index) => {
        chartPromises.push(
            new Promise((resolve) => {
                // Convert canvas to data URL
                const imageData = canvas.toDataURL('image/png');
                
                // Create img element
                const img = document.createElement('img');
                img.src = imageData;
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                img.style.display = 'block';
                img.style.margin = '0 auto';
                
                // Replace chart wrapper content with the image
                if (chartWrappers[index]) {
                    chartWrappers[index].innerHTML = '';
                    chartWrappers[index].appendChild(img);
                }
                
                resolve();
            })
        );
    });
    
    // Wait for all charts to be captured
    Promise.all(chartPromises).then(() => {
        // Create a new window for printing
        const printWindow = window.open('', '_blank', 'width=1000,height=700');
        
        // Get chart data for the text report
        const chartData = {
            revenueData: <?php echo json_encode($revenueData ?? []); ?>,
            statusData: <?php echo json_encode($statusData ?? []); ?>,
            currentMonthTotal: <?php echo $currentMonthTotal ?? 0; ?>,
            growthPercentage: <?php echo $growthPercentage ?? 0; ?>,
            totalOrders: <?php echo $totalOrders ?? 0; ?>,
            monthLabels: <?php echo json_encode($currentMonthLabels ?? []); ?>,
            monthRevenue: <?php echo json_encode($currentMonthRevenue ?? []); ?>,
            topProducts: <?php echo json_encode($topProducts ?? []); ?>
        };
        
        const currentDate = new Date();
        const monthNames = ["January", "February", "March", "April", "May", "June",
                          "July", "August", "September", "October", "November", "December"];
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Sales Analytics Report - <?php echo date('Y-m-d'); ?></title>
                <style>
                    /* Reset and base styles */
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    body {
                        font-family: 'Segoe UI', 'Arial', sans-serif;
                        padding: 30px;
                        color: #333;
                        line-height: 1.6;
                        background: white;
                        font-size: 14px;
                    }
                    
                    /* Header styles */
                    .report-header {
                        text-align: center;
                        margin-bottom: 30px;
                        padding-bottom: 20px;
                        border-bottom: 3px solid #5a4bff;
                    }
                    
                    .report-title {
                        color: #333;
                        margin: 0 0 10px 0;
                        font-size: 28px;
                        font-weight: 600;
                    }
                    
                    .report-meta {
                        color: #666;
                        font-size: 14px;
                        margin: 5px 0;
                    }
                    
                    .report-period {
                        background: #f8f9fa;
                        display: inline-block;
                        padding: 8px 15px;
                        border-radius: 20px;
                        margin-top: 10px;
                        font-weight: 500;
                    }
                    
                    /* Summary section */
                    .summary-highlight {
                        background: linear-gradient(135deg, #f8f9ff 0%, #eef0ff 100%);
                        border-radius: 12px;
                        padding: 25px;
                        margin: 25px 0;
                        text-align: center;
                        border: 2px solid #e6e9ff;
                    }
                    
                    .summary-value {
                        font-size: 42px;
                        color: #5a4bff;
                        margin: 10px 0;
                        font-weight: bold;
                        line-height: 1.2;
                    }
                    
                    .summary-label {
                        color: #666;
                        font-size: 16px;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                        margin-bottom: 15px;
                    }
                    
                    .growth-indicator {
                        display: inline-flex;
                        align-items: center;
                        padding: 8px 16px;
                        border-radius: 25px;
                        font-weight: 600;
                        margin-top: 10px;
                        font-size: 14px;
                    }
                    
                    .growth-positive {
                        background: #d4edda;
                        color: #155724;
                    }
                    
                    .growth-negative {
                        background: #f8d7da;
                        color: #721c24;
                    }
                    
                    /* Charts section */
                    .charts-section-print {
                        margin: 35px 0;
                    }
                    
                    .chart-container-print {
                        margin-bottom: 40px;
                        page-break-inside: avoid;
                    }
                    
                    .chart-header-print {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 15px;
                        padding-bottom: 10px;
                        border-bottom: 2px solid #f0f0f0;
                    }
                    
                    .chart-title {
                        font-size: 18px;
                        color: #333;
                        font-weight: 600;
                    }
                    
                    .chart-info {
                        color: #666;
                        font-size: 13px;
                        background: #f8f9fa;
                        padding: 4px 10px;
                        border-radius: 12px;
                    }
                    
                    .chart-image-container {
                        background: white;
                        border: 1px solid #eaeaea;
                        border-radius: 8px;
                        padding: 20px;
                        margin: 15px 0;
                    }
                    
                    .chart-image-container img {
                        display: block;
                        max-width: 100%;
                        height: auto;
                        margin: 0 auto;
                    }
                    
                    /* Data tables */
                    .data-section {
                        margin: 40px 0;
                        page-break-inside: avoid;
                    }
                    
                    .section-title {
                        font-size: 18px;
                        color: #333;
                        margin-bottom: 20px;
                        padding-bottom: 10px;
                        border-bottom: 2px solid #5a4bff;
                    }
                    
                    .data-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                        background: white;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                    }
                    
                    .data-table th {
                        background: #5a4bff;
                        color: white;
                        padding: 12px 15px;
                        text-align: left;
                        font-weight: 600;
                        font-size: 13px;
                    }
                    
                    .data-table td {
                        padding: 10px 15px;
                        border-bottom: 1px solid #f0f0f0;
                        font-size: 13px;
                    }
                    
                    .data-table tr:last-child td {
                        border-bottom: none;
                    }
                    
                    .data-table tr:nth-child(even) {
                        background: #f9f9f9;
                    }
                    
                    /* Stats grid */
                    .stats-grid-print {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 20px;
                        margin: 25px 0;
                    }
                    
                    .stat-card-print {
                        background: white;
                        border: 1px solid #eaeaea;
                        border-radius: 10px;
                        padding: 20px;
                        text-align: center;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                    }
                    
                    .stat-value-print {
                        font-size: 28px;
                        color: #5a4bff;
                        margin: 10px 0;
                        font-weight: bold;
                    }
                    
                    .stat-label-print {
                        color: #666;
                        font-size: 13px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }
                    
                    /* Footer */
                    .report-footer {
                        margin-top: 50px;
                        padding-top: 20px;
                        border-top: 2px dashed #ddd;
                        text-align: center;
                        color: #666;
                        font-size: 12px;
                    }
                    
                    /* Print-specific styles */
                    @media print {
                        @page {
                            margin: 20mm;
                            size: A4;
                        }
                        
                        body {
                            padding: 0;
                            font-size: 12px;
                        }
                        
                        .report-title {
                            font-size: 24px;
                        }
                        
                        .summary-value {
                            font-size: 36px;
                        }
                        
                        .stat-value-print {
                            font-size: 22px;
                        }
                        
                        .chart-container-print {
                            page-break-inside: avoid;
                            break-inside: avoid;
                        }
                        
                        .data-section {
                            page-break-inside: avoid;
                            break-inside: avoid;
                        }
                        
                        .no-print {
                            display: none !important;
                        }
                    }
                    
                    /* Utility classes */
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .mb-20 { margin-bottom: 20px; }
                    .mt-20 { margin-top: 20px; }
                    .p-20 { padding: 20px; }
                    .bg-light { background: #f8f9fa; }
                    .border { border: 1px solid #dee2e6; }
                    .rounded { border-radius: 8px; }
                    .d-flex { display: flex; }
                    .justify-between { justify-content: space-between; }
                    .align-center { align-items: center; }
                    
                    /* Status badges for printing */
                    .status-badge-print {
                        padding: 4px 10px;
                        border-radius: 12px;
                        font-size: 11px;
                        font-weight: 600;
                        display: inline-block;
                    }
                    
                    .status-pending { background: #fff3cd; color: #856404; }
                    .status-delivered { background: #d4edda; color: #155724; }
                    .status-cancelled { background: #f8d7da; color: #721c24; }
                    .status-confirmed { background: #d1ecf1; color: #0c5460; }
                    .status-shipped { background: #cce5ff; color: #004085; }
                </style>
            </head>
            <body>
                <div class="report-header">
                    <h1 class="report-title">📈 SALES ANALYTICS REPORT</h1>
                    <p class="report-meta">Online Shopping System - Admin Dashboard</p>
                    <p class="report-meta">Generated: ${currentDate.toLocaleDateString()} ${currentDate.toLocaleTimeString()}</p>
                    <div class="report-period">
                        ${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()} Analysis
                    </div>
                </div>
                
                <!-- Summary Section -->
                <div class="summary-highlight">
                    <div class="summary-label">Total Revenue This Month</div>
                    <div class="summary-value">
                        RM ${parseFloat(chartData.currentMonthTotal).toFixed(2)}
                    </div>
                    <div class="growth-indicator ${chartData.growthPercentage >= 0 ? 'growth-positive' : 'growth-negative'}">
                        ${chartData.growthPercentage >= 0 ? '↗' : '↘'} 
                        ${Math.abs(parseFloat(chartData.growthPercentage)).toFixed(1)}% 
                        ${chartData.growthPercentage >= 0 ? 'Increase' : 'Decrease'}
                    </div>
                    <p class="report-meta mt-20">Compared to previous month</p>
                </div>
                
                <!-- Quick Stats -->
                <div class="stats-grid-print">
                    <div class="stat-card-print">
                        <div class="stat-label-print">Total Orders</div>
                        <div class="stat-value-print">${chartData.totalOrders}</div>
                    </div>
                    <div class="stat-card-print">
                        <div class="stat-label-print">Current Month Revenue</div>
                        <div class="stat-value-print">RM ${parseFloat(chartData.currentMonthTotal).toFixed(2)}</div>
                    </div>
                    <div class="stat-card-print">
                        <div class="stat-label-print">Growth Rate</div>
                        <div class="stat-value-print ${chartData.growthPercentage >= 0 ? 'growth-positive' : 'growth-negative'}" 
                             style="font-size: 24px; padding: 5px; border-radius: 6px;">
                            ${chartData.growthPercentage >= 0 ? '+' : ''}${parseFloat(chartData.growthPercentage).toFixed(1)}%
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="charts-section-print">
                    <h2 class="section-title">Sales Charts & Analytics</h2>
                    
                    <!-- Revenue Chart -->
                    <div class="chart-container-print">
                        <div class="chart-header-print">
                            <h3 class="chart-title">Monthly Revenue Breakdown</h3>
                            <span class="chart-info">Current Month: ${monthNames[currentDate.getMonth()]}</span>
                        </div>
                        <div class="chart-image-container">
                            ${contentClone.querySelector('.chart-wrapper') ? contentClone.querySelector('.chart-wrapper').innerHTML : 
                              '<p style="text-align: center; color: #666; padding: 40px;">Chart could not be loaded</p>'}
                        </div>
                        <div class="data-table bg-light p-20 rounded">
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Orders</th>
                                        <th>Revenue (RM)</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${chartData.revenueData.map((item, index) => {
                                        const percentage = chartData.currentMonthTotal > 0 ? 
                                            ((item.revenue / chartData.currentMonthTotal) * 100).toFixed(1) : 0;
                                        return `
                                            <tr>
                                                <td><strong>${item.status}</strong></td>
                                                <td>${item.order_count}</td>
                                                <td>RM ${parseFloat(item.revenue).toFixed(2)}</td>
                                                <td>${percentage}%</td>
                                            </tr>
                                        `;
                                    }).join('')}
                                    <tr style="background: #f0f0f0; font-weight: bold;">
                                        <td>TOTAL</td>
                                        <td>${chartData.revenueData.reduce((sum, item) => sum + parseInt(item.order_count), 0)}</td>
                                        <td>RM ${parseFloat(chartData.currentMonthTotal).toFixed(2)}</td>
                                        <td>100%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Status Chart -->
                    <div class="chart-container-print">
                        <div class="chart-header-print">
                            <h3 class="chart-title">Order Status Distribution</h3>
                            <span class="chart-info">All Orders: ${chartData.totalOrders}</span>
                        </div>
                        <div class="chart-image-container">
                            ${contentClone.querySelector('.chart-canvas-container') ? contentClone.querySelector('.chart-canvas-container').innerHTML : 
                              '<p style="text-align: center; color: #666; padding: 40px;">Chart could not be loaded</p>'}
                        </div>
                        <div class="data-table bg-light p-20 rounded">
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Number of Orders</th>
                                        <th>Percentage</th>
                                        <th>Visual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${chartData.statusData.map(item => {
                                        const percentage = chartData.totalOrders > 0 ? 
                                            ((item.count / chartData.totalOrders) * 100).toFixed(1) : 0;
                                        const barWidth = Math.min(percentage * 2, 100);
                                        return `
                                            <tr>
                                                <td>
                                                    <span class="status-badge-print status-${item.status.toLowerCase()}">
                                                        ${item.status}
                                                    </span>
                                                </td>
                                                <td>${item.count}</td>
                                                <td>${percentage}%</td>
                                                <td>
                                                    <div style="background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                                                        <div style="width: ${barWidth}%; height: 100%; 
                                                            background: ${item.status === 'pending' ? '#ffc107' : 
                                                                        item.status === 'delivered' ? '#28a745' : 
                                                                        item.status === 'cancelled' ? '#dc3545' : 
                                                                        item.status === 'confirmed' ? '#17a2b8' : '#5a4bff'};">
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        `;
                                    }).join('')}
                                    <tr style="background: #f0f0f0; font-weight: bold;">
                                        <td>TOTAL</td>
                                        <td>${chartData.totalOrders}</td>
                                        <td>100%</td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Analytics -->
                <div class="data-section">
                    <h2 class="section-title">Additional Analytics</h2>
                    
                    <!-- Top Products -->
                    ${chartData.topProducts && chartData.topProducts.length > 0 ? `
                    <div class="mb-20">
                        <h3 style="font-size: 16px; margin-bottom: 15px; color: #495057;">Top Selling Products</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product Name</th>
                                    <th>Price (RM)</th>
                                    <th>Units Sold</th>
                                    <th>Revenue (RM)</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${chartData.topProducts.map((product, index) => {
                                    const revenue = (product.price || 0) * (product.total_sold || 0);
                                    return `
                                        <tr>
                                            <td>${index + 1}</td>
                                            <td><strong>${product.name || 'N/A'}</strong></td>
                                            <td>${parseFloat(product.price || 0).toFixed(2)}</td>
                                            <td>${product.total_sold || 0}</td>
                                            <td><strong>RM ${parseFloat(revenue).toFixed(2)}</strong></td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                    ` : ''}
                    
                    <!-- Summary Notes -->
                    <div class="bg-light p-20 rounded">
                        <h3 style="font-size: 16px; margin-bottom: 15px; color: #333;">Key Insights & Recommendations</h3>
                        <ul style="padding-left: 20px;">
                            <li>Total revenue for ${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}: <strong>RM ${parseFloat(chartData.currentMonthTotal).toFixed(2)}</strong></li>
                            <li>Month-over-month growth: <strong>${parseFloat(chartData.growthPercentage).toFixed(1)}%</strong></li>
                            <li>Total orders processed: <strong>${chartData.totalOrders}</strong></li>
                            <li>Report generated on: <strong>${currentDate.toLocaleString()}</strong></li>
                        </ul>
                        <p style="margin-top: 15px; font-style: italic; color: #666;">
                            Note: This report provides an overview of sales performance. For detailed transaction data, please refer to the full sales report.
                        </p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="report-footer">
                    <p>--- CONFIDENTIAL - FOR INTERNAL USE ONLY ---</p>
                    <p>Online Shopping System | Admin Dashboard</p>
                    <p>Report ID: ANALYTICS-${currentDate.getFullYear()}${(currentDate.getMonth() + 1).toString().padStart(2, '0')}-${Math.random().toString(36).substr(2, 8).toUpperCase()}</p>
                    <p>Page 1 of 1</p>
                </div>
                
                <!-- Print button for the print window -->
                <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
                    <button onclick="window.print()" 
                            style="background: #5a4bff; color: white; border: none; padding: 10px 20px; 
                                   border-radius: 5px; cursor: pointer; font-weight: bold;">
                        🖨️ Print This Report
                    </button>
                </div>
                
                <script>
                    // Auto-trigger print dialog after page loads
                    window.onload = function() {
                        // Small delay to ensure all images are loaded
                        setTimeout(() => {
                            window.print();
                        }, 500);
                        
                        // Close window after printing (for browsers that support afterprint event)
                        window.onafterprint = function() {
                            setTimeout(() => {
                                window.close();
                            }, 1000);
                        };
                    };
                    
                    // Fallback: Close after 10 seconds if print dialog doesn't trigger
                    setTimeout(() => {
                        if (!document.hidden) {
                            window.close();
                        }
                    }, 10000);
                <\/script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
        
        // Restore button state
        document.querySelector('.btn-green[onclick="printSalesAnalytics()"]').innerHTML = originalButtonText;
        document.querySelector('.btn-green[onclick="printSalesAnalytics()"]').disabled = false;
        
    }).catch(error => {
        console.error('Error generating report:', error);
        alert('Error generating report. Please try again.');
        
        // Restore button state on error
        document.querySelector('.btn-green[onclick="printSalesAnalytics()"]').innerHTML = originalButtonText;
        document.querySelector('.btn-green[onclick="printSalesAnalytics()"]').disabled = false;
    });
}

// Print Detailed Reports (text version)
function printDetailedReports() {
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    const reportDate = new Date().toLocaleString();
    
    // Get the content from your Detailed Report Data section
    const recentOrders = <?php echo json_encode($recentOrders ?? []); ?>;
    const topProducts = <?php echo json_encode($topProducts ?? []); ?>;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Detailed Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { color: #333; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background: #f0f0f0; padding: 10px; text-align: left; }
                td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
                @media print { body { font-size: 12px; } }
            </style>
        </head>
        <body>
            <h1>Detailed Report Data</h1>
            <p><strong>Generated:</strong> ${reportDate}</p>
            
            <h2>Recent Orders</h2>
            <table>
                <tr>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
                ${recentOrders && recentOrders.map(order => `
                    <tr>
                        <td>${order.customer_name || order.username || 'N/A'}</td>
                        <td>${order.order_date ? new Date(order.order_date).toLocaleDateString() : 'N/A'}</td>
                        <td>RM ${parseFloat(order.total_amount || 0).toFixed(2)}</td>
                        <td>${order.status || 'N/A'}</td>
                    </tr>
                `).join('') || '<tr><td colspan="4">No orders found</td></tr>'}
            </table>
            
            <h2>Top Products</h2>
            <table>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Sold</th>
                    <th>Revenue</th>
                </tr>
                ${topProducts && topProducts.map(product => `
                    <tr>
                        <td>${product.name || 'N/A'}</td>
                        <td>RM ${parseFloat(product.price || 0).toFixed(2)}</td>
                        <td>${product.total_sold || 0}</td>
                        <td>RM ${parseFloat((product.price || 0) * (product.total_sold || 0)).toFixed(2)}</td>
                    </tr>
                `).join('') || '<tr><td colspan="4">No products found</td></tr>'}
            </table>
            
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(() => window.close(), 1000);
                };
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Export to CSV function
function exportToCSV() {
    // Get the report data from the table
    const table = document.querySelector('.report-table table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Add headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Add data rows
    table.querySelectorAll('tbody tr').forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(cell => {
            // Remove HTML tags and trim
            const text = cell.textContent.replace(/[\n\r]+|[\s]{2,}/g, ' ').trim();
            // Escape commas and quotes
            rowData.push(`"${text.replace(/"/g, '""')}"`);
        });
        csv.push(rowData.join(','));
    });
    
    // Create and download CSV file
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'report_<?php echo date("Y-m-d_His"); ?>.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Export to PDF function
function exportToPDF() {
    // Create a new window for PDF generation
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    const reportType = '<?php echo $reportType; ?>';
    const reportTitle = '<?php echo $reportTitle; ?>';
    const dateFrom = '<?php echo $dateFrom; ?>';
    const dateTo = '<?php echo $dateTo; ?>';
    
    // Get table data
    const table = document.querySelector('.report-table table');
    const reportData = <?php echo json_encode($reportData); ?>;
    
    // Create PDF content
    const currentDate = new Date().toLocaleString();
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${reportTitle} - PDF Export</title>
            <style>
                body {
                    font-family: 'Helvetica', 'Arial', sans-serif;
                    padding: 20px;
                    color: #333;
                    line-height: 1.5;
                }
                
                .pdf-header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #dc3545;
                }
                
                .pdf-title {
                    color: #333;
                    margin: 0 0 10px 0;
                    font-size: 24px;
                }
                
                .pdf-subtitle {
                    color: #666;
                    font-size: 14px;
                    margin: 5px 0;
                }
                
                .pdf-info {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                
                .pdf-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                
                .pdf-table th {
                    background: #dc3545;
                    color: white;
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                }
                
                .pdf-table td {
                    padding: 10px 12px;
                    border-bottom: 1px solid #eee;
                }
                
                .pdf-table tr:nth-child(even) {
                    background: #f9f9f9;
                }
                
                .pdf-summary {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 20px 0;
                }
                
                .pdf-footer {
                    margin-top: 50px;
                    padding-top: 20px;
                    border-top: 1px dashed #ddd;
                    text-align: center;
                    color: #666;
                    font-size: 12px;
                }
                
                @media print {
                    @page {
                        margin: 0.5in;
                        size: A4;
                    }
                    
                    body {
                        padding: 0;
                        font-size: 11px;
                    }
                    
                    .pdf-title {
                        font-size: 20px;
                    }
                    
                    .pdf-table {
                        page-break-inside: auto;
                    }
                    
                    .pdf-table tr {
                        page-break-inside: avoid;
                        page-break-after: auto;
                    }
                    
                    .pdf-table th, .pdf-table td {
                        padding: 8px 10px;
                    }
                }
                
                .badge {
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: bold;
                }
                
                .badge-success {
                    background: #28a745;
                    color: white;
                }
                
                .badge-warning {
                    background: #ffc107;
                    color: #212529;
                }
                
                .badge-danger {
                    background: #dc3545;
                    color: white;
                }
            </style>
        </head>
        <body>
            <div class="pdf-header">
                <h1 class="pdf-title">${reportTitle}</h1>
                <p class="pdf-subtitle">Period: ${new Date(dateFrom).toLocaleDateString()} - ${new Date(dateTo).toLocaleDateString()}</p>
                <p class="pdf-subtitle">Generated: ${currentDate}</p>
            </div>
            
            <div class="pdf-info">
                <p><strong>Report Type:</strong> ${reportTitle}</p>
                <p><strong>Date Range:</strong> ${dateFrom} to ${dateTo}</p>
                <p><strong>Total Records:</strong> ${reportData.length}</p>
            </div>
            
            <table class="pdf-table">
                <thead>
                    ${table.querySelector('thead').innerHTML}
                </thead>
                <tbody>
                    ${table.querySelector('tbody').innerHTML.replace(/class="[^"]*"/g, '').replace(/style="[^"]*"/g, '')}
                </tbody>
            </table>
            
            <div class="pdf-summary">
                <h3>Report Summary</h3>
                <p><strong>Generated By:</strong> Admin Dashboard</p>
                <p><strong>Total Records:</strong> ${reportData.length}</p>
                <p><strong>Generation Time:</strong> ${currentDate}</p>
                <p><em>This report was automatically generated by the system.</em></p>
            </div>
            
            <div class="pdf-footer">
                <p>--- Confidential Report ---</p>
                <p>Online Shopping System | Admin Dashboard</p>
                <p>Report ID: ${reportType.toUpperCase()}-${new Date().getTime().toString().slice(-8)}</p>
            </div>
            
            <script>
                window.onload = function() {
                    // Auto-print the PDF
                    window.print();
                    
                    // Close the window after printing
                    setTimeout(function() {
                        window.close();
                    }, 1000);
                };
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Additional CSS for printing and styling
const style = document.createElement('style');
style.innerHTML = `
    @media print {
        .admin-nav, .filter-section, .btn-primary, .btn-success, 
        .dashboard-actions, .alert, .btn-sm, .report-header div,
        #profileEditSection, #passwordChangeSection, .btn-outline-primary, .btn-outline-secondary {
            display: none !important;
        }
        .container {
            padding: 0 !important;
        }
        .chart-container, .report-details, .report-results {
            page-break-inside: avoid;
        }
        h1, h2, h3, h4 {
            color: #000 !important;
        }
        .report-summary, .report-stats {
            border: 1px solid #ddd !important;
        }
    }
    
    .stat-box {
        padding: 0.5rem 1rem;
        background: #f8f9fa;
        border-radius: 4px;
        min-width: 150px;
    }
    .stat-box small {
        color: #666;
        font-size: 0.9rem;
    }
    .stat-box h5 {
        margin: 0.25rem 0 0 0;
        color: #333;
    }
    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }
    .badge-danger {
        background-color: #dc3545;
        color: white;
    }
    .badge-success {
        background-color: #28a745;
        color: white;
    }
    .text-muted {
        color: #6c757d;
    }
    
    /* Profile form styling */
    .profile-form, .password-form {
        max-width: 500px;
        margin: 0 auto;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    
    .form-control {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        font-size: 1rem;
        line-height: 1.5;
    }
    
    .form-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .btn {
        display: inline-block;
        font-weight: 400;
        text-align: center;
        vertical-align: middle;
        user-select: none;
        border: 1px solid transparent;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        border-radius: 0.25rem;
        transition: all 0.15s ease-in-out;
        cursor: pointer;
    }
    
    .btn-primary {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
    }
    
    .btn-secondary {
        color: #fff;
        background-color: #6c757d;
        border-color: #6c757d;
    }
    
    .btn-outline-primary {
        color: #007bff;
        background-color: transparent;
        border-color: #007bff;
    }
    
    .btn-outline-secondary {
        color: #6c757d;
        background-color: transparent;
        border-color: #6c757d;
    }
    
    .btn:hover {
        opacity: 0.9;
    }
    
    .alert {
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: 0.25rem;
    }
    
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }
    
    .form-text {
        display: block;
        margin-top: 0.25rem;
    }
`;
document.head.appendChild(style);
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
