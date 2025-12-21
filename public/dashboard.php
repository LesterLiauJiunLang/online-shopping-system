<?php
// dashboard.php - FIXED VERSION

// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}


// Include auth functions
require_once '../lib/auth.php';

// Call auth to check login
auth();

// Now redirect based on role
$user_role = $_user['role'] ?? $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'member';

if ($user_role === 'admin') {
    // Redirect admin to admin dashboard
    header("Location: adminDashboard.php");
    exit();
} else {
    // Redirect member to member dashboard
    header("Location: memberDashboard.php");
    exit();
}

// If for some reason redirect fails, show a simple page
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