<?php
session_start();
require_once '../lib/base.php'; // Add this
require_once '../lib/auth.php'; // Add this

// Check if already logged in 
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user email for display
global $_db;
$stmt = $_db->prepare("SELECT email, email_verified FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If somehow already verified, redirect
if ($user['email_verified']) {
    $redirect = $_SESSION['redirect_after_verify'] ?? 'shop.php';
    unset($_SESSION['redirect_after_verify']);
    header("Location: $redirect");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Verification Required</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; }
        .emoji { font-size: 64px; margin: 20px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; text-align: left; }
        .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px; font-weight: bold; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .feature-list { text-align: left; margin: 20px 0; }
        .feature-list li { margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">🔒</div>
        <h1>Email Verification Required</h1>
        
        <div class="warning">
            <strong>⚠️ Access Restricted</strong>
            <p>You need to verify your email address before accessing this page.</p>
        </div>
        
        <p>We sent a verification link to: <strong><?php echo htmlspecialchars($user['email']); ?></strong></p>
        
        <div class="feature-list">
            <p><strong>You need verification to:</strong></p>
            <ul>
                <li>✅ Complete purchases</li>
                <li>✅ View order history</li>
                <li>✅ Save items to wishlist</li>
                <li>✅ Write product reviews</li>
                <li>✅ Save shipping addresses</li>
            </ul>
        </div>
        
        <div style="margin: 30px 0;">
            <a href="resend_verification.php" class="btn">
                📨 Resend Verification Email
            </a>
            
            <a href="profile.php" class="btn btn-success">
                ⚙️ Go to Profile
            </a>
            
            <a href="shop.php" class="btn btn-warning">
                🛍️ Continue Browsing
            </a>
        </div>
        
        <div style="font-size: 14px; color: #666; margin-top: 20px;">
            <p>📌 <strong>Note:</strong> Admins don't require email verification.</p>
            <p>📧 Check your spam folder if you didn't receive the email.</p>
        </div>
    </div>
</body>
</html>