<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../lib/base.php';
$db = db();

// ---------------------------------------------------------

if (is_post()) {
    $product_id = req('id');
    $quantity   = (int) req('unit');

    if ($quantity <= 0) {
        $stmt = $db->prepare("
            DELETE FROM shopping_cart
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO shopping_cart (user_id, product_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
    }

    redirect("products_detail.php?id=$product_id");
}

$id   = req('id');
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN category c ON p.category_id = c.category_id
    WHERE p.product_id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit();
}

// Get product images
$image_stmt = $db->prepare("SELECT file_path FROM product_image WHERE product_id = ? ORDER BY sort_order ASC");
$image_stmt->execute([$id]);
$product_images = $image_stmt->fetchAll();

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

// Fallback to product's image_url or placeholder
if (!$image_path) {
    if (!empty($product['image_url'])) {
        $image_path = $product['image_url'];
    } else {
        // Use a category-specific icon as fallback
        $image_path = null;
    }
}

$stmt = $db->prepare("
    SELECT quantity
    FROM shopping_cart
    WHERE user_id = ? AND product_id = ?
");
$stmt->execute([$_SESSION['user_id'], $product['product_id']]);
$cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

$unit = $cart_item['quantity'] ?? 0;

// quantity options (1–10)
$_units = array_combine(range(1, 10), range(1, 10));

require_once __DIR__ . "/../../includes/header.php";
?>

<style>
    .detail-container {
        max-width: 900px;
        margin: 40px auto;
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 30px;
    }

    .detail-img {
        width: 300px;
        height: 300px;
        object-fit: contain;
        border: 1px solid #ddd;
        background-color: #f8f9fa;
    }
    
    .image-fallback {
        width: 300px;
        height: 300px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #666;
        border: 1px solid #ddd;
        background-color: #f8f9fa;
    }
    
    .image-fallback .icon {
        font-size: 72px;
        margin-bottom: 10px;
    }

    .detail-info h2 {
        margin-bottom: 15px;
    }

    .detail-price {
        font-size: 1.5rem;
        color: #e44d26;
        margin-bottom: 15px;
    }
</style>

<div class="detail-container">
    <?php if ($image_path): ?>
        <img src="<?php echo htmlspecialchars($image_path); ?>" 
             class="detail-img"
             alt="<?php echo htmlspecialchars($product['name']); ?>"
             onerror="this.style.display='none'; document.getElementById('image-fallback').style.display='flex';">
        
        <div id="image-fallback" class="image-fallback" style="display: none;">
            <div class="icon">
                <?php if ($product['category_name']): ?>
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
                <?php else: ?>
                    🛒
                <?php endif; ?>
            </div>
            <small>Image not found</small>
        </div>
    <?php else: ?>
        <div class="image-fallback">
            <div class="icon">
                <?php if ($product['category_name']): ?>
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
                <?php else: ?>
                    🛒
                <?php endif; ?>
            </div>
            <small>No image available</small>
        </div>
    <?php endif; ?>

    <div class="detail-info">
        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
        <p class="detail-price">RM <?php echo number_format($product['price'], 2); ?></p>
        
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $product['product_id']; ?>">

            <?php if ($unit == 0): ?>
                <input type="hidden" name="unit" value="1">
                <button type="submit">Add to Cart</button>
            <?php else: ?>
                <label>
                    Quantity:
                    <select name="unit">
                        <?php foreach ($_units as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $unit == $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Update Cart</button>
            <?php endif; ?>
        </form>

        <?php if (!empty($product['description'])): ?>
            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
        <?php endif; ?>

        <?php if ($product['category_name']): ?>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?></p>
        <?php endif; ?>
        
        <?php if (isset($product['stock_quantity'])): ?>
            <p>
                <strong>Stock:</strong> 
                <span style="color: <?php echo $product['stock_quantity'] > 0 ? 'green' : 'red'; ?>;">
                    <?php echo $product['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                </span>
            </p>
        <?php endif; ?>

        <p>
            <button data-get="products.php">Back to Products</button>
        </p>
    </div>
</div>

<script>
// Handle image errors
function handleImageError(img) {
    img.style.display = 'none';
    const fallback = img.nextElementSibling;
    if (fallback && fallback.classList.contains('image-fallback')) {
        fallback.style.display = 'flex';
    }
}

// Back button functionality
document.querySelectorAll('[data-get]').forEach(btn => {
    btn.addEventListener('click', () => {
        window.location.href = btn.dataset.get;
    });
});
</script>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>