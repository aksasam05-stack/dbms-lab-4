
<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT owner_id, password_hash FROM canteen_owner WHERE username = ?"
    );

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $owner = mysqli_fetch_assoc($result);

    if ($owner && password_verify($password, $owner['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['owner_id'] = $owner['owner_id'];
        $_SESSION['owner_username'] = $username;

        header("Location: owner_dashboard.php");
        exit();
    } else {
        $error = "Incorrect username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Owner Login - RG Canteen</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="navbar">
    <h2>🍴 RG Canteen</h2>
    <nav><a href="order.php">Home</a></nav>
</header>

<div class="order-form" style="max-width:450px;margin:60px auto;">
    <h2>Owner Login</h2>
    <p>Sign in to manage canteen orders.</p>

    <?php if ($error !== ''): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button class="primary-btn" type="submit">Login</button>
    </form>
</div>
</body>
</html>
