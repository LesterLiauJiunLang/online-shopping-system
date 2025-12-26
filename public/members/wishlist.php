<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';

$stmt = db()->prepare("
    SELECT w.product_id, p.name, p.price, p.image_url
    FROM wishlist w
    JOIN products p ON p.product_id = w.product_id
    WHERE w.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/wishlist.css">

<div class="cart-container">
    <h2 class="checkout-title">My Wishlist</h2>

    <?php if (!$wishlist_items): ?>
        <p>Your wishlist is empty.</p>
        <a href="products.php" class="back-btn">Continue Shopping</a>
    <?php else: ?>
        <div class="cart-items">
            <?php foreach ($wishlist_items as $item): ?>
                <div class="cart-item">
                    <img src="<?= encode($item['image_url']) ?>" alt="<?= encode($item['name']) ?>" class="cart-item-img">

                    <div class="cart-item-info">
                        <h4><?= encode($item['name']) ?></h4>
                        <p>RM <?= number_format($item['price'], 2) ?></p>
                        
                        <div class="cart-actions">
                            <a href="wishlist_remove.php?product_id=<?= $item['product_id'] ?>" class="remove-btn">Remove</a>
                            <a href="products_detail.php?id=<?= $item['product_id'] ?>" class="add-cart-btn">View Product</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <a href="products.php" class="back-btn">Continue Shopping</a>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
