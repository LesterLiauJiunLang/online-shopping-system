<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is member
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../lib/base.php';

// Database Connection
$db = db();

// Header
require_once __DIR__ . "/../../includes/header.php";

// Fetch all products
$name = req('name'); 
$category_name = req('category_name'); 
$min_price = req('min_price');
$max_price = req('max_price');

$_category = get_all_categories($db);

// Validate price range
if (!empty($min_price) && !is_numeric($min_price)) {
    $min_price = null;
}
if (!empty($max_price) && !is_numeric($max_price)) {
    $max_price = null;
}

// Get price range for placeholders (min and max available prices)
$price_range = get_price_range(db()); // You'll need to implement this function

$products = get_filtered_products(db(), $name, $category_name, $min_price, $max_price);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Showcase</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }
        
        /* Header Banner */
        .banner {
            width: 100%;
            height: 400px;
            overflow: hidden;
            position: relative;
        }
        
        #banner-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            border-bottom: 5px solid #333;
            cursor: pointer;
        }
        
        /* Banner Text Overlay */
        .banner-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-align: center;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 20px;
            border-radius: 10px;
        }
        
        .banner-text h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        /* Search Form */
        .search-form {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-form .form-group {
            margin-bottom: 15px;
        }
        
        .search-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .search-form input,
        .search-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .search-form .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .search-form .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .search-form button {
            background-color: #333;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            width: 100%;
        }
        
        .search-form button:hover {
            background-color: #e44d26;
        }
        
        .clear-btn {
            display: inline-block;
            margin-top: 10px;
            color: #666;
            text-decoration: none;
            text-align: center;
            width: 100%;
        }
        
        .clear-btn:hover {
            color: #e44d26;
            text-decoration: underline;
        }
        
        /* Products Section */
        .products-section {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
            color: #333;
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        /* Product Card */
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        /* Product Image Container */
        .product-img-container {
            width: 100%;
            height: 250px;
            position: relative;
            overflow: hidden;
            background-color: #f8f9fa;
        }
        
        .product-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-img {
            transform: scale(1.05);
        }
        
        /* Image fallback styles */
        .image-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #666;
        }
        
        .image-fallback .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .image-count {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        
        /* Product Info */
        .product-info {
            padding: 20px;
        }
        
        .product-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #333;
            font-weight: bold;
            height: 40px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #e44d26;
            margin-bottom: 10px;
        }
        
        .product-category {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            padding: 3px 8px;
            background: #f0f0f0;
            border-radius: 12px;
            display: inline-block;
        }
        
        .add-to-cart {
            background-color: #333;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        
        .add-to-cart:hover {
            background-color: #e44d26;
        }
        
        /* Stock status styles */
        .stock-out-of-stock {
            background-color: #ffebee !important;
            color: #c62828 !important;
        }
        
        .stock-low-stock {
            background-color: #fff3e0 !important;
            color: #ef6c00 !important;
        }
        
        .stock-in-stock {
            background-color: #e8f5e9 !important;
            color: #2e7d32 !important;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .banner {
                height: 250px;
            }
            
            .banner-text h1 {
                font-size: 2rem;
            }
            
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }
            
            .search-form .form-row .form-group {
                min-width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .banner {
                height: 200px;
            }
            
            .banner-text h1 {
                font-size: 1.5rem;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
            }
            
            .product-img-container {
                height: 200px;
            }
        }
    </style>
</head>

<body>
    <!-- Banner Section -->
    <header class="banner">
        <div class="banner-text">
            <h1>Welcome to GadgetMart</h1>
            <p>Amazing products at great prices!</p>
        </div>
    </header>
    
    <!-- Products Section -->
    <main class="products-section">
        <h2 class="section-title">Featured Products</h2>

        <!-- Search Form -->
        <form method="GET" class="search-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="search" name="name" placeholder="Search products..." 
                           value="<?php echo htmlspecialchars($name ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="category_name">Category</label>
                    <select name="category_name">
                        <option value="">All Categories</option>
                        <?php foreach ($_category as $id => $category): ?>
                            <option value="<?php echo $id; ?>"
                                <?php echo ($category_name == $id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="min_price">Min Price</label>
                    <input type="number" name="min_price" placeholder="0" 
                           value="<?php echo htmlspecialchars($min_price ?? ''); ?>" 
                           min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="max_price">Max Price</label>
                    <input type="number" name="max_price" placeholder="Any" 
                           value="<?php echo htmlspecialchars($max_price ?? ''); ?>" 
                           min="0" step="0.01">
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit">Search</button>
                <?php if (!empty($name) || !empty($category_name) || !empty($min_price) || !empty($max_price)): ?>
                    <a href="?" class="clear-btn">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Product Grid -->
        <div class="product-grid">
            <?php if (empty($products)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 10px;">
                    <h3>No products found</h3>
                    <p>Try adjusting your search filters</p>
                    <a href="?" style="color: #e44d26;">Clear all filters</a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
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
                            '/' . $first_image['file_path'],
                            '/online-shopping-system/' . $first_image['file_path'],
                            $first_image['file_path'],
                            '../' . $first_image['file_path'],
                            '../../' . $first_image['file_path']
                        ];
                        
                        // Check which path works
                        foreach ($possible_paths as $path) {
                            $path = str_replace('//', '/', $path);
                            $test_path = $_SERVER['DOCUMENT_ROOT'] . $path;
                            
                            if (file_exists($test_path)) {
                                $image_path = $path;
                                break;
                            }
                        }
                        
                        // If still no valid path, try without document root
                        if (!$image_path) {
                            foreach ($possible_paths as $path) {
                                $path = str_replace('//', '/', $path);
                                $relative_path = __DIR__ . '/../..' . $path;
                                if (file_exists($relative_path)) {
                                    $image_path = $path;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Fallback to product's image_url if no product_image records
                    if (!$image_path && !empty($product['image_url'])) {
                        $image_path = $product['image_url'];
                    }
                    ?>
                    
                    <div class="product-card" onclick="window.location.href='products_detail.php?id=<?php echo $product['product_id']; ?>'">
                        <div class="product-img-container">
                            <?php if ($image_path): ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="product-img"
                                     onerror="handleImageError(this)">
                                
                                <div class="image-fallback" style="display: none;">
                                    <div class="icon">📷</div>
                                    <small>Image not found</small>
                                </div>
                                
                                <?php if ($image_count > 1): ?>
                                    <span class="image-count">+<?php echo $image_count - 1; ?> more</span>
                                <?php endif; ?>
                                
                            <?php elseif ($product['category_name']): ?>
                                <div class="image-fallback">
                                    <div class="icon">
                                        <?php 
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
                                <div class="image-fallback">
                                    <div class="icon">🛒</div>
                                    <small>No image</small>
                                </div>
                            <?php endif; ?>
                        </div>
                                
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            
                            <div class="product-price">
                                RM <?php echo number_format($product['price'], 2); ?>
                            </div>
                            
                            <?php if ($product['category_name']): ?>
                                <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                            <?php endif; ?>
                            
                            <!-- Stock status -->
                            <div style="margin: 10px 0; font-size: 0.9rem;">
                                <?php
                                $stock_class = get_stock_class($product['stock_quantity']);
                                $stock_text = get_stock_text($product['stock_quantity']);
                                ?>
                                <span class="stock-<?php echo $stock_class; ?>" 
                                      style="padding: 3px 8px; border-radius: 12px;">
                                    <?php echo $stock_text; ?>
                                </span>
                            </div>
                            
                            <form method="post" action="products_detail.php" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $product['product_id']; ?>">
                                <input type="hidden" name="unit" value="1">
                                <button type="submit" class="add-to-cart" onclick="event.stopPropagation();">
                                    Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
    // Add to Cart functionality
    function addToCart(productId) {
        // Show loading state
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Adding...';
        button.disabled = true;
        
        // Make AJAX call to add to cart
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'product_id=' + productId + '&quantity=1&csrf_token=' + '<?php echo csrf_token(); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update cart count in header
                const cartCount = document.querySelector('.cart-count');
                if (cartCount) {
                    cartCount.textContent = data.cart_count;
                }
                
                // Show success message
                alert('Product added to cart!');
            } else {
                alert('Failed to add product to cart: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        })
        .finally(() => {
            // Reset button
            button.textContent = originalText;
            button.disabled = false;
        });
    }
    
    // Handle image errors
    function handleImageError(img) {
        img.style.display = 'none';
        const fallback = img.nextElementSibling;
        if (fallback && fallback.classList.contains('image-fallback')) {
            fallback.style.display = 'flex';
        }
    }
    </script>
</body>
</html>