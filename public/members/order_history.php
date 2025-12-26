<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';

// --- Sorting parameters ---
$sort_field = $_GET['field'] ?? 'order_date'; // default sort by date
$sort_order = $_GET['order'] ?? 'desc';       // default descending

// Validate input to prevent SQL injection
$allowed_fields = ['order_id', 'order_date'];
$allowed_orders = ['asc', 'desc'];

if (!in_array($sort_field, $allowed_fields)) $sort_field = 'order_date';
if (!in_array(strtolower($sort_order), $allowed_orders)) $sort_order = 'desc';

$sort_order_upper = strtoupper($sort_order);
// --- Pagination Setup ---
$limit = 10; // Show 10 orders per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

// Count total orders for pagination
$countStmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$countStmt->execute([$_SESSION['user_id']]);
$total_orders = $countStmt->fetchColumn();

$total_pages = ceil($total_orders / $limit);
$offset = ($page - 1) * $limit;

// --- Fetch orders with sorting & pagination ---
$stmt = db()->prepare("
    SELECT *
    FROM orders
    WHERE user_id = ?
    ORDER BY $sort_field $sort_order_upper
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . "/../../includes/header.php";
?>

<link rel="stylesheet" href="../../assets/css/orders.css">

<div class="order-history-container">
    <h2 class="order-history-title">My Orders</h2>

    <?php if ($total_pages > 1): ?>
<nav class="pagination">

    <!-- Prev Button (disabled if first page) -->
    <span class="page-btn <?= ($page <= 1) ? 'disabled' : '' ?>">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&field=<?= $sort_field ?>&order=<?= $sort_order ?>">« Prev</a>
        <?php else: ?>
            « Prev
        <?php endif; ?>
    </span>

    <!-- Numbered Pages -->
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a class="page-number <?= $i == $page ? 'active' : '' ?>"
           href="?page=<?= $i ?>&field=<?= $sort_field ?>&order=<?= $sort_order ?>">
           <?= $i ?>
        </a>
    <?php endfor; ?>

    <!-- Next Button (disabled if last page) -->
    <span class="page-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&field=<?= $sort_field ?>&order=<?= $sort_order ?>">Next »</a>
        <?php else: ?>
            Next »
        <?php endif; ?>
    </span>

</nav>
<?php endif; ?>



    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <div class="empty-orders-icon">📦</div>
            <h3>No Orders Yet</h3>
            <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
            <a href="products.php" class="shop-now-btn">Start Shopping</a>
        </div>
    <?php else: ?>
        <!-- Sorting Links -->
        <div class="orders-sorting">
            Sort by: 
            <a href="?field=order_id&order=asc">Order ↑</a> | 
            <a href="?field=order_id&order=desc">Order ↓</a> | 
        </div>

        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $status_class = 'status-' . strtolower($order['status']); ?>
                    <tr>
                        <td data-label="Order ID"><?= $order['order_id'] ?>.</td>
                        <td data-label="Date"><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                        <td data-label="Total">RM <?= number_format($order['total_amount'], 2) ?></td>
                        <td data-label="Status">
                            <span class="status-badge <?= $status_class ?>">
                                <?= encode($order['status']) ?>
                            </span>
                        </td>
                        <td data-label="Action">
                            <a href="order_detail.php?id=<?= $order['order_id'] ?>" class="view-order-btn">
                                View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="cart-actions">
        <a href="products.php" class="back-to-orders-btn">← Continue Shopping</a>
    </div>
</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
