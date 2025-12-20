<?php
<<<<<<< HEAD
// logout.php - PROCEDURAL VERSION

// Load the necessary files in correct order
require_once '../lib/base.php';
require_once '../config/database.php';
require_once '../lib/auth.php';

// Call the logout function directly
logout();
=======
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
>>>>>>> origin/LoginAndProductPart
?>