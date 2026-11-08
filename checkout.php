
<?php
session_start();
include 'db.php';

$message = "";
$message_type = "error";

$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header("Location: order.php");
    exit();
}

// Get food details and prices from the database.
$result = mysqli_query($conn, "SELECT item_id, item_name, price FROM food_item");

if (!$result) {
    die("Unable to load checkout details.");
}

$food_items = [];

while ($row = mysqli_fetch_assoc($result)) {
    $food_items[(int)$row['item_id']] = $row;
}

$cart_items = [];
$total = 0;

// Build the checkout summary.
foreach ($cart as $item_id => $quantity) {
    $item_id = (int)$item_id;
    $quantity = (int)$quantity;

    if (!isset($food_items[$item_id]) || $quantity < 1 || $quantity > 10) {
        $message = "One or more items in your cart are unavailable. Please return to the menu.";
        continue;
    }

    $item = $food_items[$item_id];
    $price = (float)$item['price'];
    $amount = $price * $quantity;

    $cart_items[] = [
        'item_id' => $item_id,
        'name' => $item['item_name'],
        'price' => $price,
        'quantity' => $quantity,
        'amount' => $amount
    ];

    $total += $amount;
}

if (empty($cart_items) && $message === "") {
    $message = "Your cart is empty. Please select food items.";
}

// Place the order.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['customer_type'] ?? '';
    $phone = trim($_POST['phone'] ?? '');

    $allowed_types = ['Student', 'Faculty', 'Staff', 'Visitor'];

    if ($name === '' || $phone === '' || !in_array($type, $allowed_types, true)) {
        $message = "Please enter all your details correctly.";

    } elseif (empty($cart_items) || count($cart_items) !== count($cart)) {
        $message = "Your cart contains an unavailable item. Please return to the menu.";

    } else {

        $conn->begin_transaction();

        try {
            // Check whether the customer already exists.
            $check = mysqli_prepare(
                $conn,
                "SELECT customer_id FROM customer WHERE phone = ?"
            );

            mysqli_stmt_bind_param($check, "s", $phone);
            mysqli_stmt_execute($check);

            $customer_result = mysqli_stmt_get_result($check);

            if (mysqli_num_rows($customer_result) > 0) {
                $row = mysqli_fetch_assoc($customer_result);
                $customer_id = (int)$row['customer_id'];

            } else {
                // Insert a new customer.
                $insert_customer = mysqli_prepare(
                    $conn,
                    "INSERT INTO customer (name, phone, customer_type)
                     VALUES (?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $insert_customer,
                    "sss",
                    $name,
                    $phone,
                    $type
                );

                mysqli_stmt_execute($insert_customer);
                $customer_id = mysqli_insert_id($conn);
            }

         $group_id = null;

// Save the same date and time for all items in this order.
$order_date = date('Y-m-d H:i:s');

// Insert all selected food items as one grouped order.
foreach ($cart_items as $item) {

                $item_id = $item['item_id'];
                $quantity = $item['quantity'];

                if ($group_id === null) {

                    $order_stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO orders
                         (customer_id, item_id, quantity, order_date, order_group_id)
                         VALUES (?, ?, ?, ?, NULL)"
                    );

                    mysqli_stmt_bind_param(
                        $order_stmt,
                        "iiis",
 			$customer_id,
			$item_id,
			$quantity,
			$order_date
                    );

                    mysqli_stmt_execute($order_stmt);

                    $group_id = mysqli_insert_id($conn);

                    // Use the first order ID as the group ID.
                    $update = mysqli_prepare(
                        $conn,
                        "UPDATE orders SET order_group_id = ?
                         WHERE order_id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $update,
                        "ii",
                        $group_id,
                        $group_id
                    );

                    mysqli_stmt_execute($update);

                } else {

                    $order_stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO orders
                         (customer_id, item_id, quantity, order_date, order_group_id)
                         VALUES (?, ?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $order_stmt,
                       "iiisi",
			$customer_id,
			$item_id,
			$quantity,
			$order_date,
			$group_id
                    );

                    mysqli_stmt_execute($order_stmt);
                }
            }

            $conn->commit();

            // Clear the cart only after a successful order.
            unset($_SESSION['cart']);

            $_SESSION['success'] = "Your order has been placed successfully!";

            header("Location: order.php");
            exit();

        } catch (Throwable $e) {

            $conn->rollback();

            $message = "Something went wrong while placing your order. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>RG Canteen - Checkout</title>

    <link rel="stylesheet" href="style.css?v=4">
</head>

<body>

<header class="navbar">
    <h2>🍴 RG Canteen</h2>

    <nav>
        <a href="order.php">Home</a>
        <a href="order.php#menu">Menu</a>
       <a href="owner_login.php">Owner Login</a>
    </nav>
</header>

<section class="hero">
    <div>
        <h1>Checkout</h1>
        <p>Almost there! Your delicious food is waiting ❤️</p>
    </div>

    <div class="hero-icon">🛒</div>
</section>

<h2 class="section-title">Review Your Order</h2>

<?php if ($message !== ''): ?>
    <div class="notice notice-error">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="checkout-layout">

    <section class="cart-section checkout-summary">

        <h2>🛍️ Order Summary</h2>

        <?php foreach ($cart_items as $item): ?>

            <div class="cart-line">
                <span>
                    <?php echo htmlspecialchars($item['name']); ?>
                    × <?php echo $item['quantity']; ?>
                </span>

                <strong>
                    ₹<?php echo number_format($item['amount'], 2); ?>
                </strong>
            </div>

        <?php endforeach; ?>

        <h3>
            Total: ₹<?php echo number_format($total, 2); ?>
        </h3>

        <p class="checkout-note">
            Please check your selected items before placing your order.
        </p>

        <a href="order.php#menu" class="back-link">
            ← Back to Menu
        </a>

    </section>

    <section class="order-section">

        <div class="order-form">

            <h2>Customer Details</h2>

            <p class="form-intro">
                Enter your details to complete your order.
            </p>

            <form method="POST" id="checkoutForm">

                <label for="customerName">Your Name</label>

                <input
                    id="customerName"
                    type="text"
                    name="name"
                    placeholder="Enter your name"
                    autocomplete="name"
                    required
                >

                <label for="customerType">Customer Type</label>

                <select id="customerType" name="customer_type" required>
                    <option value="" selected disabled>
                        Select Customer Type
                    </option>

                    <option value="Student">Student</option>
                    <option value="Faculty">Faculty</option>
                    <option value="Staff">Staff</option>
                    <option value="Visitor">Visitor</option>
                </select>

                <label for="phone">Phone Number</label>

                <input
                    id="phone"
                    type="tel"
                    name="phone"
                    placeholder="Enter phone number"
                    autocomplete="tel"
                    required
                >

                <button
                    type="submit"
                    name="submit"
                    value="1"
                    class="primary-btn"
                    <?php echo empty($cart_items) || count($cart_items) !== count($cart) ? 'disabled' : ''; ?>
                >
                    Place Order ❤️
                </button>

            </form>

        </div>

    </section>

</div>

<footer> RG Canteen – Eat Happy! </footer>

</body>
</html>
