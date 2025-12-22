<?php
// verify_email_code.php
session_start();
require_once '../lib/base.php';
require_once '../lib/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// Get user data and check if already verified
try {
    $stmt = $_db->prepare("SELECT email, email_verified, verification_code, verification_code_expires FROM users WHERE user_id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['notification'] = 'User not found.';
        $_SESSION['notification_type'] = 'error';
        redirect('profile.php');
        exit();
    }
    
    $email = $user['email'];
    $email_verified = $user['email_verified'] ?? 0;
    $has_existing_code = !empty($user['verification_code']);
    $code_expired = false;
    
    // Check if existing code is expired
    if ($has_existing_code && !empty($user['verification_code_expires'])) {
        $code_expired = strtotime($user['verification_code_expires']) < time();
    }
    
    // If already verified, redirect to profile
    if ($email_verified) {
        $_SESSION['notification'] = 'Email is already verified.';
        $_SESSION['notification_type'] = 'info';
        redirect('profile.php');
        exit();
    }
    
    // Only send new email if no code exists OR code is expired
    $send_email = !$has_existing_code || $code_expired;
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['notification'] = 'Database error occurred.';
    $_SESSION['notification_type'] = 'error';
    redirect('profile.php');
    exit();
}

// Generate and send verification code if needed
if (isset($send_email) && $send_email) {
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
                <p>Enter this code below to verify your email address.</p>
                <p>This code will expire in 10 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
                <p>Best regards,<br>Online Shopping System Team</p>
            ";
            
            $mail->AltBody = "Your verification code: $verification_code\n\nCode expires in 10 minutes.";
            
            if ($mail->send()) {
                $_SESSION['notification'] = 'Verification email sent to ' . $email . '. Please check your inbox.';
                $_SESSION['notification_type'] = 'success';
            } else {
                error_log("Email sending failed: " . $mail->ErrorInfo);
                $_SESSION['notification'] = 'Failed to send verification email. Please try again.';
                $_SESSION['notification_type'] = 'error';
            }
        }
    } catch (Exception $e) {
        error_log("Verification email error: " . $e->getMessage());
    }
}

// Handle code submission
$error = '';
$success = '';

if (is_post()) {
    $code = req('verification_code');
    
    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } else {
        try {
            // Check if code is valid and not expired
            $stmt = $_db->prepare("
                SELECT user_id FROM users 
                WHERE user_id = ? 
                AND verification_code = ?
                AND verification_code_expires > NOW()
            ");
            $stmt->execute([$uid, $code]);
            
            if ($stmt->fetch()) {
                // Code is valid - verify email
                $updateStmt = $_db->prepare("
                    UPDATE users 
                    SET email_verified = 1, 
                        verification_code = NULL,
                        verification_code_expires = NULL
                    WHERE user_id = ?
                ");
                $updateStmt->execute([$uid]);
                
                $success = 'Email verified successfully!';
                
                // Redirect to profile after 2 seconds
                header("Refresh: 2; url=profile.php");
            } else {
                $error = 'Invalid or expired verification code.';
            }
        } catch (Exception $e) {
            error_log("Verification error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

$_title = 'Verify Email';
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../assets/css/verify-email.css">

<div class="verification-container">
    <div class="verification-card">
        <div class="card-header">
            <h2><i class="fas fa-envelope"></i> Verify Your Email</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <p>Redirecting to profile page...</p>
                </div>
            <?php else: ?>
                <!-- Notification Area -->
                <?php if (isset($_SESSION['notification'])): ?>
                    <div class="notification <?php echo $_SESSION['notification_type'] ?? 'info'; ?>">
                        <?php 
                        echo htmlspecialchars($_SESSION['notification']);
                        unset($_SESSION['notification']);
                        unset($_SESSION['notification_type']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$send_email && $has_existing_code && !$code_expired): ?>
                    <div class="notification info">
                        <i class="fas fa-info-circle"></i>
                        A verification code has already been sent to your email. 
                        <?php if ($has_existing_code): ?>
                            It expires in <?php echo ceil((strtotime($user['verification_code_expires']) - time()) / 60); ?> minutes.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="verification-info">
                    <div class="info-icon">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div class="info-content">
                        <h4>Check Your Email</h4>
                        <p>We've sent a verification code to:</p>
                        <p class="user-email"><?php echo htmlspecialchars($email); ?></p>
                        <p>Enter the 6-digit code below to verify your email address.</p>
                    </div>
                </div>
                
                <form method="POST" action="" class="verification-form">
                    <div class="form-group">
                        <label for="verification_code">Verification Code</label>
                        <input type="text" id="verification_code" name="verification_code" 
                               maxlength="6" pattern="\d{6}" required
                               placeholder="Enter 6-digit code" autocomplete="off">
                        <small class="form-text">Enter the 6-digit code sent to your email</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-verify">
                            <i class="fas fa-check"></i> Verify Email
                        </button>
                        
                        <button type="button" class="btn btn-secondary" id="resend-code">
                            <i class="fas fa-redo"></i> Resend Code
                        </button>
                        
                        <a href="profile.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                    </div>
                </form>
                
                <div class="verification-tips">
                    <h5><i class="fas fa-lightbulb"></i> Tips:</h5>
                    <ul>
                        <li>Check your spam folder if you don't see the email</li>
                        <li>The code expires in 10 minutes</li>
                        <li>Make sure you entered the correct email address</li>
                        <li>Codes are only valid for one use</li>
                    </ul>
                </div>
                
                <!-- Email Status -->
                <div class="email-status">
                    <div class="status-item">
                        <span class="status-label">Email Status:</span>
                        <span class="status-value not-verified">Not Verified</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Email Address:</span>
                        <span class="status-value"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <?php if ($has_existing_code && !$code_expired): ?>
                    <div class="status-item">
                        <span class="status-label">Code Expires:</span>
                        <span class="status-value"><?php echo date('H:i:s', strtotime($user['verification_code_expires'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/js/verify-email.js"></script>

<?php include '../includes/footer.php'; ?>