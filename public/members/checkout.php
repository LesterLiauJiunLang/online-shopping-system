<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';

// Get cart items with product details
$stmt = db()->prepare("
    SELECT sc.product_id, sc.quantity, p.name, p.price, p.image_url
    FROM shopping_cart sc
    JOIN products p ON p.product_id = sc.product_id
    WHERE sc.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$cart_items) {
    redirect('cart.php');
}

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

if (is_get()) {
    require_once __DIR__ . "/../../includes/header.php";
?>
    
    <link rel="stylesheet" href="../../assets/css/cart.css">

<div class="checkout-container">
    <h2 class="checkout-title">Checkout</h2>
    
    <!-- Checkout Steps -->
    <div class="checkout-steps">
        <div class="checkout-step completed">
            <div class="step-number">1</div>
            <div class="step-label">Cart</div>
        </div>
        <div class="checkout-step active">
            <div class="step-number">2</div>
            <div class="step-label">Checkout</div>
        </div>
        <div class="checkout-step">
            <div class="step-number">3</div>
            <div class="step-label">Complete</div>
        </div>
    </div>
    
    <!-- Order Summary -->
    <div class="order-summary">
        <h3>Order Summary</h3>
        
        <table class="order-items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                    <?php $subtotal = $item['price'] * $item['quantity']; ?>
                    <tr>
                        <td class="order-item-name"><?= encode($item['name']) ?></td>
                        <td class="order-item-price">RM <?= number_format($item['price'], 2) ?></td>
                        <td class="order-item-quantity"><?= $item['quantity'] ?></td>
                        <td class="order-item-subtotal">RM <?= number_format($subtotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="summary-item">
            <span class="summary-label">Subtotal</span>
            <span class="summary-value">RM <?= number_format($total, 2) ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Shipping</span>
            <span class="summary-value">RM 0.00</span>
        </div>
        <div class="summary-item summary-total">
            <span class="summary-label">Total</span>
            <span class="summary-value">RM <?= number_format($total, 2) ?></span>
        </div>
    </div>
    
    <!-- Checkout Form -->
    <form method="post" class="checkout-form">
        <h3>Shipping & Payment Details</h3>
        
        <div class="form-group">
            <label for="shipping_address">Shipping Address</label>
            <textarea name="shipping_address" id="shipping_address" required placeholder="Enter your complete shipping address"></textarea>
        </div>
        
        <div class="form-group">
            <label for="payment_method">Payment Method</label>
            <select name="payment_method" id="payment_method" required>
                <option value="">-- Select Payment Method --</option>
                <option value="Card">Credit/Debit Card</option>
                <option value="Online Banking">Online Banking</option>
                <option value="Cash on Delivery">Cash on Delivery</option>
            </select>
        </div>
        
        <div class="checkout-actions">
            <a href="cart.php" class="cancel-btn">← Back to Cart</a>
            <button type="submit" class="place-order-btn">Place Order →</button>
        </div>
    </form>
</div>

<?php
    require_once __DIR__ . "/../../includes/footer.php";
    exit();
}

if (is_post()) {
    $shipping = req('shipping_address');
    $payment  = req('payment_method');

    try {
        db()->beginTransaction();
        
        // Check stock availability first
        foreach ($cart_items as $item) {
            $stmt = db()->prepare("
                UPDATE products
                SET stock_quantity = stock_quantity - ?
                WHERE product_id = ? AND stock_quantity >= ?
            ");
            $stmt->execute([
                $item['quantity'],
                $item['product_id'],
                $item['quantity']
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Insufficient stock for product: " . $item['name']);
            }
        }
        
        // Create order
        $stmt = db()->prepare("
            INSERT INTO orders (
                user_id,
                order_date,
                total_amount,
                status,
                shipping_address,
                payment_method,
                payment_status
            )
            VALUES (?, NOW(), ?, 'Completed', ?, ?, 'Paid')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $total,
            $shipping,
            $payment
        ]);

        $order_id = db()->lastInsertId();

        // Insert order items
        foreach ($cart_items as $item) {
            $subtotal = $item['price'] * $item['quantity'];

            $stmt = db()->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $subtotal
            ]);
        }

        // Remove purchased items from wishlist
        $wishlistRemove = db()->prepare("
        DELETE FROM wishlist
        WHERE user_id = ?
        AND product_id IN (
        SELECT product_id FROM order_items WHERE order_id = ?
            )
        ");
        
$wishlistRemove->execute([$_SESSION['user_id'], $order_id]);

        // Clear cart
        $stmt = db()->prepare("
            DELETE FROM shopping_cart
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);

        // Commit
        db()->commit();

        redirect("order_detail.php?id=$order_id");

    } catch (Exception $e) {
        db()->rollBack();
        die("Checkout failed: " . $e->getMessage());
    }
}