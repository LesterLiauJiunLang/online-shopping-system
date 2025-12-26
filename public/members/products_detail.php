<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../app/config/database.php';


// ---------------------------------------------------------

if (is_post()) {
    $product_id = req('id');
    $quantity   = (int) req('unit');

    if ($quantity <= 0) {
        $stmt = db()->prepare("
            DELETE FROM shopping_cart
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
    } else {
        $stmt = db()->prepare("
            INSERT INTO shopping_cart (user_id, product_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
    }

    redirect("products_detail.php?id=$product_id");
}

$id   = req('id');
$stmt = db()->prepare("
     SELECT 
        p.*,
        COALESCE(pi.file_path, p.image_url) AS image_url
    FROM products p
    LEFT JOIN product_image pi
        ON pi.product_id = p.product_id
        AND pi.sort_order = 0
    WHERE p.product_id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = db()->prepare("
    SELECT quantity
    FROM shopping_cart
    WHERE user_id = ? AND product_id = ?
");
$stmt->execute([$_SESSION['user_id'], $product['product_id']]);
$cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

$unit = $cart_item['quantity'] ?? 0;




if (!$product) {
    header("Location: products.php");
    exit();
}

$cart = get_cart();
$unit = $cart[$product['product_id']] ?? 0;
// quantity options (1–10)
$_units = array_combine(range(1, 10), range(1, 10));

// ---------------------------------------------------------

// Image handling
$image_url = $product['image_url'] ?? '/assets/img/no-image.png';
if (!preg_match('/^https?:\/\//i', $image_url)) {
    $image_url = '/' . ltrim($image_url, '/');
    $image_url = str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $image_url);
}


require_once __DIR__ . "/../../includes/header.php";
?>

    <link rel="stylesheet" href="../../assets/css/product.css">


<div class="detail-container">
    <img src="<?= htmlspecialchars($image_url) ?>" class="detail-img">

    <div class="detail-info">
        <h2><?= htmlspecialchars($product['name']) ?></h2>
        <p class="detail-price">RM <?= number_format($product['price'], 2) ?></p>
    <form method="post">
        <input type="hidden" name="id" value="<?= $product['product_id'] ?>">

        <?php if (!empty($product['description'])): ?>
            <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        <?php endif; ?>

        <label>
            Quantity:
            <?php html_select('unit', $_units); ?>
        </label>
            <button type="submit" class="add-cart-btn">Add to Cart</button>
        </form>

        <p>
            <button data-get="products.php" class="back-btn">Back to Products</button>
        </p>
    </div>
</div>

<script>
document.querySelectorAll('[data-get]').forEach(btn => {
    btn.addEventListener('click', () => {
        window.location.href = btn.dataset.get;
    });
});
</script>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
