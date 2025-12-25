<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include auth functions
require_once '../lib/auth.php';

// Check if user is logged in using the practical 7 auth() function
// First, let's check if the function exists
if (function_exists('auth')) {
    auth(); // This will redirect to login if not authenticated
} else {
    // Fallback: Check session directly
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        header("Location: login.php");
        exit();
    }
}

// Get user role from session (more reliable than $_user which might not be set)
$user_role = $_SESSION['role'] ?? 'member';

// Redirect based on role
if ($user_role === 'admin') {
    header("Location: adminDashboard.php");
    exit();
} else {
    header("Location: memberDashboard.php");
    exit();
}

// If redirect fails, show a simple page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Redirecting...</title>
    <meta http-equiv="refresh" content="2;url=memberDashboard.php">
</head>
<body>
    <p>Redirecting to your dashboard...</p>
</body>
</html>