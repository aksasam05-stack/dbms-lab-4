
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['owner_id'])) {
    header("Location: owner_login.php");
    exit();
}
