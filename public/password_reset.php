<?php
require_once '../includes/header.php';
require_once '../lib/base.php';     
require_once '../lib/auth.php';    

$error = '';
$success = '';

// Process password reset request
if (is_post() && post('email')) {
    $email = post('email');
    
    $token = create_password_reset($email);
    
    if ($token) {
        $success = "Password reset link has been sent to your email. Token: " . $token;
    } else {
        $error = "Email not found in our system.";
    }
}

// Process password reset with token
if (is_post() && post('token')) {
    $token = post('token');
    $new_password = post('new_password');
    $confirm_password = post('confirm_password');
    
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $result = reset_password($token, $new_password);
        
        if ($result === "success") {
            $success = "Password reset successful! You can now login with your new password.";
        } else {
            $error = $result;
        }
    }
}
?>

<div class="form-container">
    <h2>Password Reset</h2>
    
    <?php if ($error): ?>
        <div class="error-message"><?php echo encode($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success-message"><?php echo encode($success); ?></div>
    <?php endif; ?>
    
    <?php if (!get('token')): ?>
        <!-- Request reset form -->
        <form method="POST" action="">
            <p>Enter your email address to receive a password reset link.</p>
            
            <div class="form">
                <label for="email">Email:</label>
                <?php html_text('email', 'type="email"'); ?>
                <?php err('email'); ?>
            </div>
            
            <button type="submit" style="width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Send Reset Link
            </button>
        </form>
    <?php else: ?>
        <!-- Reset password form -->
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?php echo encode(get('token')); ?>">
            
            <div class="form">
                <label for="new_password">New Password:</label>
                <?php html_password('new_password'); ?>
                <?php err('new_password'); ?>
                
                <label for="confirm_password">Confirm New Password:</label>
                <?php html_password('confirm_password'); ?>
                <?php err('confirm_password'); ?>
            </div>
            
            <button type="submit" style="width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Reset Password
            </button>
        </form>
    <?php endif; ?>
    
    <div style="margin-top: 15px; text-align: center;">
        <a href="login.php">Back to Login</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>