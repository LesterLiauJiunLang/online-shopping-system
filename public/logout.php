<?php
session_start();

// Start a new session to store the logout message
session_regenerate_id(true);

// Set logout success message
$_SESSION['logout_message'] = "You have been successfully logged out.";

// Destroy all old session data except our message
foreach ($_SESSION as $key => $value) {
    if ($key !== 'logout_message') {
        unset($_SESSION[$key]);
    }
}

// Clear remember me cookie
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, "/");
}

// Redirect to index.php with correct path
header("Location: /online-shopping-system-product/public/index.php");
exit();
?>