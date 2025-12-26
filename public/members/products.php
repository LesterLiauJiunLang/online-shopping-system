<?php
require __DIR__ . '/../../lib/base.php';

// Check if user is logged in and is member
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
//Header================================================================
require_once __DIR__ . "/../../includes/header.php";
//Fetch all products====================================================

$name = req('name'); 
$category_id = req('category_name'); 
$min_price = req('min_price');
$max_price = req('max_price');

// Validate price range
if (!empty($min_price) && !is_numeric($min_price)) {
    $min_price = null;
}
if (!empty($max_price) && !is_numeric($max_price)) {
    $max_price = null;
}

$_category = get_all_categories(db());

// Get price range for placeholders (min and max available prices)
$price_range = get_price_range(db()); // You'll need to implement this function

$products = get_filtered_products(db(), $name, $category_id, $min_price, $max_price);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/product.css">
    <title>Product Showcase</title>
    
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

       <form class="search-form">
        <div class="form-group">
          <label for="name">Product Name</label>
             <?= html_search('name') ?>
        </div>
           <div class="form-group">
            <label for="category_name">Category</label> 
             <?= html_select('category_name', $_category, 'All') ?>
           </div>

           <div class="form-group">
            <label for="min_price">Min Price</label>
            <input type="number" name="min_price" placeholder="0" value="<?= htmlspecialchars($min_price ?? '') ?>" min="0" step="0.01">
           </div>

           <div class="form-group">
            <label for="max_price">Max Price</label>
            <input type="number" name="max_price" placeholder="Any" value="<?= htmlspecialchars($max_price ?? '') ?>" min="0" step="0.01">
           </div>


             <div class="form-group">
            <button type="submit">Search</button>
            <?php if (!empty($name) || !empty($category_name) || !empty($min_price) || !empty($max_price)): ?>
                    <a href="?" class="clear-btn" style="display: block; margin-top: 10px; text-align: center;">Clear Filters</a>
            <?php endif; ?>
        </div>
        </form>

        <div class="product-grid">
    <?php if (empty($products)): ?>
        <p>No products found.</p>
    <?php else: ?>
        <?php foreach ($products as $product): ?>
            <?php
            
                $image_url = !empty($product['image_url'])
                    ? $product['image_url']
                    : '/assets/img/no-image.png';
            ?>

            <div class="product-card">
                <div class="product-img-wrapper">
                 <img src="<?= htmlspecialchars($image_url) ?>" class="product-img" data-get="products_detail.php?id=<?= $product['product_id'] ?>">
                 <button class="wishlist-btn" 
                  data-product-id="<?= $product['product_id'] ?>" 
                  aria-label="Add to Wishlist">
                     ♥
                 </button>
            </div>

                <div class="product-info">
                    <?= htmlspecialchars($product['name']) ?> |
                    RM <?= number_format($product['price'], 2) ?>
                </div>

                <button class="add-to-cart" data-product-id="<?= $product['product_id'] ?>">Add to Cart</button>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

    </main>
    
    <script>
        document.querySelectorAll('[data-get]').forEach(el => {
    el.addEventListener('click', () => {
        window.location.href = el.dataset.get;
    });
});

    document.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation(); // prevent image redirect
        const productId = this.dataset.productId;
        const button = this;

        button.disabled = true;

        fetch('cart_add.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ product_id: productId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                button.textContent = 'Added!';
                setTimeout(() => button.textContent = 'Add to Cart', 1000);
            } else {
                alert(data.message || 'Failed to add to cart');
            }
            button.disabled = false;
        })
        .catch(() => {
            alert('Network error');
            button.disabled = false;
        });
    });
});


    document.querySelectorAll('.wishlist-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation(); // prevent redirect if inside image
        const productId = this.dataset.productId;
        const button = this;

        // Toggle loading state
        button.disabled = true;

        // AJAX call to add/remove wishlist
        fetch('wishlist_add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ product_id: productId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Toggle heart fill
                button.classList.toggle('active', data.added);
            } else {
                alert('Failed to update wishlist');
            }
            button.disabled = false;
        })
        .catch(() => {
            alert('Network error');
            button.disabled = false;
        });
    });
});

    </script>
</body>
</html>