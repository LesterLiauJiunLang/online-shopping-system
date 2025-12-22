<?php
session_start();
require_once '../app/models/UserModel.php';

$message = '';
$message_type = '';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in'])) {
    header("Location: dashboard.php");
    exit();
}

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = "Please enter your email address.";
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = 'error';
    } else {
        $userModel = new User();
        if ($userModel->emailExists($email)) {
            $message = "Password reset instructions have been sent to your email.";
            $message_type = 'success';
        } else {
            $message = "No account found with that email address.";
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Online Shopping System</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-icon">🔑</div>
            <h2>Reset Password</h2>
            <p>Enter your email to reset your password</p>
            
            <div class="instructions">
                We'll send you an email with instructions to reset your password.
            </div>
            
            <?php if ($message): ?>
                <div class="auth-message message-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span>📧</span>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required placeholder="Enter your registered email">
                    </div>
                </div>
                
                <button type="submit" class="btn-auth">Send Reset Instructions</button>
            </form>
            
            <div class="back-link">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>