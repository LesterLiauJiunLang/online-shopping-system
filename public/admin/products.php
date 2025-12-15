<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';

$db = Database::getConnection();
$productModel = new Product($db);

// Handle product deletion
if (isset($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    $stmt = $db->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    $_SESSION['success_message'] = "Product deleted successfully";
    header("Location: products.php");
    exit();
}

// Get all products with category
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN category c ON p.category_id = c.category_id 
    ORDER BY p.created_at DESC
");
$stmt->execute();
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT * FROM category")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">📋 Manage Products</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
            </div>
        </div>
        
        <div class="admin-nav">
            <a href="products.php" class="active">📋 Products</a>
            <a href="product_add.php">➕ Add Product</a>
            <a href="orders.php">📦 Orders</a>
            <a href="../dashboard.php">🏠 Dashboard</a>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="search-section">
            <form method="GET" class="search-form">
                <div class="search-group">
                    <label>Search Products</label>
                    <input type="text" name="search" placeholder="Search by name or description...">
                </div>
                
                <div class="search-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-group">
                    <label>Price Range</label>
                    <select name="price_range">
                        <option value="">All Prices</option>
                        <option value="0-50">Under RM50</option>
                        <option value="50-100">RM50 - RM100</option>
                        <option value="100-200">RM100 - RM200</option>
                        <option value="200+">Above RM200</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-search">Search</button>
                <a href="products.php" class="btn-clear">Clear</a>
            </form>
        </div>
        
        <a href="product_add.php" class="add-product-btn">➕ Add New Product</a>
        
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
                <h3>No Products Found</h3>
                <p>There are no products in the system yet. <a href="product_add.php">Add your first product</a>.</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($product['category_name']): ?>
                            <div style="text-align: center;">
                                <div style="font-size: 48px;">📱</div>
                                <small><?php echo htmlspecialchars($product['category_name']); ?></small>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 48px;">🛒</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        
                        <p class="product-description">
                            <?php echo htmlspecialchars(substr($product['description'] ?? 'No description', 0, 100)); ?>...
                        </p>
                        
                        <div class="product-meta">
                            <div class="product-price">RM <?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-stock <?php echo $product['stock_quantity'] > 0 ? 'stock-in' : 'stock-out'; ?>">
                                <?php echo $product['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                            </div>
                        </div>
                        
                        <?php if ($product['category_name']): ?>
                            <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                        <?php endif; ?>
                        
                        <div class="product-actions">
                            <a href="product_add.php?edit=<?php echo $product['product_id']; ?>" class="btn-edit">Edit</a>
                            <a href="products.php?delete=<?php echo $product['product_id']; ?>" class="btn-delete" onclick="return confirm('Delete this product?')">Delete</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>