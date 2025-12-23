<?php
require __DIR__ . '/../../app/models/_base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';
$db = Database::getConnection();

if (is_post()) {
    $product_id = req('product_id');
    $quantity   = (int) req('quantity');

    if ($quantity <= 0) {
        $stmt = $db->prepare("
            DELETE FROM shopping_cart
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
    } else {
        $stmt = $db->prepare("
            UPDATE shopping_cart
            SET quantity = ?
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$quantity, $_SESSION['user_id'], $product_id]);
    }

    redirect('cart.php');
}

$stmt = $db->prepare("
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

if (!$items) {
    require_once __DIR__ . "/../../includes/header.php";
    echo "<p>Your cart is empty.</p>";
    echo "<p><a href='products.php'>Continue shopping</a></p>";
    require_once __DIR__ . "/../../includes/footer.php";
    exit();
}

$_qty = array_combine(range(1, 10), range(1, 10));

require_once __DIR__ . "/../../includes/header.php";

$total = 0;
?>

<h2>Your Shopping Cart</h2>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>Product</th>
        <th>Price</th>
        <th>Quantity</th>
        <th>Subtotal</th>
        <th>Action</th>
    </tr>

    <?php foreach ($items as $item): ?>
        <?php
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;
        ?>
        <tr>
            <td><?= encode($item['name']) ?></td>
            <td>RM <?= number_format($item['price'], 2) ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                    <?php html_select('quantity', $_qty, null); ?>
                    <button type="submit">Update</button>
                </form>
            </td>
            <td>RM <?= number_format($subtotal, 2) ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                    <input type="hidden" name="quantity" value="0">
                    <button type="submit">Remove</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>

    <tr>
        <td colspan="3" align="right"><strong>Total</strong></td>
        <td colspan="2"><strong>RM <?= number_format($total, 2) ?></strong></td>
    </tr>
</table>

<p>
    <a href="products.php">Continue Shopping</a>
    |
    <a href="checkout.php"><strong>Checkout</strong></a>
</p>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
