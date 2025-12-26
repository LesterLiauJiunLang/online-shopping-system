<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../lib/base.php';
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';

$db = db();
$productModel = new Product($db);

if (isset($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    $stmt = $db->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    $_SESSION['success_message'] = "Product deleted successfully";
    header("Location: products.php");
    exit();
}

$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$price_range = $_GET['price_range'] ?? '';

$categories = $db->query("SELECT * FROM category")->fetchAll();

$sql = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN category c ON p.category_id = c.category_id 
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($category_filter)) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($price_range)) {
    switch ($price_range) {
        case '0-1000':
            $sql .= " AND p.price BETWEEN 0 AND 1000";
            break;
        case '1000-2000':
            $sql .= " AND p.price BETWEEN 1000 AND 2000";
            break;
        case '2000-3000':
            $sql .= " AND p.price BETWEEN 2000 AND 3000";
            break;
        case '3000-4000':
            $sql .= " AND p.price BETWEEN 3000 AND 4000";
            break;
        case '4000-5000':
            $sql .= " AND p.price BETWEEN 4000 AND 5000";
            break;
        case '5000+':
            $sql .= " AND p.price > 5000";
            break;
    }
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
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
    <script src="../assets/js/sync_categories.js"></script>
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
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="products.php" class="active">📋 Products</a>
            <a href="product_add.php">➕ Add Product</a>
            <a href="orders.php">📦 Orders</a>
            <a href="categories.php">🏷️ Categories</a>
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
                    <input type="text" name="search" placeholder="Search by name or description..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="search-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-group">
                    <label>Price Range</label>
                    <select name="price_range">
                        <option value="">All Prices</option>
                        <option value="0-1000" <?php echo $price_range == '0-1000' ? 'selected' : ''; ?>>Under RM1000</option>
                        <option value="1000-2000" <?php echo $price_range == '1000-2000' ? 'selected' : ''; ?>>RM1000-RM2000</option>
                        <option value="2000-3000" <?php echo $price_range == '2000-3000' ? 'selected' : ''; ?>>RM2000-RM3000</option>
                        <option value="3000-4000" <?php echo $price_range == '3000-4000' ? 'selected' : ''; ?>>RM3000-RM4000</option>
                        <option value="4000-5000" <?php echo $price_range == '4000-5000' ? 'selected' : ''; ?>>RM4000-RM5000</option>
                        <option value="5000+" <?php echo $price_range == '5000+' ? 'selected' : ''; ?>>Above RM5000</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-search">Search</button>
                <a href="products.php" class="btn-clear">Clear</a>
            </form>
        </div>
        
        <?php if ($search || $category_filter || $price_range): ?>
            <div class="results-info">
                <p>
                    Showing <?php echo count($products); ?> product(s)
                    <?php if ($search): ?>
                        matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    <?php endif; ?>
                    <?php if ($category_filter): ?>
                        <?php 
                        $category_name = '';
                        foreach ($categories as $cat) {
                            if ($cat['category_id'] == $category_filter) {
                                $category_name = $cat['name'];
                                break;
                            }
                        }
                        if ($category_name): ?>
                            in category: <strong><?php echo htmlspecialchars($category_name); ?></strong>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($price_range): ?>
                        with price: <strong>
                            <?php 
                            switch ($price_range) {
                                case '0-1000': echo 'Under RM1000'; break;
                                case '1000-2000': echo 'RM1000 - RM2000'; break;
                                case '2000-2000': echo 'RM2000 - RM3000'; break;
                                case '3000-2000': echo 'RM3000 - RM4000'; break;
                                case '4000-2000': echo 'RM4000 - RM5000'; break;
                                case '5000+': echo 'Above RM5000'; break;
                            }
                            ?>
                        </strong>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
                <h3>No Products Found</h3>
                <p>
                    <?php if ($search || $category_filter || $price_range): ?>
                        No products match your search criteria. <a href="products.php">Clear filters</a> to see all products.
                    <?php else: ?>
                        There are no products in the system yet. <a href="product_add.php">Add your first product</a>.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php 
                // Add sequential numbering if needed
                $counter = 1;
                foreach ($products as $product): 
                ?>
                <div class="product-card">
                    <div class="product-card">
    <div class="product-image <?php echo ($image_count > 1) ? 'has-multiple-images' : ''; ?>">
    <?php 
// Get product images
$image_stmt = $db->prepare("SELECT file_path FROM product_image WHERE product_id = ? ORDER BY sort_order ASC");
$image_stmt->execute([$product['product_id']]);
$product_images = $image_stmt->fetchAll();
$image_count = count($product_images);

// Get the first image
$first_image = $product_images[0] ?? null;
$image_path = null;

if ($first_image && !empty($first_image['file_path'])) {
    // Try multiple possible paths
    $possible_paths = [
        '/' . $first_image['file_path'], // Add leading slash
        '/online-shopping-system/' . $first_image['file_path'], // Full path
        $first_image['file_path'], // Just the relative path
        base($first_image['file_path']) // Use base() function
    ];
    
    // Check which path works
    foreach ($possible_paths as $path) {
        // Remove any double slashes
        $path = str_replace('//', '/', $path);
        $test_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        
        if (file_exists($test_path)) {
            $image_path = $path;
            break;
        }
    }
    
    // Debug logging
    error_log("Product ID: {$product['product_id']}");
    error_log("Stored path: {$first_image['file_path']}");
    error_log("Using path: " . ($image_path ?: 'NOT FOUND'));
}
    
    if ($image_path): 
    ?>
        <img src="<?php echo $image_path; ?>" 
             alt="<?php echo htmlspecialchars($product['name']); ?>"
             class="product-thumbnail"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        
        <div class="fallback-icon" style="display: none;">
            <div style="font-size: 48px;">📷</div>
            <small>Image not found</small>
        </div>
        
        <?php if ($image_count > 1): ?>
            <span class="image-count">+<?php echo $image_count - 1; ?> more</span>
        <?php endif; ?>
        
    <?php elseif ($product['category_name']): ?>
        <div class="category-icon">
            <div style="font-size: 48px;">
                <?php 
                // Category-specific icons
                switch(strtolower($product['category_name'])) {
                    case 'electronics': echo '📱'; break;
                    case 'clothing': echo '👕'; break;
                    case 'books': echo '📚'; break;
                    case 'home & kitchen': echo '🏠'; break;
                    case 'sports & outdoors': echo '⚽'; break;
                    default: echo '🛒';
                }
                ?>
            </div>
            <small><?php echo htmlspecialchars($product['category_name']); ?></small>
        </div>
    <?php else: ?>
        <div class="default-icon">
            <div style="font-size: 48px;">🛒</div>
            <small>No image</small>
        </div>
    <?php endif; ?>
</div>
    
    <div class="product-info">
        <!-- Rest of your product info -->
    </div>
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
                <?php 
                $counter++;
                endforeach; 
                ?>
            </div>
        <?php endif; ?>
    </div>
    </body>
<script>
// Check for category updates when returning from product_add.php
document.addEventListener('DOMContentLoaded', function() {
    // Check if we need to refresh categories (set by product_add.php)
    if (sessionStorage.getItem('categories_refresh') === 'true') {
        // Clear the flag
        sessionStorage.removeItem('categories_refresh');
        
        // Reload the page to get updated categories
        // Or use AJAX to refresh just the dropdown
        location.reload();
    }
    
    // Listen for messages from other tabs (if using BroadcastChannel)
    if (typeof BroadcastChannel !== 'undefined') {
        const channel = new BroadcastChannel('category_updates');
        channel.addEventListener('message', (event) => {
            if (event.data.type === 'categories_updated') {
                // Refresh the page or update dropdown via AJAX
                location.reload();
            }
        });
    }
});
</script>
</body>
</html>