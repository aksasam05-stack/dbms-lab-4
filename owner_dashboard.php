<?php
require 'owner_auth.php';
include 'db.php';

if (empty($_SESSION['owner_csrf'])) {
    $_SESSION['owner_csrf'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['owner_csrf'];
$message = '';
$confirm_mode = '';
$confirm_ids = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($token, $posted_token)) {
        die('Invalid request. Please refresh the page.');
    }

    if (isset($_POST['ask_clear_all'])) {
        $confirm_mode = 'all';
    } elseif (isset($_POST['ask_delete_selected'])) {
        $ids = $_POST['order_ids'] ?? [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) $confirm_ids[] = $id;
        }
        $confirm_ids = array_values(array_unique($confirm_ids));
        if (empty($confirm_ids)) {
            $message = 'Please select at least one order.';
        } else {
            $confirm_mode = 'selected';
        }
    } elseif (isset($_POST['confirm_clear_all'])) {
        if (($_POST['confirmation'] ?? '') === 'YES') {
            if (!mysqli_query($conn, 'DELETE FROM orders')) {
                $message = 'Could not clear orders. Check whether another table references orders.';
            } else {
                $message = 'All orders have been cleared successfully.';
            }
        } else {
            $message = 'Clear all was cancelled.';
        }
    } elseif (isset($_POST['confirm_delete_selected'])) {
        $ids = $_POST['order_ids'] ?? [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) { return $id > 0; })));
        if (empty($ids)) {
            $message = 'No orders were selected.';
        } else {
            $stmt = mysqli_prepare($conn, 'DELETE FROM orders WHERE COALESCE(NULLIF(order_group_id, 0), order_id) = ?');
            foreach ($ids as $id) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
            $message = 'Selected orders have been deleted.';
        }
    }
}

$sql = "SELECT
            COALESCE(NULLIF(orders.order_group_id, 0), orders.order_id) AS display_order_id,
            customer.name,
            GROUP_CONCAT(CONCAT(food_item.item_name, ' (x', orders.quantity, ')') ORDER BY orders.order_id SEPARATOR ', ') AS items,
            SUM(food_item.price * orders.quantity) AS total_amount,
            MIN(orders.order_date) AS order_date,
            MAX(orders.order_id) AS latest_order_id
        FROM orders
        INNER JOIN customer ON orders.customer_id = customer.customer_id
        INNER JOIN food_item ON orders.item_id = food_item.item_id
        GROUP BY COALESCE(NULLIF(orders.order_group_id, 0), orders.order_id), customer.customer_id, customer.name, orders.order_date
        ORDER BY latest_order_id DESC";
$result = mysqli_query($conn, $sql);
if (!$result) die('Unable to load orders.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owner Dashboard - RG Canteen</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body>
<header class="navbar">
    <h2>🍴 RG Canteen</h2>
    <nav><a href="order.php">Home</a><a href="owner_dashboard.php" class="active">Manage Orders</a><a href="owner_logout.php">Logout</a></nav>
</header>
<section class="hero"><div><h1>Owner Dashboard</h1><p>Manage RG Canteen orders here.</p></div><div class="hero-icon">📋</div></section>
<h2 class="section-title">Manage Customer Orders</h2>

<?php if ($message !== ''): ?><div class="notice"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<?php if ($confirm_mode !== ''): ?>
    <section class="cart-section" style="max-width:700px;margin:25px auto;">
        <?php if ($confirm_mode === 'all'): ?>
            <h2>Confirm Clear All Orders</h2>
            <p>This will permanently delete every order. Customer and food records will remain.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="confirmation" value="YES">
                <button type="submit" name="confirm_clear_all" class="primary-btn" style="background:#b83232;">Yes, Clear All Orders</button>
                <a class="back-link" href="owner_dashboard.php">Cancel</a>
            </form>
        <?php else: ?>
            <h2>Confirm Delete Selected Orders</h2>
            <p>You are about to permanently delete <?php echo count($confirm_ids); ?> selected order(s).</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
                <?php foreach ($confirm_ids as $id): ?>
                    <input type="hidden" name="order_ids[]" value="<?php echo (int)$id; ?>">
                <?php endforeach; ?>
                <button type="submit" name="confirm_delete_selected" class="primary-btn" style="background:#b83232;">Yes, Delete Selected</button>
                <a class="back-link" href="owner_dashboard.php">Cancel</a>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="report-wrap">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
            <button type="submit" name="ask_delete_selected" class="primary-btn">Delete Selected</button>
            <button type="submit" name="ask_clear_all" class="primary-btn" style="background:#b83232;">Clear All Orders</button>
        </div>
        <table class="report-table">
            <thead><tr><th>Select</th><th>Order ID</th><th>Customer Name</th><th>Food Items</th><th>Total Amount</th><th>Order Date</th><th>Order Time</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><input type="checkbox" name="order_ids[]" value="<?php echo (int)$row['display_order_id']; ?>"></td>
                        <td><?php echo 'ORD-' . str_pad((string)$row['display_order_id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['items']); ?></td>
                        <td>₹<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                        <td><?php echo date('d-m-Y', strtotime($row['order_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($row['order_date'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="report-empty">No orders available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>
<footer>❤️ RG Canteen – Owner Dashboard ❤️</footer>
</body>
</html>
