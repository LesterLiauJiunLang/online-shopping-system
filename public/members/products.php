<?php
require __DIR__ . '/../../app/models/_base.php';

// Check if user is logged in and is member
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
//Database Connection===================================================
$db = Database::getConnection();
//Header================================================================
require_once __DIR__ . "/../../includes/header.php";
//Fetch all products====================================================

$name = req('name'); 
$category_name = req('category_name'); 


$_category = get_all_categories($db);


$products = get_filtered_products($db, $name, $category_name);

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
            object-fit: cover; /* Ensures image covers the area without distortion */
            object-position: center;
            border-bottom: 5px solid #333;
            cursor: pointer;
        }
        
        /* Banner Text Overlay (Optional) */
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
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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
        
        .product-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #333;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #e44d26;
            margin-bottom: 10px;
        }
        
        .add-to-cart {
            background-color: #333;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        
        .add-to-cart:hover {
            background-color: #e44d26;
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

       <form class="search-form">
             <?= html_search('name') ?>
             <?= html_select('category_name', $_category, 'All') ?>
             <button>Search</button>
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
                <img src="<?= htmlspecialchars($image_url) ?>"
                     class="product-img"
                     data-get="products_detail.php?id=<?= $product['product_id'] ?>">

                <div class="product-info">
                    <?= htmlspecialchars($product['name']) ?> |
                    RM <?= number_format($product['price'], 2) ?>
                </div>
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

    // Add to Cart functionality
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.disabled) return;
            
            const productId = this.getAttribute('data-product-id');
            const buttonText = this.textContent;
            
            // Show loading state
            this.textContent = 'Adding...';
            this.disabled = true;
            
            // Simulate API call (replace with actual AJAX call)
            setTimeout(() => {
                // In a real implementation, you would make an AJAX call to add to cart
                // For example:
                /*
                fetch('/add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ product_id: productId })
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
                        alert('Failed to add product to cart.');
                    }
                    this.textContent = buttonText;
                    this.disabled = false;
                });
                */
                
                // For now, just show an alert
                alert('Product added to cart!');
                
                // Reset button
                this.textContent = buttonText;
                this.disabled = false;
                
            }, 500);
        });
    });
    </script>
</body>
</html>