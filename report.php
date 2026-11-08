
<?php
require 'owner_auth.php';
include 'db.php';

$sql = "SELECT
            COALESCE(NULLIF(orders.order_group_id, 0), orders.order_id) AS display_order_id,
            customer.name,
            GROUP_CONCAT(
                CONCAT(food_item.item_name, ' (x', orders.quantity, ')')
                ORDER BY orders.order_id SEPARATOR ', '
            ) AS items,
            SUM(food_item.price * orders.quantity) AS total_amount,
            DATE_FORMAT(orders.order_date, '%d-%m-%Y') AS order_date_display,
            DATE_FORMAT(orders.order_date, '%h:%i %p') AS order_time_display,
            MAX(orders.order_id) AS latest_order_id
        FROM orders
        INNER JOIN customer ON orders.customer_id = customer.customer_id
        INNER JOIN food_item ON orders.item_id = food_item.item_id
        GROUP BY
            COALESCE(NULLIF(orders.order_group_id, 0), orders.order_id),
            customer.customer_id,
            customer.name,
            orders.order_date
        ORDER BY latest_order_id DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Unable to load the order report right now.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RG Canteen – Order Report</title>
    <link rel="stylesheet" href="style.css?v=4">
</head>

<body>

<header class="navbar">
    <h2>🍴 RG Canteen</h2>

    <nav aria-label="Main navigation">
        <a href="order.php">Home</a>
        <a href="order.php#menu">Order</a>
        <a href="report.php" class="active">View Orders</a>
    </nav>
</header>

<section class="hero">
    <div>
        <h1>Order Report</h1>
        <p>All your canteen orders in one place ❤️</p>
    </div>

    <div class="hero-icon" aria-hidden="true">🧾</div>
</section>

<h2 class="section-title">Customer Orders</h2>

<div class="report-wrap">
    <table class="report-table">

        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Food Items</th>
                <th>Total Amount</th>
                <th>Order Date</th>
                <th>Order Time</th>
            </tr>
        </thead>

        <tbody>

            <?php if (mysqli_num_rows($result) > 0): ?>

                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <tr>
                        <td>
                            <?php
                            echo 'ORD-' . str_pad(
                                (string)$row['display_order_id'],
                                4,
                                '0',
                                STR_PAD_LEFT
                            );
                            ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['name']); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['items']); ?>
                        </td>

                        <td>
                            ₹<?php echo number_format((float)$row['total_amount'], 2); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['order_date_display']); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['order_time_display']); ?>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="report-empty">
                        No orders have been placed yet.
                    </td>
                </tr>

            <?php endif; ?>

        </tbody>
    </table>
</div>

<footer>❤️ RG Canteen – Eat Happy! ❤️</footer>

</body>
</html>
