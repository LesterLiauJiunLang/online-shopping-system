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
    SELECT p.*
    FROM products p
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

$image_url = filter_var($product['image_url'] ?? '', FILTER_VALIDATE_URL)
    ? $product['image_url']
    : 'https://via.placeholder.com/300x300?text=No+Image';

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
        object-fit: cover;
        border: 1px solid #ddd;
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
    <img src="<?= htmlspecialchars($image_url) ?>" class="detail-img">

    <div class="detail-info">
        <h2><?= htmlspecialchars($product['name']) ?></h2>
        <p class="detail-price">RM <?= number_format($product['price'], 2) ?></p>
    <form method="post">
        <input type="hidden" name="id" value="<?= $product['product_id'] ?>">

    <?php if ($unit == 0): ?>
         <input type="hidden" name="unit" value="1">
           <button type="submit">Add to Cart</button>
    <?php else: ?>
        <label>
            Quantity:
            <?php html_select('unit', $_units, null); ?>
        </label>
            <button type="submit">Add to Cart</button>
    <?php endif; ?>
       
        </form>

        <?php if (!empty($product['description'])): ?>
            <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        <?php endif; ?>

        <p>
            <button data-get="products.php">Back to Products</button>
        </p>
    </div>
</div>

<script>
document.querySelectorAll('[data-get]').forEach(btn => {
    btn.addEventListener('click', () => {
        window.location.href = btn.dataset.get;
    });
});

/* Back button */
document.querySelectorAll('[data-get]').forEach(btn => {
    btn.addEventListener('click', () => {
        window.location.href = btn.dataset.get;
    });
});


</script>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
