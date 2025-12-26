<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';

if (is_post()) {
    $product_id = req('product_id');
    $quantity   = (int) req('quantity');

    if ($quantity <= 0) {
        $stmt = db()->prepare("
            DELETE FROM shopping_cart
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
    } else {
        $stmt = db()->prepare("
            UPDATE shopping_cart
            SET quantity = ?
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$quantity, $_SESSION['user_id'], $product_id]);
    }

    redirect('cart.php');
}

$stmt = db()->prepare("
    SELECT 
        sc.product_id,
        sc.quantity,
        p.name,
        p.price,
        p.image_url
    FROM shopping_cart sc
    JOIN products p ON p.product_id = sc.product_id
    WHERE sc.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . "/../../includes/header.php";
?>
<link rel="stylesheet" href="../../assets/css/product.css">
<link rel="stylesheet" href="../../assets/css/cart.css">
<?php

if (!$items) {
    ?>
    <div class="cart-container">
        <div class="empty-cart">
            <div class="empty-cart-icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Looks like you haven't added any products to your cart yet.</p>
            <a href="products.php" class="shop-now-btn">Start Shopping</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    exit();
}

$_qty = array_combine(range(1, 10), range(1, 10));
$total = 0;
?>

    

<div class="cart-container">
    <h2 class="cart-title">Your Shopping Cart</h2>

    <table class="cart-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                <tr>
                    <td class="product-cell" data-label="Product">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= encode($item['image_url']) ?>" alt="<?= encode($item['name']) ?>" class="product-img-small">
                        <?php endif; ?>
                        <span class="product-name"><?= encode($item['name']) ?></span>
                    </td>
                    <td class="price-cell" data-label="Price">
                        <span class="price-value">RM <?= number_format($item['price'], 2) ?></span>
                    </td>
                    <td class="quantity-cell" data-label="Quantity">
                        <form method="post" class="quantity-form">
                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                            <select name="quantity" class="quantity-select">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i == $item['quantity'] ? 'selected' : '' ?>>
                                        <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="update-btn">Update</button>
                        </form>
                    </td>
                    <td class="subtotal-cell" data-label="Subtotal">
                        <span class="subtotal-value">RM <?= number_format($subtotal, 2) ?></span>
                    </td>
                    <td data-label="Action">
                        <form method="post" class="remove-form">
                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                            <input type="hidden" name="quantity" value="0">
                            <button type="submit" class="remove-btn">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <tr class="total-row">
                <td colspan="3" class="total-label">Total</td>
                <td colspan="2" class="total-value">RM <?= number_format($total, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="cart-actions">
        <a href="products.php" class="continue-shopping-btn">← Continue Shopping</a>
        <a href="checkout.php" class="checkout-btn">Proceed to Checkout →</a>
    </div>
</div>



<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
