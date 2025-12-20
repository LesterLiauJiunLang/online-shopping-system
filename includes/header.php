<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../lib/base.php';  // Fixed path
require_once __DIR__ . '/../lib/auth.php'; 
check_remember_token(); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Shopping System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
        .header { background: #333; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; margin: 0 15px; }
        .header img { width: 36px; height: 36px; border-radius: 5px; border: 1px solid #666; object-fit: cover; }
        .header .profile { display: flex; align-items: center; gap: 10px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background: white; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <?php if (is_logged_in()): ?>
                <a href="dashboard.php">Dashboard</a>
                <?php if (is_admin()): ?>
                    <a href="admin/users.php">Manage Members</a>
                <?php else: ?>
                    <a href="shop.php">Browse Products</a>
                    <a href="cart.php">Cart</a>
                    <a href="orders.php">My Orders</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>

        <?php if (is_logged_in()): ?>
        <div class="profile">
            <a href="profile.php">
                <img src="<?= base('uploads/profile/' . encode($_SESSION['photo'] ?? 'avatar.png')) ?>" alt="Profile">
            </a>
            <span><?= encode($_SESSION['username']) ?> (<?= encode($_SESSION['role']) ?>)</span>
            <a href="logout.php">Logout</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="container">