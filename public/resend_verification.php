<?php
session_start();
require_once '../lib/base.php';
require_once '../lib/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if already verified
$stmt = $_db->prepare('SELECT email_verified, email, username FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['email_verified']) {
    $_SESSION['info'] = 'Your email is already verified.';
    header('Location: profile.php');
    exit;
}

// Generate new 6-digit code
$new_code = sprintf('%06d', random_int(0, 999999));
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

try {
    // Update code in database
    $stmt = $_db->prepare('
        UPDATE users 
        SET verification_code = ?, 
            verification_code_expires = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ');
    
    if ($stmt->execute([$new_code, $expires, $user_id])) {
        // Send email with code
        if (send_verification_code_email($user['email'], $user['username'], $new_code)) {
            $_SESSION['success'] = '✅ New verification code sent to ' . htmlspecialchars($user['email']);
        } else {
            $_SESSION['error'] = '❌ Failed to send email. Please try again later.';
        }
    } else {
        $_SESSION['error'] = '❌ Failed to generate verification code.';
    }
} catch (PDOException $e) {
    error_log("Resend verification error: " . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred.';
}

header('Location: verify_email_code.php');
exit;
?>