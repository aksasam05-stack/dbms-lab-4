<?php
session_start();
include 'db.php';

$message = '';
$cart = $_SESSION['cart'] ?? [];
$allowed_categories = ['All', 'Drinks', 'Snacks', 'Meals', 'Other'];
$category = $_GET['category'] ?? 'All';
if (!in_array($category, $allowed_categories, true)) {
    $category = 'All';
}

if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Update or remove one item from the session cart.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $action = $_POST['cart_action'];

    if ($item_id > 0) {
        if ($action === 'remove') {
            unset($cart[$item_id]);
            $message = 'Item removed from your cart.';
        } elseif ($action === 'update') {
            $quantity = (int)($_POST['quantity'] ?? 0);
            $check = mysqli_prepare($conn, "SELECT item_id FROM food_item WHERE item_id = ?");
            mysqli_stmt_bind_param($check, 'i', $item_id);
            mysqli_stmt_execute($check);
            $check_result = mysqli_stmt_get_result($check);

            if (mysqli_num_rows($check_result) > 0 && $quantity >= 1 && $quantity <= 10) {
                $cart[$item_id] = $quantity;
                $message = 'Cart updated.';
            } else {
                $message = 'Choose a quantity between 1 and 10.';
            }
            mysqli_stmt_close($check);
        }
    }

    $_SESSION['cart'] = $cart;
    header('Location: order.php?category=' . urlencode($category) . '#item-' . $item_id, true, 303);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_checkout'])) {
    if (empty($cart)) {
        $message = 'Please add at least one food item before checkout.';
    } else {
        header('Location: checkout.php');
        exit();
    }
}

$items = mysqli_query($conn, "SELECT * FROM food_item ORDER BY item_id");
if (!$items) {
    die('Unable to load the menu right now.');
}

$menu_details = [
    'tea' => ['category' => 'Drinks', 'description' => 'Hot, freshly brewed chai.', 'emoji' => '🍵', 'image' => 'tea.jpg'],
    'coffee' => ['category' => 'Drinks', 'description' => 'Strong, hot filter coffee.', 'emoji' => '☕', 'image' => 'coffee.jpg'],
    'sandwich' => ['category' => 'Snacks', 'description' => 'Grilled with veggies and cheese.', 'emoji' => '🥪', 'image' => 'sandwich.jpg'],
    'veg puff' => ['category' => 'Snacks', 'description' => 'Flaky pastry, spiced veg filling.', 'emoji' => '🥟', 'image' => 'vegpuff.jpg'],
    'chicken roll' => ['category' => 'Snacks', 'description' => 'Crispy golden chicken rolls.', 'emoji' => '🌯', 'image' => 'chickenroll.jpg'],
    'meals' => ['category' => 'Meals', 'description' => 'Rice, curries, thoran and papad.', 'emoji' => '🍛', 'image' => 'meals.jpg'],
    'fresh lime' => ['category' => 'Drinks', 'description' => 'Cool lime soda, sweet or salt.', 'emoji' => '🍋', 'image' => 'freshlime.jpg'],
    'burger' => ['category' => 'Snacks', 'description' => 'Crispy patty, cheese and sauce.', 'emoji' => '🍔', 'image' => 'burger.jpg']
];

$menu_rows = [];
$food_by_id = [];
while ($item = mysqli_fetch_assoc($items)) {
    $id = (int)$item['item_id'];
    $key = strtolower(trim($item['item_name']));
    $details = $menu_details[$key] ?? ['category' => 'Other', 'description' => 'Freshly prepared at RG Canteen.', 'emoji' => '🍽️', 'image' => ''];
    $item['details'] = $details;
    $menu_rows[] = $item;
    $food_by_id[$id] = $item;
}

$cart_lines = [];
$cart_total = 0;
$total_quantity = 0;
foreach ($cart as $id => $qty) {
    $id = (int)$id;
    $qty = (int)$qty;
    if (isset($food_by_id[$id]) && $qty > 0 && $qty <= 10) {
        $amount = (float)$food_by_id[$id]['price'] * $qty;
        $cart_lines[] = ['name' => $food_by_id[$id]['item_name'], 'quantity' => $qty, 'amount' => $amount];
        $cart_total += $amount;
        $total_quantity += $qty;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RG Canteen - Menu</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body>
<header class="navbar">
    <h2>🍴 RG Canteen</h2>
    <nav>
        <a href="order.php" class="active">Home</a>
        <a href="#menu">Menu</a>
        <a href="owner_login.php">Owner Login</a>
    </nav>
</header>

<section class="hero">
    <div><h1>Welcome to<br>RG Canteen!</h1><p>Good Food. Good Mood. ❤️</p></div>
    <div class="hero-icon">🍔</div>
</section>

<h2 class="section-title" id="menu">Our Menu</h2>

<?php if ($message !== ''): ?>
    <div class="notice"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="tabs">
    <?php foreach ($allowed_categories as $cat): ?>
        <a class="tab <?php echo $category === $cat ? 'active' : ''; ?>" href="order.php?category=<?php echo urlencode($cat); ?>#menu"><?php echo htmlspecialchars($cat); ?></a>
    <?php endforeach; ?>
</div>

<div class="menu-grid">
<?php foreach ($menu_rows as $item): ?>
    <?php
        $id = (int)$item['item_id'];
        $details = $item['details'];
        $qty = (int)($cart[$id] ?? 0);
        if ($category !== 'All' && $details['category'] !== $category) continue;
    ?>
    <article id="item-<?php echo $id; ?>" class="food-card <?php echo $qty > 0 ? 'selected' : ''; ?>">
        <div class="food-picture">
            <span class="food-emoji"><?php echo $details['emoji']; ?></span>
            <?php if ($details['image'] !== ''): ?>
                <img src="images/<?php echo htmlspecialchars($details['image']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
            <?php endif; ?>
        </div>
        <div class="food-details">
            <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
            <p class="food-desc"><?php echo htmlspecialchars($details['description']); ?></p>
            <div class="food-bottom">
                <span class="price">₹<?php echo number_format((float)$item['price'], 2); ?></span>
            </div>
            <form method="POST" class="quantity-form">
                <input type="hidden" name="item_id" value="<?php echo $id; ?>">
                <label>Quantity (1–10)</label>
                <input type="number" name="quantity" min="1" max="10" value="<?php echo $qty > 0 ? $qty : 1; ?>" required>
                <button type="submit" name="cart_action" value="update" class="add-btn"><?php echo $qty > 0 ? 'Update Cart' : 'Add to Cart'; ?></button>
                <?php if ($qty > 0): ?>
                    <button type="submit" name="cart_action" value="remove" class="remove-cart-btn">Remove</button>
                <?php endif; ?>
            </form>
        </div>
    </article>
<?php endforeach; ?>
</div>

<section class="cart-section" id="cartSection">
    <h2>🛒 Your Cart</h2>
    <?php if (empty($cart_lines)): ?>
        <p class="empty">Your cart is empty. Add something delicious!</p>
    <?php else: ?>
        <?php foreach ($cart_lines as $line): ?>
            <div class="cart-line">
                <span><?php echo htmlspecialchars($line['name']); ?> × <?php echo $line['quantity']; ?></span>
                <strong>₹<?php echo number_format($line['amount'], 2); ?></strong>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <h3>Total: ₹<?php echo number_format($cart_total, 2); ?></h3>
    <p><?php echo $total_quantity; ?> item(s) in your cart</p>
    <form method="POST">
        <button type="submit" name="proceed_checkout" value="1" class="primary-btn" <?php echo empty($cart_lines) ? 'disabled' : ''; ?>>Proceed to Checkout →</button>
    </form>
</section>

<footer>❤️ RG Canteen – Eat Happy! ❤️</footer>
</body>
</html>
