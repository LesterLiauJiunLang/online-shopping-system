<?php
require_once 'includes/header.php';

$error = '';
$success = '';
$auth = new Auth();

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = Helpers::sanitize($_POST['email']);
    
    $token = $auth->createPasswordReset($email);
    
    if ($token) {
        $success = "Password reset link has been sent to your email. Token: " . $token;
    } else {
        $error = "Email not found in our system.";
    }
}

// Process password reset with token
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['token'])) {
    $token = Helpers::sanitize($_POST['token']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $result = $auth->resetPassword($token, $new_password);
        
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
    
    <?php echo Helpers::displayError($error); ?>
    <?php echo Helpers::displaySuccess($success); ?>
    
    <?php if (!isset($_GET['token'])): ?>
        <!-- Request reset form -->
        <form method="POST" action="">
            <p>Enter your email address to receive a password reset link.</p>
            <?php echo Helpers::inputField('email', 'email', '', 'Your Email', true); ?>
            
            <button type="submit" style="width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Send Reset Link
            </button>
        </form>
    <?php else: ?>
        <!-- Reset password form -->
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?php echo Helpers::sanitize($_GET['token']); ?>">
            
            <?php echo Helpers::inputField('password', 'new_password', '', 'New Password', true); ?>
            <?php echo Helpers::inputField('password', 'confirm_password', '', 'Confirm New Password', true); ?>
            
            <button type="submit" style="width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Reset Password
            </button>
        </form>
    <?php endif; ?>
    
    <div style="margin-top: 15px; text-align: center;">
        <a href="login.php">Back to Login</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>