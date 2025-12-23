<?php
require __DIR__ . '/../../app/models/_base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT sc.product_id, sc.quantity, p.price
    FROM shopping_cart sc
    JOIN products p ON p.product_id = sc.product_id
    WHERE sc.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$cart_items) {
    redirect('cart.php');
}

if (is_get()) {
    require_once __DIR__ . "/../../includes/header.php";
?>
<h2>Confirm Checkout</h2>

<form method="post">
    <label>
        Shipping Address<br>
        <textarea name="shipping_address" required></textarea>
    </label><br><br>

    <label>
        Payment Method
        <select name="payment_method" required>
            <option value="">-- Select --</option>
            <option value="Card">Card</option>
            <option value="Online Banking">Online Banking</option>
            <option value="Cash on Delivery">Cash on Delivery</option>
        </select>
    </label><br><br>

    <button type="submit">Place Order</button>
    <a href="cart.php">Cancel</a>
</form>

<?php
    require_once __DIR__ . "/../../includes/footer.php";
    exit();
}

if (is_post()) {

    $shipping = req('shipping_address');
    $payment  = req('payment_method');

    try {
        $db->beginTransaction();

        // 1️⃣ Create order
        $stmt = $db->prepare("
            INSERT INTO orders (user_id, order_date, total_amount, status, shipping_address, payment_method, payment_status)
            VALUES (?, NOW(), 0, 'Pending', ?, ?, 'Unpaid')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $shipping,
            $payment
        ]);

        $order_id = $db->lastInsertId();

        // 2️⃣ Insert order items
        $total = 0;

        foreach ($cart_items as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;

            $stmt = $db->prepare("
                INSERT INTO order_item (order_id, product_id, quantity, unit_price, subtotal)
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

        // 3️⃣ Update total
        $stmt = $db->prepare("
            UPDATE orders
            SET total_amount = ?
            WHERE order_id = ?
        ");
        $stmt->execute([$total, $order_id]);

        // 4️⃣ Clear cart
        $stmt = $db->prepare("
            DELETE FROM shopping_cart
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);

        // 5️⃣ Commit
        $db->commit();

        redirect("order_detail.php?id=$order_id");

    } catch (Exception $e) {
        $db->rollBack();
        die("Checkout failed: " . $e->getMessage());
    }
}
