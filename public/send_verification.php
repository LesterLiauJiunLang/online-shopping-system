<?php
session_start();
require_once '../lib/base.php';  
require_once '../lib/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}

$uid = $_SESSION['user_id'];

// Get user email from database
try {
    global $_db;
    $stmt = $_db->prepare("SELECT email, email_verified FROM users WHERE user_id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    $email = $user['email'];
    $email_verified = $user['email_verified'] ?? 0;
    
    if (empty($email)) {
        $response = ['success' => false, 'message' => 'Email not found.'];
        sendResponse($response);
    }
    
    // If already verified
    if ($email_verified) {
        $response = ['success' => false, 'message' => 'Email is already verified.'];
        sendResponse($response);
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Database error occurred.'];
    sendResponse($response);
}

// Generate a new verification code
$verification_code = sprintf('%06d', mt_rand(0, 999999));
$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

try {
    // Update user with new verification code
    $stmt = $_db->prepare("
        UPDATE users 
        SET verification_code = :code,
            verification_code_expires = :expires
        WHERE user_id = :user_id
    ");
    
    $result = $stmt->execute([
        ':code' => $verification_code,
        ':expires' => $expires_at,
        ':user_id' => $uid
    ]);
    
    if ($result) {
        // Send verification email
        $mail = get_mail();
        $mail->addAddress($email);
        $mail->Subject = 'Email Verification - Online Shopping System';
        
        $verification_link = base("verify_email_code.php");
        
        $mail->Body = "
            <h2>Email Verification</h2>
            <p>Hello " . htmlspecialchars($_SESSION['username'] ?? 'User') . ",</p>
            <p>Your verification code is: <strong>$verification_code</strong></p>
            <p>Enter this code on the verification page or click the link below:</p>
            <p><a href='$verification_link'>Verify Email Address</a></p>
            <p>This code will expire in 10 minutes.</p>
            <p>If you didn't request this, please ignore this email.</p>
            <p>Best regards,<br>Online Shopping System Team</p>
        ";
        
        $mail->AltBody = "Your verification code: $verification_code\n\nVisit: $verification_link\n\nCode expires in 10 minutes.";
        
        if ($mail->send()) {
            $response = [
                'success' => true, 
                'message' => 'Verification email sent to ' . $email . '. Please check your inbox (and spam folder).',
                'has_code' => true
            ];
            sendResponse($response);
        } else {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            $response = ['success' => false, 'message' => 'Failed to send verification email. Please try again.'];
            sendResponse($response);
        }
        
    } else {
        $response = ['success' => false, 'message' => 'Failed to generate verification code.'];
        sendResponse($response);
    }
    
} catch (Exception $e) {
    error_log("Verification email error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'An error occurred. Please try again.'];
    sendResponse($response);
}

function sendResponse($response) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        // For non-AJAX requests, use session messages
        $_SESSION['notification'] = $response['message'];
        $_SESSION['notification_type'] = $response['success'] ? 'success' : 'error';
        redirect('profile.php');
        exit();
    }
}
?>