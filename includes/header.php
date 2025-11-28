<?php
session_start();
require_once '../lib/auth.php';
require_once '../lib/helpers.php';

$auth = new Auth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Shopping System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
        .header { background: #333; color: white; padding: 15px; }
        .header a { color: white; text-decoration: none; margin: 0 15px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background: white; border-radius: 5px; }
        .form-container { max-width: 400px; margin: 50px auto; padding: 20px; }
        .nav { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <?php if ($auth->isLoggedIn()): ?>
            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> 
            (<?php echo htmlspecialchars($_SESSION['role']); ?>)
            <a href="dashboard.php">Dashboard</a>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin/members.php">Manage Members</a>
            <?php endif; ?>
            <a href="logout.php" style="float: right;">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </div>
    <div class="container">